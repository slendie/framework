<?php

declare(strict_types=1);

namespace Slendie\Http;

use Slendie\Contracts\Arrayable;

class JsonResponse extends Response
{
    protected mixed $data;

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        mixed $data = null,
        int $status = 200,
        array $headers = [],
        protected int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ) {
        parent::__construct('', $status, $headers);

        $this->header('Content-Type', 'application/json', replace: false);
        $this->setData($data);
    }

    public function setData(mixed $data): static
    {
        $this->data = $data;

        $payload = $data instanceof Arrayable ? $data->toArray() : $data;

        $this->content = json_encode($payload ?? new \stdClass(), $this->flags | JSON_THROW_ON_ERROR);

        return $this;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function setEncodingOptions(int $flags): static
    {
        $this->flags = $flags;

        return $this->setData($this->data);
    }
}
