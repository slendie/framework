<?php

declare(strict_types=1);

namespace Slendie\Routing;

/**
 * Resultado da compilação de uma Route: regex + nomes dos parâmetros.
 */
final readonly class CompiledRoute
{
    /**
     * @param  list<string>  $parameterNames
     * @param  list<string>  $optionalNames
     */
    public function __construct(
        public string $regex,
        public array $parameterNames,
        public array $optionalNames,
    ) {
    }

    /**
     * @return array<string, string>|null  parâmetros capturados, ou null se não houver match
     */
    public function match(string $path): ?array
    {
        if (preg_match($this->regex, $path, $matches) !== 1) {
            return null;
        }

        $parameters = [];

        foreach ($this->parameterNames as $name) {
            if (isset($matches[$name]) && $matches[$name] !== '') {
                $parameters[$name] = rawurldecode($matches[$name]);
            }
        }

        return $parameters;
    }
}
