<?php

declare(strict_types=1);

namespace Slendie\View;

use Slendie\Exceptions\SlendieException;
use Throwable;

/**
 * Excepção ocorrida ao executar uma view: indica o nome da view e a linha original do `.blade.php`.
 */
class ViewException extends SlendieException
{
    protected ?string $viewName = null;

    protected string $baseMessage;

    public function __construct(string $message, protected ?string $viewPath = null, protected ?int $viewLine = null, ?Throwable $previous = null)
    {
        $this->baseMessage = $message;

        parent::__construct($this->buildMessage(), 0, $previous);
    }

    public static function wrap(Throwable $e, ?string $viewPath, ?int $viewLine): self
    {
        return new self($e->getMessage(), $viewPath, $viewLine, $e);
    }

    public function withViewName(string $name): static
    {
        $this->viewName = $name;
        $this->message = $this->buildMessage();

        return $this;
    }

    public function getViewName(): ?string
    {
        return $this->viewName;
    }

    public function getViewPath(): ?string
    {
        return $this->viewPath;
    }

    public function getViewLine(): ?int
    {
        return $this->viewLine;
    }

    protected function buildMessage(): string
    {
        $where = $this->viewName !== null ? "{$this->viewName}, " : '';
        $where .= $this->viewPath ?? 'desconhecida';

        if ($this->viewLine !== null) {
            $where .= ':'.$this->viewLine;
        }

        return "{$this->baseMessage} (View: {$where})";
    }
}
