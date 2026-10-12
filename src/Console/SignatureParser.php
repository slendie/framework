<?php

declare(strict_types=1);

namespace Slendie\Console;

use InvalidArgumentException;

/**
 * Lê assinaturas ao estilo Laravel:
 *
 *     migrate:rollback {name} {name?} {name=padrão} {name*} {--flag} {--opt=} {--opt=padrão} {--O|opt} {--opt=*} {arg : Descrição}
 *
 * O atalho pode vir antes ou depois do nome (`{--Q|queue}` ou `{--queue|-Q}`).
 */
final class SignatureParser
{
    /** Opções globais da consola: não podem ser redefinidas por comandos. */
    private const array RESERVED_OPTIONS = ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction'];

    private const array RESERVED_SHORTCUTS = ['h', 'q', 'v', 'V', 'n'];

    /**
     * @return array{0: string, 1: InputDefinition} nome do comando e definição
     *
     * @throws InvalidArgumentException sintaxe inválida
     */
    public static function parse(string $signature): array
    {
        if (preg_match('/^\s*([^\s{]+)/', $signature, $m) !== 1) {
            throw new InvalidArgumentException('A assinatura do comando tem de começar pelo nome do comando.');
        }

        $name = $m[1];

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9:_.-]*$/', $name) !== 1) {
            throw new InvalidArgumentException("Nome de comando inválido [{$name}].");
        }

        $rest = substr($signature, strlen($m[0]));

        if (trim((string) preg_replace('/\{.*?\}/s', '', $rest)) !== '') {
            throw new InvalidArgumentException("Sintaxe inválida na assinatura [{$signature}]: o texto fora de {chavetas} não é permitido.");
        }

        preg_match_all('/\{\s*(.*?)\s*\}/s', $rest, $tokens);

        $definition = new InputDefinition();

        foreach ($tokens[1] as $token) {
            [$spec, $description] = array_pad(preg_split('/\s+:\s+/', $token, 2) ?: [$token], 2, '');
            $spec = trim($spec);

            if ($spec === '') {
                throw new InvalidArgumentException("Argumento ou opção vazio na assinatura [{$signature}].");
            }

            if (str_starts_with($spec, '--')) {
                $definition->addOption(self::option(substr($spec, 2), trim($description)));
            } else {
                $definition->addArgument(self::argument($spec, trim($description)));
            }
        }

        return [$name, $definition];
    }

    private static function argument(string $spec, string $description): InputArgument
    {
        if (str_ends_with($spec, '?*')) {
            $name = substr($spec, 0, -2);
            $argument = new InputArgument($name, false, true, [], $description);
        } elseif (str_ends_with($spec, '*')) {
            $name = substr($spec, 0, -1);
            $argument = new InputArgument($name, true, true, [], $description);
        } elseif (str_ends_with($spec, '?')) {
            $name = substr($spec, 0, -1);
            $argument = new InputArgument($name, false, false, null, $description);
        } elseif (($eq = strpos($spec, '=')) !== false) {
            $name = substr($spec, 0, $eq);
            $default = substr($spec, $eq + 1);
            $argument = new InputArgument($name, false, false, $default === '' ? null : $default, $description);
        } else {
            $name = $spec;
            $argument = new InputArgument($name, true, false, null, $description);
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name) !== 1) {
            throw new InvalidArgumentException("Nome de argumento inválido [{$name}].");
        }

        return $argument;
    }

    private static function option(string $spec, string $description): InputOption
    {
        $eq = strpos($spec, '=');
        $names = $eq === false ? $spec : substr($spec, 0, $eq);
        $value = $eq === false ? null : substr($spec, $eq + 1);

        $parts = array_map(static fn (string $part): string => ltrim(trim($part), '-'), explode('|', $names));

        if (count($parts) > 2) {
            throw new InvalidArgumentException("Opção inválida [--{$spec}]: só é permitido um atalho.");
        }

        $name = $parts[0];
        $shortcut = null;

        if (count($parts) === 2) {
            [$a, $b] = $parts;

            if (strlen($a) === 1 && strlen($b) > 1) {
                [$shortcut, $name] = [$a, $b];
            } elseif (strlen($b) === 1 && strlen($a) > 1) {
                [$name, $shortcut] = [$a, $b];
            } else {
                throw new InvalidArgumentException("Opção inválida [--{$spec}]: o atalho tem de ter uma só letra e o nome mais de uma.");
            }
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name) !== 1) {
            throw new InvalidArgumentException("Nome de opção inválido [--{$name}].");
        }

        if (in_array($name, self::RESERVED_OPTIONS, true) || ($shortcut !== null && in_array($shortcut, self::RESERVED_SHORTCUTS, true))) {
            throw new InvalidArgumentException("A opção [--{$name}] está reservada pela consola.");
        }

        if ($shortcut !== null && preg_match('/^[A-Za-z]$/', $shortcut) !== 1) {
            throw new InvalidArgumentException("Atalho inválido [-{$shortcut}].");
        }

        if ($value === null) {
            return new InputOption($name, $shortcut, false, false, false, $description);
        }

        if ($value === '*') {
            return new InputOption($name, $shortcut, true, true, [], $description);
        }

        return new InputOption($name, $shortcut, true, false, $value === '' ? null : $value, $description);
    }
}
