<?php

declare(strict_types=1);

namespace Slendie\Validation;

use Closure;
use Slendie\Container\Container;

/**
 * Cria Validators já ligados ao tradutor, à base de dados e às regras personalizadas (`extend`).
 */
class Factory
{
    /** @var array<string, Closure> */
    protected array $extensions = [];

    /** @var array<string, string> */
    protected array $extensionMessages = [];

    /** @var (Closure(): PresenceVerifier)|null */
    protected ?Closure $verifierResolver = null;

    protected ?PresenceVerifier $verifier = null;

    public function __construct(protected Translator $translator)
    {
    }

    /** O Factory registado no container; fora de uma aplicação, um com os textos da framework. */
    public static function resolve(): self
    {
        $container = Container::getInstance();

        if ($container->bound('validator')) {
            $factory = $container->make('validator');

            if ($factory instanceof self) {
                return $factory;
            }
        }

        return new self(new Translator(dirname(__DIR__, 2).'/lang'));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string|array<string, string>>  $messages
     * @param  array<string, string>  $attributes
     */
    public function make(array $data, array $rules, array $messages = [], array $attributes = []): Validator
    {
        $validator = new Validator($this->translator, $data, $rules, $messages, $attributes);

        $validator->setPresenceVerifierResolver(fn (): PresenceVerifier => $this->presenceVerifier());

        foreach ($this->extensions as $rule => $extension) {
            $validator->addExtension($rule, $extension, $this->extensionMessages[$rule] ?? null);
        }

        return $validator;
    }

    /**
     * Valida e devolve os dados validados (ou lança ValidationException).
     *
     * @param  array<array-key, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string|array<string, string>>  $messages
     * @param  array<string, string>  $attributes
     * @return array<string, mixed>
     */
    public function validate(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        return $this->make($data, $rules, $messages, $attributes)->validate();
    }

    /**
     * Regra personalizada por nome: `fn (string $attribute, mixed $value, array $parameters, Validator $validator): bool`.
     */
    public function extend(string $rule, Closure $extension, ?string $message = null): static
    {
        $this->extensions[$rule] = $extension;

        if ($message !== null) {
            $this->extensionMessages[$rule] = $message;
        }

        return $this;
    }

    public function getTranslator(): Translator
    {
        return $this->translator;
    }

    public function setPresenceVerifier(PresenceVerifier $verifier): static
    {
        $this->verifier = $verifier;

        return $this;
    }

    /**
     * @param  Closure(): PresenceVerifier  $resolver
     */
    public function setPresenceVerifierResolver(Closure $resolver): static
    {
        $this->verifierResolver = $resolver;

        return $this;
    }

    protected function presenceVerifier(): PresenceVerifier
    {
        if ($this->verifier !== null) {
            return $this->verifier;
        }

        if ($this->verifierResolver === null) {
            throw new \RuntimeException('exists/unique precisam de uma base de dados: regista um PresenceVerifier.');
        }

        return $this->verifier = ($this->verifierResolver)();
    }
}
