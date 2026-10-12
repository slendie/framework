<?php

declare(strict_types=1);

namespace Slendie\Validation;

use Slendie\Exceptions\SlendieException;

/**
 * Lançada quando a validação falha. O Handler converte-a em redirect (com erros e input) ou em 422 JSON.
 */
class ValidationException extends SlendieException
{
    public int $status = 422;

    public ?string $redirectTo = null;

    public string $errorBag = 'default';

    final public function __construct(public readonly Validator $validator)
    {
        parent::__construct(self::summarize($validator));
    }

    /**
     * @param  array<string, string|list<string>>  $messages  campo => mensagem(ns)
     */
    public static function withMessages(array $messages): static
    {
        $validator = Validator::make([], []);

        foreach ($messages as $key => $value) {
            foreach ((array) $value as $message) {
                $validator->getMessageBag()->add($key, $message);
            }
        }

        return new static($validator);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->validator->errors()->toArray();
    }

    public function status(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function errorBag(string $errorBag): static
    {
        $this->errorBag = $errorBag;

        return $this;
    }

    public function redirectTo(string $url): static
    {
        $this->redirectTo = $url;

        return $this;
    }

    protected static function summarize(Validator $validator): string
    {
        $messages = $validator->errors()->all();

        if ($messages === []) {
            return 'The given data was invalid.';
        }

        $message = $messages[0];
        $more = count($messages) - 1;

        return $more > 0 ? "{$message} (and {$more} more ".($more === 1 ? 'error' : 'errors').')' : $message;
    }
}
