<?php

declare(strict_types=1);

namespace Slendie\Support\Facades;

use Slendie\Support\Facade;

/**
 * @method static \Slendie\Http\Response make(string $content = '', int $status = 200, array<mixed> $headers = [])
 * @method static \Slendie\Http\Response noContent(int $status = 204, array<mixed> $headers = [])
 * @method static \Slendie\Http\JsonResponse json(mixed $data = [], int $status = 200, array<mixed> $headers = [], int $flags = 0)
 * @method static \Slendie\Http\ViewResponse view(string $view, array<mixed> $data = [], int $status = 200, array<mixed> $headers = [])
 * @method static \Slendie\Routing\Redirector redirect()
 * @method static \Slendie\Http\Response download(string $file, ?string $name = null, array<mixed> $headers = [], string $disposition = 'attachment')
 *
 * @see \Slendie\Http\ResponseFactory
 */
class Response extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'response';
    }
}
