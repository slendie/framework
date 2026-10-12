<?php

declare(strict_types=1);

namespace Slendie\Validation;

use BadMethodCallException;
use Closure;
use RuntimeException;
use Slendie\Support\Arr;
use Slendie\Support\Str;
use Slendie\Validation\Concerns\FormatsMessages;
use Slendie\Validation\Concerns\ValidatesAttributes;

/**
 * Motor de validação: regras estilo Laravel (`'required|email|max:255'`, arrays, objectos, closures) sobre um array de dados.
 */
class Validator
{
    use FormatsMessages;
    use ValidatesAttributes;

    /** @var array<string, mixed> regras tal como foram dadas */
    protected array $initialRules;

    /** @var array<string, list<mixed>> regras normalizadas, por atributo concreto */
    protected array $rules = [];

    /** @var array<string, string> atributo concreto => padrão com wildcard */
    protected array $implicitAttributes = [];

    protected MessageBag $messages;

    /** @var array<string, array<string, list<string>>> atributo => regra => parâmetros */
    protected array $failedRules = [];

    /** @var array<string, true> atributos removidos de validated() por regras exclude* */
    protected array $excluded = [];

    /** @var list<array{0: string|list<string>, 1: mixed, 2: Closure}> */
    protected array $sometimesRules = [];

    /** @var list<callable> */
    protected array $after = [];

    protected bool $stopOnFirstFailure = false;

    protected bool $hasRun = false;

    /** @var array<string, Closure> */
    protected array $extensions = [];

    /** @var array<string, string> */
    protected array $extensionMessages = [];

    /** @var (Closure(): PresenceVerifier)|null */
    protected ?Closure $presenceVerifierResolver = null;

    protected ?PresenceVerifier $presenceVerifier = null;

    /** @var list<string> regras que correm mesmo com valor vazio ou ausente */
    protected array $implicitRules = [
        'Accepted', 'AcceptedIf', 'Declined', 'DeclinedIf', 'Filled', 'Missing', 'MissingIf', 'MissingUnless',
        'MissingWith', 'MissingWithAll', 'Present', 'Required', 'RequiredIf', 'RequiredUnless', 'RequiredWith',
        'RequiredWithAll', 'RequiredWithout', 'RequiredWithoutAll', 'Prohibited', 'ProhibitedIf', 'ProhibitedUnless',
    ];

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string|array<string, string>>  $customMessages  mensagens personalizadas (`campo.regra` ou `regra`)
     * @param  array<string, string>  $customAttributes  nomes legíveis dos campos
     */
    final public function __construct(
        protected Translator $translator,
        protected array $data,
        array $rules,
        protected array $customMessages = [],
        protected array $customAttributes = [],
    ) {
        $this->initialRules = $rules;
        $this->messages = new MessageBag();
        $this->explodeRules();
    }

    /**
     * Cria um Validator com o tradutor e a base de dados da aplicação.
     *
     * @param  array<array-key, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string|array<string, string>>  $messages
     * @param  array<string, string>  $attributes
     */
    public static function make(array $data, array $rules, array $messages = [], array $attributes = []): self
    {
        return Factory::resolve()->make($data, $rules, $messages, $attributes);
    }

    // -------------------------------------------------------------------------
    // Execução
    // -------------------------------------------------------------------------

    public function passes(): bool
    {
        $this->messages = new MessageBag();
        $this->failedRules = [];
        $this->excluded = [];
        $this->hasRun = true;

        $this->explodeRules();

        foreach ($this->rules as $attribute => $rules) {
            if ($this->shouldBeExcluded($attribute, $rules)) {
                $this->excluded[$attribute] = true;

                continue;
            }

            foreach ($rules as $rule) {
                $this->runRule($attribute, $rule);

                if ($this->shouldStopValidating($attribute)) {
                    break;
                }
            }

            if ($this->stopOnFirstFailure && $this->messages->isNotEmpty()) {
                break;
            }
        }

        foreach ($this->after as $callback) {
            $callback($this);
        }

        return $this->messages->isEmpty();
    }

    public function fails(): bool
    {
        return ! $this->passes();
    }

    /**
     * Valida e devolve os dados validados; lança ValidationException se falhar.
     *
     * @return array<string, mixed>
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this);
        }

        return $this->validated();
    }

    /**
     * Só os campos que têm regras (e que existem nos dados), sem os excluídos por `exclude*`.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        $this->ensureRun();

        if ($this->messages->isNotEmpty()) {
            throw new ValidationException($this);
        }

        $results = [];

        foreach (array_keys($this->rules) as $attribute) {
            if (isset($this->excluded[$attribute]) || ! Arr::has($this->data, $attribute)) {
                continue;
            }

            Arr::set($results, $attribute, Arr::get($this->data, $attribute));
        }

        foreach (array_keys($this->excluded) as $attribute) {
            Arr::forget($results, $attribute);
        }

        return $results;
    }

    public function safe(): ValidatedInput
    {
        return new ValidatedInput($this->validated());
    }

    public function errors(): MessageBag
    {
        $this->ensureRun();

        return $this->messages;
    }

    public function getMessageBag(): MessageBag
    {
        return $this->errors();
    }

    /**
     * Regras que falharam: atributo => [regra => parâmetros].
     *
     * @return array<string, array<string, list<string>>>
     */
    public function failed(): array
    {
        $this->ensureRun();

        return $this->failedRules;
    }

