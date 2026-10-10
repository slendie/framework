<?php

declare(strict_types=1);

namespace Slendie\Support;

use Random\RandomException;
use RuntimeException;

final class Str
{
    /** @var array<string, string> */
    private static array $snakeCache = [];

    /** @var array<string, string> */
    private static array $camelCache = [];

    /** @var array<string, string> */
    private static array $studlyCache = [];

    /** @var array<string, string> singular => plural */
    private const IRREGULAR = [
        'person' => 'people',
        'man' => 'men',
        'woman' => 'women',
        'child' => 'children',
        'tooth' => 'teeth',
        'foot' => 'feet',
        'mouse' => 'mice',
        'goose' => 'geese',
        'ox' => 'oxen',
        'leaf' => 'leaves',
        'life' => 'lives',
        'knife' => 'knives',
        'wife' => 'wives',
        'half' => 'halves',
        'self' => 'selves',
        'elf' => 'elves',
        'loaf' => 'loaves',
        'potato' => 'potatoes',
        'tomato' => 'tomatoes',
        'hero' => 'heroes',
        'echo' => 'echoes',
        'cactus' => 'cacti',
        'focus' => 'foci',
        'fungus' => 'fungi',
        'nucleus' => 'nuclei',
        'syllabus' => 'syllabi',
        'analysis' => 'analyses',
        'diagnosis' => 'diagnoses',
        'thesis' => 'theses',
        'crisis' => 'crises',
        'phenomenon' => 'phenomena',
        'criterion' => 'criteria',
        'datum' => 'data',
        'medium' => 'media',
        'index' => 'indices',
        'matrix' => 'matrices',
        'vertex' => 'vertices',
        'quiz' => 'quizzes',
        'status' => 'statuses',
        'alias' => 'aliases',
        'address' => 'addresses',
    ];

    /** @var list<string> */
    private const UNCOUNTABLE = [
        'audio', 'bison', 'cattle', 'chassis', 'compensation', 'coreopsis', 'data', 'deer', 'education',
        'emoji', 'equipment', 'evidence', 'feedback', 'firmware', 'fish', 'furniture', 'gold', 'hardware',
        'information', 'jedi', 'kin', 'knowledge', 'love', 'metadata', 'money', 'moose', 'news', 'nutrition',
        'offspring', 'plankton', 'pokemon', 'police', 'rain', 'recommended', 'related', 'rice', 'series',
        'sheep', 'software', 'species', 'swine', 'traffic', 'wheat',
    ];

    // -------------------------------------------------------------------------
    // Casing
    // -------------------------------------------------------------------------

    public static function snake(string $value, string $delimiter = '_'): string
    {
        $key = $value.$delimiter;

        if (isset(self::$snakeCache[$key])) {
            return self::$snakeCache[$key];
        }

        if (! ctype_lower($value)) {
            $value = (string) preg_replace('/\s+/u', '', ucwords($value));
            $value = (string) preg_replace('/(.)(?=[A-Z])/u', '$1'.$delimiter, $value);
            $value = self::lower($value);
        }

        return self::$snakeCache[$key] = $value;
    }

    public static function kebab(string $value): string
    {
        return self::snake($value, '-');
    }

    public static function camel(string $value): string
    {
        return self::$camelCache[$value] ??= lcfirst(self::studly($value));
    }

    public static function studly(string $value): string
    {
        if (isset(self::$studlyCache[$value])) {
            return self::$studlyCache[$value];
        }

        $words = explode(' ', str_replace(['-', '_'], ' ', $value));
        $studly = array_map(static fn (string $w): string => ucfirst($w), $words);

        return self::$studlyCache[$value] = implode('', $studly);
    }

    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    public static function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    public static function ucfirst(string $value): string
    {
        return self::upper(self::substr($value, 0, 1)).self::substr($value, 1);
    }

    public static function lcfirst(string $value): string
    {
        return self::lower(self::substr($value, 0, 1)).self::substr($value, 1);
    }

    // -------------------------------------------------------------------------
    // Pluralização (inglês; suficiente para nomes de tabelas)
    // -------------------------------------------------------------------------

