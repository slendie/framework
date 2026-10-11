<?php

declare(strict_types=1);

namespace Slendie\View;

use Slendie\Contracts\Arrayable;
use Slendie\Contracts\Renderable;
use Stringable;

/**
 * Uma view pronta a renderizar: nome, ficheiro e dados.
 */
class View implements Arrayable, Renderable, Stringable
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        protected Factory $factory,
        protected Engine $engine,
        protected string $name,
        protected string $path,
        protected array $data = [],
    ) {
    }

    /**
     * @param  string|array<string, mixed>  $key
     */
    public function with(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->data = [...$this->data, ...$key];
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return $this->data;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function getFactory(): Factory
    {
        return $this->factory;
    }

    public function render(): string
    {
        $this->factory->incrementRender();

        try {
            $this->factory->callComposers($this);

            return $this->engine->get($this->path, [...$this->factory->getShared(), ...$this->data], $this->factory);
        } catch (ViewException $e) {
            throw $e->getViewName() === null ? $e->withViewName($this->name) : $e;
        } finally {
            $this->factory->decrementRender();
            $this->factory->flushStateIfDoneRendering();
        }
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