    /**
     * Regista uma falha manualmente (por exemplo num callback `after`).
     *
     * @param  list<string>  $parameters
     */
    public function addFailure(string $attribute, string $rule, array $parameters = []): void
    {
        $this->messages->add($attribute, $this->failMessage($attribute, $rule, $parameters));
        $this->failedRules[$attribute][Str::studly($rule)] = $parameters;
    }

    /**
     * Callbacks executados depois das regras (recebem o Validator).
     *
     * @param  callable|list<callable>  $callbacks
     */
    public function after(callable|array $callbacks): static
    {
        $callbacks = is_array($callbacks) && ! is_callable($callbacks) ? $callbacks : [$callbacks];

        foreach ($callbacks as $callback) {
            if (is_callable($callback)) {
                $this->after[] = $callback;
            }
        }

        return $this;
    }

    /**
     * Acrescenta regras apenas quando `$condition(array $data)` devolve true.
     *
     * @param  string|list<string>  $attribute
     */
    public function sometimes(string|array $attribute, mixed $rules, Closure $condition): static
    {
        $this->sometimesRules[] = [$attribute, $rules, $condition];

        return $this;
    }

    public function stopOnFirstFailure(bool $stop = true): static
    {
        $this->stopOnFirstFailure = $stop;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Configuração
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, string|array<string, string>>  $messages
     */
    public function setCustomMessages(array $messages): static
    {
        $this->customMessages = array_replace($this->customMessages, $messages);

        return $this;
    }

    /**
     * @param  array<string, string>  $attributes
     */
    public function setAttributeNames(array $attributes): static
    {
        $this->customAttributes = array_replace($this->customAttributes, $attributes);

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;
        $this->hasRun = false;
        $this->explodeRules();

        return $this;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function setRules(array $rules): static
    {
        $this->initialRules = $rules;
        $this->hasRun = false;
        $this->explodeRules();

        return $this;
    }

    public function addExtension(string $rule, Closure $extension, ?string $message = null): static
    {
        $this->extensions[Str::snake($rule)] = $extension;

        if ($message !== null) {
            $this->extensionMessages[Str::snake($rule)] = $message;
        }

        return $this;
    }

    /**
     * @param  Closure(): PresenceVerifier  $resolver
     */
    public function setPresenceVerifierResolver(Closure $resolver): static
    {
        $this->presenceVerifierResolver = $resolver;

        return $this;
    }

    public function setPresenceVerifier(PresenceVerifier $verifier): static
    {
        $this->presenceVerifier = $verifier;

        return $this;
    }

    public function getPresenceVerifier(): PresenceVerifier
    {
        if ($this->presenceVerifier !== null) {
            return $this->presenceVerifier;
        }

        if ($this->presenceVerifierResolver === null) {
            throw new RuntimeException('exists/unique precisam de uma base de dados: regista um PresenceVerifier.');
        }

        return $this->presenceVerifier = ($this->presenceVerifierResolver)();
    }

    public function getTranslator(): Translator
    {
        return $this->translator;
    }

    /** @return array<array-key, mixed> */
    public function getData(): array
    {
        return $this->data;
    }

    /** @return array<string, list<mixed>> */
    public function getRules(): array
    {
        return $this->rules;
    }

    // -------------------------------------------------------------------------
    // Internos
    // -------------------------------------------------------------------------

    protected function ensureRun(): void
    {
        if (! $this->hasRun) {
            $this->passes();
        }
    }

    protected function explodeRules(): void
    {
        $parser = new ValidationRuleParser($this->data);

        $this->rules = $parser->explode($this->initialRules);

        foreach ($this->sometimesRules as [$attributes, $rules, $condition]) {
            if (! $condition($this->data)) {
                continue;
            }

            $extra = $parser->explode(array_fill_keys((array) $attributes, $rules));

            foreach ($extra as $attribute => $set) {
                $this->rules[$attribute] = [...($this->rules[$attribute] ?? []), ...$set];
            }
        }

        $this->implicitAttributes = $parser->implicitAttributes;
    }

    protected function getValue(string $attribute): mixed
    {
        return Arr::get($this->data, $attribute);
    }

    protected function hasAttribute(string $attribute): bool
    {
        return Arr::has($this->data, $attribute);
    }

    /**
     * Alguma das regras (por nome em StudlyCase) está definida para o atributo.
     *
     * @param  list<string>  $names
     */
    protected function hasRule(string $attribute, array $names): bool
    {
        return $this->getRule($attribute, $names) !== null;
    }

    /**
     * @param  list<string>  $names
     * @return array{0: string, 1: list<string>}|null
     */
    protected function getRule(string $attribute, array $names): ?array
    {
        foreach ($this->rules[$attribute] ?? [] as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            $parsed = ValidationRuleParser::parse($rule);

            if (in_array($parsed[0], $names, true)) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $rules
     */
    protected function shouldBeExcluded(string $attribute, array $rules): bool
    {
        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            [$name, $parameters] = ValidationRuleParser::parse($rule);

            $exclude = match ($name) {
                'Exclude' => true,
                'ExcludeIf' => $this->dependentValuesMatch($parameters),
                'ExcludeUnless' => ! $this->dependentValuesMatch($parameters),
                'ExcludeWith' => $parameters !== [] && ! $this->allFailingRequired($parameters),
                'ExcludeWithout' => $parameters !== [] && $this->anyFailingRequired($parameters),
                default => false,
            };

            if ($exclude) {
                return true;
            }
        }

        return false;
    }

    protected function shouldStopValidating(string $attribute): bool
    {
        if ($this->messages->isEmpty()) {
            return false;
        }

        if ($this->stopOnFirstFailure) {
            return true;
        }

        if ($this->hasRule($attribute, ['Bail'])) {
            return $this->messages->has($attribute);
        }

        // Se uma regra de presença (required, filled...) já falhou, as restantes já não interessam.
        return array_intersect(array_keys($this->failedRules[$attribute] ?? []), $this->implicitRules) !== [];
    }

    protected function runRule(string $attribute, mixed $rule): void
    {
        if ($rule instanceof ValidationRule || $rule instanceof Closure) {
            $this->runObjectRule($attribute, $rule);

            return;
        }

        if (! is_string($rule)) {
            return;
        }

        [$name, $parameters] = ValidationRuleParser::parse($rule);

        if ($name === '') {
            return;
        }

        $value = $this->getValue($attribute);

        if (! $this->isValidatable($attribute, $value, $name)) {
            return;
        }

        $method = 'validate'.$name;
        $custom = $this->extensions[Str::snake($name)] ?? null;

        if ($custom !== null) {
            $valid = (bool) $custom($attribute, $value, $parameters, $this);
        } elseif ($this->isRuleMethod($method)) {
            $valid = $this->{$method}($attribute, $value, $parameters);
        } else {
            throw new BadMethodCallException('A regra de validação ['.Str::snake($name).'] não existe ou não é suportada.');
        }

        if (! $valid) {
            $this->addFailure($attribute, $name, $parameters);
        }
    }

    protected function isRuleMethod(string $method): bool
    {
        return ! in_array($method, ['validate', 'validated'], true) && method_exists($this, $method);
    }

    protected function runObjectRule(string $attribute, ValidationRule|Closure $rule): void
    {
        $value = $this->getValue($attribute);
        $implicit = $rule instanceof ValidationRule && property_exists($rule, 'implicit') && $rule->implicit === true;

        if (! $implicit) {
            if ((is_string($value) && trim($value) === '') || ! $this->hasAttribute($attribute)) {
                return;
            }

            if ($value === null && $this->hasRule($attribute, ['Nullable'])) {
                return;
            }
        }

        if ($this->hasRule($attribute, ['Sometimes']) && ! $this->hasAttribute($attribute)) {
            return;
        }

        if ($rule instanceof DataAwareRule) {
            $rule->setData($this->data);
        }

        if ($rule instanceof ValidatorAwareRule) {
            $rule->setValidator($this);
        }

        $key = $rule instanceof Closure ? 'Closure' : $rule::class;

        $fail = function (string $message) use ($attribute, $key, $value): void {
            $this->messages->add($attribute, $this->replaceInMessage($message, $attribute, $value));
            $this->failedRules[$attribute][$key] = [];
        };

        if ($rule instanceof Closure) {
            $rule($attribute, $value, $fail);

            return;
        }

        $rule->validate($attribute, $value, $fail);
    }

    protected function isValidatable(string $attribute, mixed $value, string $name): bool
    {
        $implicit = in_array($name, $this->implicitRules, true);

        if (is_string($value) && trim($value) === '') {
            $presentOrImplicit = $implicit;
        } else {
            $presentOrImplicit = $this->hasAttribute($attribute) || $implicit;
        }

        if (! $presentOrImplicit) {
            return false;
        }

        // `sometimes`: só valida se o campo vier nos dados.
        if ($this->hasRule($attribute, ['Sometimes']) && ! $this->hasAttribute($attribute)) {
            return false;
        }

        // `nullable`: null não precisa de passar nas restantes regras.
        if (! $implicit && $value === null && $this->hasRule($attribute, ['Nullable'])) {
            return false;
        }

        // Evita duas mensagens de presença seguidas (required + filled...).
        if (in_array($name, ['Required', 'Filled', 'Present'], true)) {
            foreach (['Required', 'Filled', 'Present'] as $presence) {
                if (isset($this->failedRules[$attribute][$presence])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Valida um valor isolado com um conjunto de regras e devolve as mensagens de erro (usado por `Rule::file()`).
     *
     * @param  list<string>  $rules
     * @return list<string>
     */
    public function nestedMessages(string $attribute, mixed $value, array $rules): array
    {
        $nested = new static($this->translator, [$attribute => $value], [$attribute => $rules], $this->customMessages, $this->customAttributes);

        return $nested->passes() ? [] : $nested->errors()->all();
    }
}
