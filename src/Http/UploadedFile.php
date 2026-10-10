<?php

declare(strict_types=1);

namespace Slendie\Http;

use RuntimeException;

/**
 * Ficheiro recebido por upload (entrada de $_FILES).
 */
class UploadedFile
{
    /**
     * @param  bool  $test  true em testes: permite mover ficheiros que não vieram de um upload HTTP
     */
    public function __construct(
        protected string $path,
        protected string $clientName,
        protected ?string $clientMimeType = null,
        protected ?int $size = null,
        protected int $error = UPLOAD_ERR_OK,
        protected bool $test = false,
    ) {
    }

    public function clientName(): string
    {
        return $this->clientName;
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->clientName, PATHINFO_EXTENSION));
    }

    /** Detecta o tipo pelo conteúdo; recorre ao enviado pelo cliente se necessário. */
    public function mimeType(): ?string
    {
        if (is_file($this->path) && function_exists('mime_content_type')) {
            $mime = @mime_content_type($this->path);

            if ($mime !== false) {
                return $mime;
            }
        }

        return $this->clientMimeType;
    }

    public function size(): int
    {
        if ($this->size !== null) {
            return $this->size;
        }

        $size = is_file($this->path) ? filesize($this->path) : false;

        return $size === false ? 0 : $size;
    }

    public function error(): int
    {
        return $this->error;
    }

    public function isValid(): bool
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            return false;
        }

        return $this->test ? is_file($this->path) : is_uploaded_file($this->path);
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Move para storage/app/{dir}; devolve o caminho relativo (dir/nome).
     */
    public function store(string $dir, ?string $name = null): string
    {
        return $this->moveTo(storage_path('app'), $dir, $name);
    }

    /**
     * Move para public/uploads/{dir}; devolve o caminho relativo a public/ (uploads/dir/nome).
     */
    public function storePublicly(string $dir = '', ?string $name = null): string
    {
        return 'uploads/'.$this->moveTo(public_path('uploads'), $dir, $name);
    }

    protected function moveTo(string $root, string $dir, ?string $name): string
    {
        if (! $this->isValid()) {
            throw new RuntimeException('O ficheiro enviado não é válido (código de erro '.$this->error.').');
        }

        $dir = trim(str_replace('\\', '/', $dir), '/');
        $name ??= bin2hex(random_bytes(20)).($this->extension() !== '' ? '.'.$this->extension() : '');
        $name = basename($name);

        $target = rtrim($root, '\/').($dir === '' ? '' : DIRECTORY_SEPARATOR.$dir);

        if (! is_dir($target) && ! mkdir($target, 0775, true) && ! is_dir($target)) {
            throw new RuntimeException("Não foi possível criar a pasta [{$target}].");
        }

        $destination = $target.DIRECTORY_SEPARATOR.$name;

        $moved = $this->test ? rename($this->path, $destination) : move_uploaded_file($this->path, $destination);

        if (! $moved) {
            throw new RuntimeException("Não foi possível mover o ficheiro para [{$destination}].");
        }

        $this->path = $destination;

        return ($dir === '' ? '' : $dir.'/').$name;
    }
}
