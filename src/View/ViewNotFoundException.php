<?php

declare(strict_types=1);

namespace Slendie\View;

use Slendie\Exceptions\SlendieException;

/**
 * A view pedida não existe em nenhuma das pastas registadas.
 */
class ViewNotFoundException extends SlendieException
{
    /**
     * @param  list<string>  $searched  ficheiros que foram tentados, por ordem
     */
    public function __construct(string $message, protected array $searched = [])
    {
        if ($searched !== []) {
            $message .= " Procurado em:\n - ".implode("\n - ", $searched);
        }

        parent::__construct($message);
    }

    /** @return list<string> */
    public function getSearched(): array
    {
        return $this->searched;
    }
}
