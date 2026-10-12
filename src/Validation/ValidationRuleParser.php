<?php

declare(strict_types=1);

namespace Slendie\Validation;

use Closure;
use Slendie\Support\Str;
use Slendie\Validation\Rules\ConditionalRules;
use Stringable;

/**
 * Normaliza as regras: separa `a|b:1,2`, resolve `Rule::when()`, converte objectos "stringáveis" (`Rule::in()`)
 * e expande wildcards (`items.*.qty`) contra os dados.
 */
final class ValidationRuleParser
{
    /** @var array<string, string> atributo concreto => padrão com wildcard de onde veio */
    public array $implicitAttributes = [];

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function __construct(private readonly array $data)
    {
    }

    /**
     * @param  array<string, mixed>  $rules  atributo (ou padrão) => regras
     * @return array<string, list<mixed>> atributo concreto => regras normalizadas
     */
    public function explode(array $rules): array
    {
        $exploded = [];

        foreach ($rules as $attribute => $ruleSet) {
            $attribute = (string) $attribute;
            $normalized = $this->normalize($ruleSet);

            if (! str_contains($attribute, '*')) {
                $exploded[$attribute] = [...($exploded[$attribute] ?? []), ...$normalized];

                continue;
            }

            foreach ($this->expand($this->data, explode('.', $attribute), '') as $concrete) {
                $this->implicitAttributes[$concrete] = $attribute;
                $exploded[$concrete] = [...($exploded[$concrete] ?? []), ...$normalized];
            }
        }

        return $exploded;
    }

    /**
     * @return list<mixed>
     */
    public function normalize(mixed $ruleSet): array
    {
        if ($ruleSet instanceof ConditionalRules) {
            return $this->normalize($ruleSet->rules($this->data));
        }

        if (is_string($ruleSet)) {
            return array_values(array_filter(explode('|', $ruleSet), static fn (string $rule): bool => $rule !== ''));
        }

        if (is_object($ruleSet) && ! $ruleSet instanceof Closure && ! $ruleSet instanceof ValidationRule) {
            $ruleSet = $ruleSet instanceof Stringable ? (string) $ruleSet : [$ruleSet];

            return is_string($ruleSet) ? $this->normalize($ruleSet) : $ruleSet;
        }

        if (! is_array($ruleSet)) {
            return [$ruleSet];
        }

        $rules = [];

        foreach ($ruleSet as $rule) {
            if ($rule instanceof ConditionalRules) {
                array_push($rules, ...$this->normalize($rule));

                continue;
            }

            if (is_object($rule) && ! $rule instanceof Closure && ! $rule instanceof ValidationRule && $rule instanceof Stringable) {
                $rule = (string) $rule;
            }

            if ($rule !== '') {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * Separa uma regra em [nome em StudlyCase, parâmetros].
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function parse(string $rule): array
    {
        $parameters = [];

        if (str_contains($rule, ':')) {
            [$rule, $payload] = explode(':', $rule, 2);

            $name = strtolower(trim($rule));

            // regex não separa por vírgulas: o padrão pode contê-las.
            $parameters = in_array($name, ['regex', 'not_regex'], true)
                ? [$payload]
                : array_map(static fn (?string $p): string => (string) $p, str_getcsv($payload, ',', '"', ''));
        }

        return [Str::studly(trim($rule)), $parameters];
    }

    /**
     * Percorre os dados seguindo os segmentos do padrão e devolve os caminhos concretos.
     *
     * @param  list<string>  $segments
     * @return list<string>
     */
    private function expand(mixed $data, array $segments, string $prefix): array
    {
        if ($segments === []) {
            return [$prefix];
        }

        $segment = array_shift($segments);

        if ($segment === '*') {
            if (! is_array($data)) {
                return [];
            }

            $paths = [];

            foreach (array_keys($data) as $key) {
                array_push($paths, ...$this->expand($data[$key], $segments, $prefix === '' ? (string) $key : $prefix.'.'.$key));
            }

            return $paths;
        }

        $next = is_array($data) && array_key_exists($segment, $data) ? $data[$segment] : null;

        return $this->expand($next, $segments, $prefix === '' ? $segment : $prefix.'.'.$segment);
    }
}
