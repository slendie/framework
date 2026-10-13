<?php

declare(strict_types=1);

namespace Slendie\Testing\Fakes;

use InvalidArgumentException;
use Slendie\Http\UploadedFile;

/**
 * Ficheiros de upload falsos para `$this->post('/x', ['avatar' => FakeUploadedFile::image('a.png')])`.
 *
 * Escreve um ficheiro real numa pasta temporária (apagado no fim do processo), por isso `isValid()` é verdadeiro
 * e `store()` funciona.
 */
final class FakeUploadedFile extends UploadedFile
{
    /** PNG 1x1 válido, usado quando a extensão GD não existe. */
    private const string PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** @var list<string> */
    private static array $created = [];

    private static bool $registered = false;

    /**
     * Ficheiro com `$kilobytes` KB de conteúdo (0 = vazio). O tipo MIME deduz-se da extensão.
     */
    public static function create(string $name, int $kilobytes = 0, ?string $mimeType = null): self
    {
        $path = self::tempPath($name);

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new InvalidArgumentException("Não foi possível criar o ficheiro temporário [{$path}].");
        }

        // Os primeiros bytes são a assinatura do formato (para o MIME detectado pelo conteúdo coincidir); o resto é enchimento.
        $remaining = $kilobytes * 1024;
        $signature = substr(self::signature($name), 0, $remaining);
        fwrite($handle, $signature);
        $remaining -= strlen($signature);

        // Escrito por blocos: não ocupa kilobytes de memória de uma só vez.
        while ($remaining > 0) {
            $chunk = min($remaining, 1024);
            fwrite($handle, str_repeat('0', $chunk));
            $remaining -= $chunk;
        }

        fclose($handle);

        return new self($path, $name, $mimeType ?? self::guessMime($name), $kilobytes * 1024, UPLOAD_ERR_OK, true);
    }

    /**
     * Imagem PNG/JPEG/GIF real (GD) com as dimensões pedidas; sem GD gera um PNG 1x1.
     */
    public static function image(string $name, int $width = 10, int $height = 10): self
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('A imagem precisa de largura e altura positivas.');
        }

        $path = self::tempPath($name);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $written = false;

        if (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor($width, $height);

            if ($image !== false) {
                $written = match ($extension) {
                    'jpg', 'jpeg' => imagejpeg($image, $path),
                    'gif' => imagegif($image, $path),
                    default => imagepng($image, $path),
                };
            }
        }

        if (! $written) {
            file_put_contents($path, base64_decode(self::PNG_1X1, true));
        }

        return new self($path, $name, self::guessMime($name), (int) filesize($path), UPLOAD_ERR_OK, true);
    }

    /** Conteúdo exacto (CSV, JSON, texto...). */
    public static function createWithContent(string $name, string $content, ?string $mimeType = null): self
    {
        $path = self::tempPath($name);
        file_put_contents($path, $content);

        return new self($path, $name, $mimeType ?? self::guessMime($name), strlen($content), UPLOAD_ERR_OK, true);
    }

    /** O tipo declarado (um ficheiro "falso" de zeros não tem conteúdo que o denuncie). */
    public function mimeType(): ?string
    {
        return $this->clientMimeType;
    }

    /**
     * O formato de `$_FILES` que o Request sabe converter de volta em UploadedFile.
     *
     * @return array<string, mixed>
     */
    public static function toFilesArray(UploadedFile $file): array
    {
        return [
            'name' => $file->clientName(),
            'type' => $file->mimeType() ?? '',
            'tmp_name' => $file->path(),
            'error' => $file->error(),
            'size' => $file->size(),
            'test' => true,
        ];
    }

    private static function tempPath(string $name): string
    {
        if (! self::$registered) {
            self::$registered = true;
            register_shutdown_function(static function (): void {
                foreach (self::$created as $path) {
                    @unlink($path);
                }
            });
        }

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie-uploads';

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new InvalidArgumentException("Não foi possível criar a pasta [{$directory}].");
        }

        $path = $directory.DIRECTORY_SEPARATOR.bin2hex(random_bytes(8)).'-'.basename($name);

        return self::$created[] = $path;
    }

    private static function signature(string $name): string
    {
        return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'pdf' => "%PDF-1.4\n",
            'png' => "\x89PNG\r\n\x1a\n",
            'jpg', 'jpeg' => "\xFF\xD8\xFF\xE0",
            'gif' => 'GIF89a',
            'zip' => "PK\x03\x04",
            default => '',
        };
    }

    private static function guessMime(string $name): string
    {
        return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'zip' => 'application/zip',
            default => 'application/octet-stream',
        };
    }
}
