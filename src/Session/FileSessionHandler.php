<?php

declare(strict_types=1);

namespace Slendie\Session;

use SessionHandlerInterface;

/**
 * Guarda cada sessão num ficheiro (storage/framework/sessions/{id}).
 */
class FileSessionHandler implements SessionHandlerInterface
{
    public function __construct(protected string $path, protected int $lifetimeMinutes = 120)
    {
    }

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
        $file = $this->file($id);

        if (! is_file($file)) {
            return '';
        }

        if (filemtime($file) < time() - $this->lifetimeMinutes * 60) {
            @unlink($file);

            return '';
        }

        $data = @file_get_contents($file);

        return $data === false ? '' : $data;
    }

    public function write(string $id, string $data): bool
    {
        if (! is_dir($this->path) && ! @mkdir($this->path, 0775, true) && ! is_dir($this->path)) {
            return false;
        }

        return file_put_contents($this->file($id), $data, LOCK_EX) !== false;
    }

    public function destroy(string $id): bool
    {
        $file = $this->file($id);

        if (is_file($file)) {
            @unlink($file);
        }

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $removed = 0;

        foreach (glob($this->path.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file) && basename($file) !== '.gitkeep' && filemtime($file) < time() - $max_lifetime) {
                if (@unlink($file)) {
                    $removed++;
                }
            }
        }

        return $removed;
    }

    protected function file(string $id): string
    {
        // O id é sempre [A-Za-z0-9]{40} (validado pela Session); basename é defesa em profundidade.
        return $this->path.DIRECTORY_SEPARATOR.basename($id);
    }
}