    public static function plural(string $value, int|float $count = 2): string
    {
        if ((int) $count === 1 && (float) $count === 1.0) {
            return $value;
        }

        $lower = self::lower($value);

        if (in_array($lower, self::UNCOUNTABLE, true)) {
            return $value;
        }

        if (isset(self::IRREGULAR[$lower])) {
            return self::matchCase(self::IRREGULAR[$lower], $value);
        }

        $rules = [
            '/(quiz)$/i' => '$1zes',
            '/^(ox)$/i' => '$1en',
            '/([m|l])ouse$/i' => '$1ice',
            '/(matr|vert|ind)(?:ix|ex)$/i' => '$1ices',
            '/(x|ch|ss|sh)$/i' => '$1es',
            '/([^aeiouy]|qu)y$/i' => '$1ies',
            '/(hive)$/i' => '$1s',
            '/(?:([^f])fe|([lr])f)$/i' => '$1$2ves',
            '/sis$/i' => 'ses',
            '/([ti])um$/i' => '$1a',
            '/(buffal|tomat|potat|her|ech)o$/i' => '$1oes',
            '/(bu)s$/i' => '$1ses',
            '/(alias|status)$/i' => '$1es',
            '/(octop|vir)us$/i' => '$1i',
            '/(ax|test)is$/i' => '$1es',
            '/s$/i' => 's',
            '/$/' => 's',
        ];

        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $value) === 1) {
                return (string) preg_replace($pattern, $replacement, $value);
            }
        }

        return $value;
    }

    public static function singular(string $value): string
    {
        $lower = self::lower($value);

        if (in_array($lower, self::UNCOUNTABLE, true)) {
            return $value;
        }

        $irregular = array_flip(self::IRREGULAR);

        if (isset($irregular[$lower])) {
            return self::matchCase($irregular[$lower], $value);
        }

        $rules = [
            '/(quiz)zes$/i' => '$1',
            '/(matr)ices$/i' => '$1ix',
            '/(vert|ind)ices$/i' => '$1ex',
            '/^(ox)en$/i' => '$1',
            '/(alias|status)es$/i' => '$1',
            '/(octop|vir)i$/i' => '$1us',
            '/(cris|ax|test)es$/i' => '$1is',
            '/(shoe)s$/i' => '$1',
            '/(o)es$/i' => '$1',
            '/(bus)es$/i' => '$1',
            '/([m|l])ice$/i' => '$1ouse',
            '/(x|ch|ss|sh)es$/i' => '$1',
            '/(m)ovies$/i' => '$1ovie',
            '/(s)eries$/i' => '$1eries',
            '/([^aeiouy]|qu)ies$/i' => '$1y',
            '/([lr])ves$/i' => '$1f',
            '/(tive)s$/i' => '$1',
            '/(hive)s$/i' => '$1',
            '/([^f])ves$/i' => '$1fe',
            '/(^analy)ses$/i' => '$1sis',
            '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '$1$2sis',
            '/([ti])a$/i' => '$1um',
            '/(n)ews$/i' => '$1ews',
            '/(ss)$/i' => '$1',
            '/s$/i' => '',
        ];

        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $value) === 1) {
                return (string) preg_replace($pattern, $replacement, $value);
            }
        }

        return $value;
    }

    private static function matchCase(string $value, string $comparison): string
    {
        if (ctype_upper($comparison)) {
            return self::upper($value);
        }

        if (ctype_upper($comparison[0] ?? '')) {
            return self::ucfirst($value);
        }

        return $value;
    }

    // -------------------------------------------------------------------------
    // Pesquisa e corte
    // -------------------------------------------------------------------------

    /** @param  string|iterable<string>  $needles */
    public static function startsWith(string $haystack, string|iterable $needles): bool
    {
        foreach (is_string($needles) ? [$needles] : $needles as $needle) {
            if ($needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @param  string|iterable<string>  $needles */
    public static function endsWith(string $haystack, string|iterable $needles): bool
    {
        foreach (is_string($needles) ? [$needles] : $needles as $needle) {
            if ($needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @param  string|iterable<string>  $needles */
    public static function contains(string $haystack, string|iterable $needles, bool $ignoreCase = false): bool
    {
        if ($ignoreCase) {
            $haystack = self::lower($haystack);
        }

        foreach (is_string($needles) ? [$needles] : $needles as $needle) {
            if ($ignoreCase) {
                $needle = self::lower($needle);
            }

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strpos($subject, $search);

        return $pos === false ? $subject : substr($subject, $pos + strlen($search));
    }

    public static function afterLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strrpos($subject, $search);

        return $pos === false ? $subject : substr($subject, $pos + strlen($search));
    }

    public static function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strpos($subject, $search);

        return $pos === false ? $subject : substr($subject, 0, $pos);
    }

    public static function beforeLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strrpos($subject, $search);

        return $pos === false ? $subject : substr($subject, 0, $pos);
    }

    public static function between(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') {
            return $subject;
        }

        return self::beforeLast(self::after($subject, $from), $to);
    }

    public static function substr(string $string, int $start, ?int $length = null): string
    {
        return mb_substr($string, $start, $length, 'UTF-8');
    }

    public static function length(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }

    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (self::length($value) <= $limit) {
            return $value;
        }

        return rtrim(self::substr($value, 0, $limit)).$end;
    }

    public static function words(string $value, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S++\s*+){1,'.$words.'}/u', $value, $matches);

        if (! isset($matches[0]) || self::length($value) === self::length($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]).$end;
    }

    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strpos($subject, $search);

        return $pos === false ? $subject : substr_replace($subject, $replace, $pos, strlen($search));
    }

    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = strrpos($subject, $search);

        return $pos === false ? $subject : substr_replace($subject, $replace, $pos, strlen($search));
    }

    public static function start(string $value, string $prefix): string
    {
        $quoted = preg_quote($prefix, '/');

        return $prefix.preg_replace('/^(?:'.$quoted.')+/u', '', $value);
    }

    public static function finish(string $value, string $cap): string
    {
        $quoted = preg_quote($cap, '/');

        return preg_replace('/(?:'.$quoted.')+$/u', '', $value).$cap;
    }

    public static function padLeft(string $value, int $length, string $pad = ' '): string
    {
        return mb_str_pad($value, $length, $pad, STR_PAD_LEFT, 'UTF-8');
    }

    public static function padRight(string $value, int $length, string $pad = ' '): string
    {
        return mb_str_pad($value, $length, $pad, STR_PAD_RIGHT, 'UTF-8');
    }

    // -------------------------------------------------------------------------
    // Transformações
    // -------------------------------------------------------------------------

    public static function slug(string $title, string $separator = '-'): string
    {
        $title = self::ascii($title);
        $flip = $separator === '-' ? '_' : '-';

        $title = (string) preg_replace('!['.preg_quote($flip).']+!u', $separator, $title);
        $title = str_replace('@', $separator.'at'.$separator, $title);
        $title = (string) preg_replace('![^'.preg_quote($separator).'\pL\pN\s]+!u', '', self::lower($title));
        $title = (string) preg_replace('!['.preg_quote($separator).'\s]+!u', $separator, $title);

        return trim($title, $separator);
    }

    /**
     * Transliteração simples para ASCII (suficiente para slugs em pt/es/fr/de).
     */
    public static function ascii(string $value): string
    {
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
            'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'œ' => 'oe',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE',
            'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Œ' => 'OE',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y',
        ];

        $value = strtr($value, $map);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

            if ($converted !== false) {
                $value = $converted;
            }
        }

        return (string) preg_replace('/[^\x20-\x7E]/u', '', $value);
    }

    public static function random(int $length = 16): string
    {
        $string = '';

        while (($len = strlen($string)) < $length) {
            $size = $length - $len;
            $bytesSize = max(3, (int) ceil($size / 3) * 3);

            try {
                $bytes = random_bytes($bytesSize);
            } catch (RandomException $e) {
                throw new RuntimeException('Sem fonte de aleatoriedade disponível.', 0, $e);
            }

            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public static function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $value) === 1;
    }

    /**
     * Comparação com wildcard '*'.
     *
     * @param  string|iterable<string>  $patterns
     */
    public static function is(string|iterable $patterns, string $value): bool
    {
        foreach (is_string($patterns) ? [$patterns] : $patterns as $pattern) {
            if ($pattern === $value) {
                return true;
            }

            $pattern = str_replace('\*', '.*', preg_quote($pattern, '#'));

            if (preg_match('#^'.$pattern.'\z#u', $value) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function squish(string $value): string
    {
        return trim((string) preg_replace('~(\s|\x{3164}|\x{1160})+~u', ' ', $value));
    }

    public static function wrap(string $value, string $before, ?string $after = null): string
    {
        return $before.$value.($after ?? $before);
    }

    public static function classBasename(object|string $class): string
    {
        $class = is_object($class) ? $class::class : $class;

        return basename(str_replace('\\', '/', $class));
    }

    public static function flushCache(): void
    {
        self::$snakeCache = [];
        self::$camelCache = [];
        self::$studlyCache = [];
    }
}
