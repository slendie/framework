<?php

declare(strict_types=1);

namespace Slendie\Http;

use Slendie\Contracts\Renderable;

/**
 * Resposta cujo corpo é uma view, renderizada só quando necessário (lazy).
 */
class ViewResponse extends Response
{
    protected bool $rendered = false;

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(protected Renderable $view, int $status = 200, array $headers = [])
    {
        parent::__construct('', $status, $headers);

        $this->rendered = false;
        $this->header('Content-Type', 'text/html; charset=UTF-8', replace: false);
    }

    public function getView(): Renderable
    {
        return $this->view;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        $this->rendered = true;

        return $this;
    }

    public function getContent(): string
    {
        if (! $this->rendered) {
            $this->content = $this->view->render();
            $this->rendered = true;
        }

        return $this->content;
    }
}
