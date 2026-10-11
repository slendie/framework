<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Http;

use Slendie\Contracts\Renderable;
use Slendie\Contracts\ViewFactory;

class FakeRenderable implements Renderable, ViewFactory
{
    public static int $renders = 0;

    /** @var array<string, mixed> */
    public static array $shared = [];

    public function __construct(private string $name = 'fake', private array $data = [])
    {
    }

    public function render(): string
    {
        self::$renders++;

        return '<p>'.$this->name.':'.json_encode($this->data).'</p>';
    }

    public function make(string $view, array $data = []): Renderable
    {
        return new self($view, $data);
    }

    public function exists(string $view): bool
    {
        return true;
    }

    public function share(string $key, mixed $value = null): mixed
    {
        return self::$shared[$key] = $value;
    }
}
