<?php

declare(strict_types=1);

namespace Slendie\Session;

use SessionHandlerInterface;

/**
 * Handler em memória (testes e processos de vida curta).
 */
class ArraySessionHandler implements SessionHandlerInterface
{
    /** @var array<string, array{data: string, time: int}> */
    public array $storage = [];

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        return $this->storage[$id]['data'] ?? '';
    }

    public function write(string $id, string $data): bool
    {
        $this->storage[$id] = ['data' => $data, 'time' => time()];

        return true;
    }

    public function destroy(string $id): bool
    {
        unset($this->storage[$id]);

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $removed = 0;

        foreach ($this->storage as $id => $entry) {
            if ($entry['time'] < time() - $max_lifetime) {
                unset($this->storage[$id]);
                $removed++;
            }
        }

        return $removed;
    }
}
