<?php

declare(strict_types=1);

namespace Slendie\Http;

use Closure;
use Slendie\Container\Container;
use Slendie\Exceptions\HttpException;
use Slendie\Support\Arr;
use Slendie\Validation\ValidatedInput;
use Slendie\Validation\ValidationException;
use Slendie\Validation\Validator;

/**
 * Pedido com regras de validação e autorização próprias. Injectado num controller, é validado antes de a acção correr.
 *
 *     public function store(StoreLeadRequest $request) { $data = $request->validated(); }
 */
abstract class FormRequest extends Request
{
    protected ?Container $container = null;

    protected ?Validator $validatorInstance = null;

    protected bool $stopOnFirstFailure = false;

    /** URL para onde ir quando a validação falha (por omissão, voltar atrás). */
    protected ?string $redirect = null;

    /** Nome do bag de erros. */
    protected string $errorBag = 'default';

    /**
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Callbacks `fn (Validator $validator)` executados depois das regras.
     *
     * @return list<Closure>
     */
    public function after(): array
    {
        return [];
    }

    /** Ajusta o input antes de validar (`$this->merge([...])`). */
    protected function prepareForValidation(): void
    {
    }

    /** Corre depois de a validação passar. */
    protected function passedValidation(): void
    {
    }

    // -------------------------------------------------------------------------
    // Ciclo de vida
    // -------------------------------------------------------------------------

    public function setContainer(Container $container): static
    {
        $this->container = $container;

        return $this;
    }

    /** Copia o pedido actual para este FormRequest (input, ficheiros, headers, rota, sessão...). */
    public function initializeFrom(Request $from): static
    {
        $this->query = $from->query;
        $this->request = $from->request;
        $this->cookies = $from->cookies;
        $this->files = $from->files;
        $this->server = $from->server;
        $this->content = $from->content;
        $this->headerBag = $from->headerBag;
        $this->jsonData = $from->jsonData;
        $this->fileBag = $from->fileBag;
        $this->route = $from->route;
        $this->session = $from->session;
        $this->userResolver = $from->userResolver;
        $this->attributes = $from->attributes;

        return $this;
    }

    /**
     * Prepara, autoriza e valida. Lança HttpException(403) ou ValidationException.
     */
    public function validateResolved(): void
    {
        $this->prepareForValidation();

        if (! $this->container()->call([$this, 'authorize'])) {
            throw new HttpException(403, 'This action is unauthorized.');
        }

        $validator = $this->getValidator();

        if ($validator->fails()) {
            $exception = (new ValidationException($validator))->errorBag($this->errorBag);

            if ($this->redirect !== null) {
                $exception->redirectTo($this->redirect);
            }

            throw $exception;
        }

        $this->passedValidation();
    }

    public function getValidator(): Validator
    {
        if ($this->validatorInstance !== null) {
            return $this->validatorInstance;
        }

        /** @var array<string, mixed> $rules */
        $rules = $this->container()->call([$this, 'rules']);

        $validator = $this->validator($rules, $this->messages(), $this->attributes())
            ->stopOnFirstFailure($this->stopOnFirstFailure);

        foreach ($this->after() as $callback) {
            $validator->after($callback);
        }

        return $this->validatorInstance = $validator;
    }

    // -------------------------------------------------------------------------
    // Resultado
    // -------------------------------------------------------------------------

    /**
     * Os campos validados (todos, ou a chave pedida).
     *
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function validated(?string $key = null, mixed $default = null): mixed
    {
        $data = $this->getValidator()->validated();

        return $key === null ? $data : Arr::get($data, $key, $default);
    }

    public function safe(): ValidatedInput
    {
        return $this->getValidator()->safe();
    }

    protected function container(): Container
    {
        return $this->container ?? Container::getInstance();
    }
}
