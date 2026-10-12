<?php

declare(strict_types=1);

namespace Slendie\Validation\Concerns;

use Closure;
use Countable;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use finfo;
use InvalidArgumentException;
use Slendie\Database\Query\Builder;
use Slendie\Http\UploadedFile;
use Slendie\Support\Str;
use Slendie\Validation\Rules\Enum;
use Stringable;

/**
 * Implementação das regras: um método `validate{NomeDaRegra}(atributo, valor, parâmetros): bool` por regra.
 * Os nomes seguem as regras do Laravel (`required_if` → `validateRequiredIf`).
 */
trait ValidatesAttributes
{
    /** @var array<string, list<string>> mime detectado => extensões equivalentes */
    protected array $mimeExtensions = [
        'image/jpeg' => ['jpg', 'jpeg', 'jpe'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/avif' => ['avif'],
        'image/bmp' => ['bmp'],
        'image/x-ms-bmp' => ['bmp'],
        'image/svg+xml' => ['svg'],
        'image/x-icon' => ['ico'],
        'image/vnd.microsoft.icon' => ['ico'],
        'image/tiff' => ['tif', 'tiff'],
        'application/pdf' => ['pdf'],
        'application/json' => ['json'],
        'application/zip' => ['zip'],
        'application/gzip' => ['gz'],
        'application/xml' => ['xml'],
        'text/xml' => ['xml'],
        'text/html' => ['html', 'htm'],
        'text/csv' => ['csv'],
        'text/plain' => ['txt', 'text', 'md', 'csv', 'log'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls', 'csv'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.ms-powerpoint' => ['ppt'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
        'audio/mpeg' => ['mp3'],
        'audio/wav' => ['wav'],
        'audio/x-wav' => ['wav'],
        'audio/ogg' => ['ogg'],
        'video/mp4' => ['mp4'],
        'video/mpeg' => ['mpeg', 'mpg'],
        'video/webm' => ['webm'],
        'video/quicktime' => ['mov'],
    ];

    // =========================================================================
    // Presença
    // =========================================================================

    /** @param list<string> $parameters */
    protected function validateRequired(string $attribute, mixed $value, array $parameters = []): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if ((is_array($value) || $value instanceof Countable) && count($value) < 1) {
            return false;
        }

        if ($value instanceof UploadedFile) {
            return $value->path() !== '';
        }

        return true;
    }

    /** @param list<string> $parameters */
    protected function validatePresent(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->hasAttribute($attribute);
    }

    /** @param list<string> $parameters */
    protected function validateFilled(string $attribute, mixed $value, array $parameters): bool
    {
        return ! $this->hasAttribute($attribute) || $this->validateRequired($attribute, $value);
    }

    /** @param list<string> $parameters */
    protected function validateNullable(string $attribute, mixed $value, array $parameters): bool
    {
        return true;
    }

    /** @param list<string> $parameters */
    protected function validateSometimes(string $attribute, mixed $value, array $parameters): bool
    {
        return true;
    }

    /** @param list<string> $parameters */
    protected function validateBail(string $attribute, mixed $value, array $parameters): bool
    {
        return true;
    }

    /** @param list<string> $parameters */
    protected function validateRequiredIf(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(2, $parameters, 'required_if');

        return ! $this->dependentValuesMatch($parameters) || $this->validateRequired($attribute, $value);
    }

    /** @param list<string> $parameters */
    protected function validateRequiredUnless(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(2, $parameters, 'required_unless');

        return $this->dependentValuesMatch($parameters) || $this->validateRequired($attribute, $value);
    }

    /** @param list<string> $parameters */
    protected function validateRequiredWith(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'required_with');

        return $this->allFailingRequired($parameters) || $this->validateRequired($attribute, $value);
    }

    /** @param list<string> $parameters */
    protected function validateRequiredWithAll(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'required_with_all');

        return $this->anyFailingRequired($parameters) || $this->validateRequired($attribute, $value);
    }

    /** @param list<string> $parameters */
    protected function validateRequiredWithout(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'required_without');

        return ! $this->anyFailingRequired($parameters) || $this->validateRequired($attribute, $value);
    }

    /** @param list<string> $parameters */
    protected function validateRequiredWithoutAll(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'required_without_all');

        return ! $this->allFailingRequired($parameters) || $this->validateRequired($attribute, $value);
    }

    /** @param list<string> $parameters */
    protected function validateAccepted(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->validateRequired($attribute, $value) && in_array($value, ['yes', 'on', '1', 1, true, 'true'], true);
    }

    /** @param list<string> $parameters */
    protected function validateAcceptedIf(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(2, $parameters, 'accepted_if');

        return ! $this->dependentValuesMatch($parameters) || $this->validateAccepted($attribute, $value, []);
    }

    /** @param list<string> $parameters */
    protected function validateDeclined(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->validateRequired($attribute, $value) && in_array($value, ['no', 'off', '0', 0, false, 'false'], true);
    }

    /** @param list<string> $parameters */
    protected function validateDeclinedIf(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(2, $parameters, 'declined_if');

        return ! $this->dependentValuesMatch($parameters) || $this->validateDeclined($attribute, $value, []);
    }

    /** @param list<string> $parameters */
    protected function validateProhibited(string $attribute, mixed $value, array $parameters): bool
    {
        return ! $this->validateRequired($attribute, $value);
    }

    /** @param list<string> $parameters */
    protected function validateProhibitedIf(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(2, $parameters, 'prohibited_if');

        return ! $this->dependentValuesMatch($parameters) || ! $this->validateRequired($attribute, $value);
    }

    /** @param list<string> $parameters */
    protected function validateProhibitedUnless(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(2, $parameters, 'prohibited_unless');

        return $this->dependentValuesMatch($parameters) || ! $this->validateRequired($attribute, $value);
    }

    /** @param list<string> $parameters */
    protected function validateMissing(string $attribute, mixed $value, array $parameters): bool
    {
        return ! $this->hasAttribute($attribute);
    }

    /** @param list<string> $parameters */
    protected function validateMissingIf(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(2, $parameters, 'missing_if');

        return ! $this->dependentValuesMatch($parameters) || ! $this->hasAttribute($attribute);
    }

    /** @param list<string> $parameters */
    protected function validateMissingUnless(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(2, $parameters, 'missing_unless');

        return $this->dependentValuesMatch($parameters) || ! $this->hasAttribute($attribute);
    }

    /** @param list<string> $parameters */
    protected function validateMissingWith(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'missing_with');

        return $this->allFailingRequired($parameters) || ! $this->hasAttribute($attribute);
    }

    /** @param list<string> $parameters */
    protected function validateMissingWithAll(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'missing_with_all');

        return $this->anyFailingRequired($parameters) || ! $this->hasAttribute($attribute);
    }

    // As regras exclude* são tratadas em shouldBeExcluded(): aqui passam sempre.

    /** @param list<string> $parameters */
    protected function validateExclude(string $attribute, mixed $value, array $parameters): bool
    {
        return true;
    }

    /** @param list<string> $parameters */
    protected function validateExcludeIf(string $attribute, mixed $value, array $parameters): bool
    {
        return true;
    }

    /** @param list<string> $parameters */
    protected function validateExcludeUnless(string $attribute, mixed $value, array $parameters): bool
    {
        return true;
    }

    /** @param list<string> $parameters */
    protected function validateExcludeWith(string $attribute, mixed $value, array $parameters): bool
    {
        return true;
    }

    /** @param list<string> $parameters */
    protected function validateExcludeWithout(string $attribute, mixed $value, array $parameters): bool
    {
        return true;
    }

    // =========================================================================
    // Tipos
    // =========================================================================

    /** @param list<string> $parameters */
    protected function validateString(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value);
    }

    /** @param list<string> $parameters */
    protected function validateNumeric(string $attribute, mixed $value, array $parameters): bool
    {
        return is_numeric($value);
    }

    /** @param list<string> $parameters */
    protected function validateInteger(string $attribute, mixed $value, array $parameters): bool
    {
        return (is_int($value) || is_string($value)) && filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /** @param list<string> $parameters */
    protected function validateBoolean(string $attribute, mixed $value, array $parameters): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1'], true);
    }

    /** @param list<string> $parameters */
    protected function validateArray(string $attribute, mixed $value, array $parameters): bool
    {
        if (! is_array($value)) {
            return false;
        }

        return $parameters === [] || array_diff_key($value, array_flip($parameters)) === [];
    }

    /** @param list<string> $parameters */
    protected function validateJson(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value) && $value !== '' && json_validate($value);
    }

    /** @param list<string> $parameters */
    protected function validateTimezone(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value) && in_array($value, DateTimeZone::listIdentifiers(), true);
    }

    /** @param list<string> $parameters */
    protected function validateHexColor(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value) && preg_match('/^#(?:(?:[0-9a-f]{3}){1,2}|(?:[0-9a-f]{4}){1,2})$/i', $value) === 1;
    }

    // =========================================================================
    // Texto e formatos
    // =========================================================================

    /** @param list<string> $parameters */
    protected function validateEmail(string $attribute, mixed $value, array $parameters): bool
    {
        if (! is_string($value) && ! $value instanceof Stringable) {
            return false;
        }

        $value = (string) $value;

        if (strlen($value) > 254 || filter_var($value, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) === false) {
            return false;
        }

        if (in_array('dns', $parameters, true)) {
            $domain = substr($value, (int) strrpos($value, '@') + 1);

            return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
        }

        return true;
    }

    /** @param list<string> $parameters */
    protected function validateUrl(string $attribute, mixed $value, array $parameters): bool
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        $allowed = $parameters !== [] ? array_map('strtolower', $parameters) : ['http', 'https', 'ftp', 'ftps'];

        return in_array($scheme, $allowed, true);
    }

    /** @param list<string> $parameters */
    protected function validateIp(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /** @param list<string> $parameters */
    protected function validateIpv4(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /** @param list<string> $parameters */
    protected function validateIpv6(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /** @param list<string> $parameters */
    protected function validateUuid(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value) && Str::isUuid($value);
    }

    /** @param list<string> $parameters */
    protected function validateAlpha(string $attribute, mixed $value, array $parameters): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return preg_match(in_array('ascii', $parameters, true) ? '/^[a-zA-Z]+$/' : '/^[\pL\pM]+$/u', $value) === 1;
    }

    /** @param list<string> $parameters */
    protected function validateAlphaNum(string $attribute, mixed $value, array $parameters): bool
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        return preg_match(in_array('ascii', $parameters, true) ? '/^[a-zA-Z0-9]+$/' : '/^[\pL\pM\pN]+$/u', (string) $value) === 1;
    }

    /** @param list<string> $parameters */
    protected function validateAlphaDash(string $attribute, mixed $value, array $parameters): bool
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        return preg_match(in_array('ascii', $parameters, true) ? '/^[a-zA-Z0-9_-]+$/' : '/^[\pL\pM\pN_-]+$/u', (string) $value) === 1;
    }

    /** @param list<string> $parameters */
    protected function validateAscii(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value) && preg_match('/[^\x00-\x7F]/', $value) !== 1;
    }

    /** @param list<string> $parameters */
    protected function validateLowercase(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value) && mb_strtolower($value) === $value;
    }

    /** @param list<string> $parameters */
    protected function validateUppercase(string $attribute, mixed $value, array $parameters): bool
    {
        return is_string($value) && mb_strtoupper($value) === $value;
    }

    /** @param list<string> $parameters */
    protected function validateRegex(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'regex');

        return (is_string($value) || is_numeric($value)) && @preg_match($parameters[0], (string) $value) === 1;
    }

    /** @param list<string> $parameters */
    protected function validateNotRegex(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'not_regex');

        return (is_string($value) || is_numeric($value)) && @preg_match($parameters[0], (string) $value) === 0;
    }

    /** @param list<string> $parameters */
    protected function validateStartsWith(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'starts_with');

        return is_string($value) && Str::startsWith($value, $parameters);
    }

    /** @param list<string> $parameters */
    protected function validateEndsWith(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'ends_with');

        return is_string($value) && Str::endsWith($value, $parameters);
    }

    /** @param list<string> $parameters */
    protected function validateDoesntStartWith(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'doesnt_start_with');

        return is_string($value) && ! Str::startsWith($value, $parameters);
    }

    /** @param list<string> $parameters */
    protected function validateDoesntEndWith(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'doesnt_end_with');

        return is_string($value) && ! Str::endsWith($value, $parameters);
    }

    /** @param list<string> $parameters */
    protected function validateContains(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'contains');

        if (! is_array($value)) {
            return false;
        }

        foreach ($parameters as $parameter) {
            if (! in_array($parameter, $value)) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // Conjuntos
    // =========================================================================

    /** @param list<string> $parameters */
    protected function validateIn(string $attribute, mixed $value, array $parameters): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! is_scalar($item) || ! in_array((string) $item, $parameters, true)) {
                    return false;
                }
            }

            return $value !== [];
        }

        return is_scalar($value) && in_array((string) $value, $parameters, true);
    }

    /** @param list<string> $parameters */
    protected function validateNotIn(string $attribute, mixed $value, array $parameters): bool
    {
        return ! $this->validateIn($attribute, $value, $parameters);
    }

    /** @param list<string> $parameters */
    protected function validateInArray(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'in_array');

        if (! is_scalar($value)) {
            return false;
        }

        $other = $parameters[0];
        $candidates = [];

        if (str_contains($other, '*')) {
            $parser = new \Slendie\Validation\ValidationRuleParser($this->data);

            foreach (array_keys($parser->explode([$other => 'present'])) as $concrete) {
                $candidates[] = $this->getValue($concrete);
            }
        } else {
            $array = $this->getValue($other);
            $candidates = is_array($array) ? $array : [];
        }

        return in_array($value, $candidates);
    }

    /** @param list<string> $parameters */
    protected function validateEnum(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'enum');

        if (! enum_exists($parameters[0])) {
            throw new InvalidArgumentException("A regra de validação [enum] precisa de um enum existente, recebeu [{$parameters[0]}].");
        }

        $valid = true;

        (new Enum($parameters[0]))
            ->setValidator($this)
            ->validate($attribute, $value, static function () use (&$valid): void {
                $valid = false;
            });

        return $valid;
    }

    /** @param list<string> $parameters */
    protected function validateDistinct(string $attribute, mixed $value, array $parameters): bool
    {
        $pattern = $this->implicitAttributes[$attribute] ?? null;

        if ($pattern === null) {
            return true;
        }

        $ignoreCase = in_array('ignore_case', $parameters, true);
        $strict = in_array('strict', $parameters, true);
        $value = $ignoreCase && is_string($value) ? mb_strtolower($value) : $value;

        foreach ($this->implicitAttributes as $other => $otherPattern) {
            if ($otherPattern !== $pattern || $other === $attribute) {
                continue;
            }

            $otherValue = $this->getValue($other);
            $otherValue = $ignoreCase && is_string($otherValue) ? mb_strtolower($otherValue) : $otherValue;

            if ($strict ? $otherValue === $value : $otherValue == $value) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // Datas
    // =========================================================================

    /** @param list<string> $parameters */
    protected function validateDate(string $attribute, mixed $value, array $parameters): bool
    {
        if ($value instanceof DateTimeInterface) {
            return true;
        }

        if ((! is_string($value) && ! is_numeric($value)) || strtotime((string) $value) === false) {
            return false;
        }

        $date = date_parse((string) $value);

        // Formatos relativos ("tomorrow") não têm dia/mês/ano: strtotime já os aceitou.
        if (! is_int($date['month']) || ! is_int($date['day']) || ! is_int($date['year'])) {
            return true;
        }

        return checkdate($date['month'], $date['day'], $date['year']);
    }

    /** @param list<string> $parameters */
    protected function validateDateFormat(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'date_format');

        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        foreach ($parameters as $format) {
            $date = DateTime::createFromFormat('!'.$format, (string) $value);

            if ($date !== false && $date->format($format) === (string) $value) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $parameters */
    protected function validateBefore(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->compareDates($attribute, $value, $parameters, 'before', static fn (int $a, int $b): bool => $a < $b);
    }

    /** @param list<string> $parameters */
    protected function validateBeforeOrEqual(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->compareDates($attribute, $value, $parameters, 'before_or_equal', static fn (int $a, int $b): bool => $a <= $b);
    }

    /** @param list<string> $parameters */
    protected function validateAfter(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->compareDates($attribute, $value, $parameters, 'after', static fn (int $a, int $b): bool => $a > $b);
    }

    /** @param list<string> $parameters */
    protected function validateAfterOrEqual(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->compareDates($attribute, $value, $parameters, 'after_or_equal', static fn (int $a, int $b): bool => $a >= $b);
    }

    /**
     * @param  list<string>  $parameters
     * @param  Closure(int, int): bool  $compare
     */
    protected function compareDates(string $attribute, mixed $value, array $parameters, string $rule, Closure $compare): bool
    {
        $this->requireParameterCount(1, $parameters, $rule);

        if (! is_string($value) && ! is_numeric($value) && ! $value instanceof DateTimeInterface) {
            return false;
        }

        $first = $this->timestampOf($value, $this->dateFormatOf($attribute));
        $second = $this->hasAttribute($parameters[0])
            ? $this->timestampOf($this->getValue($parameters[0]), $this->dateFormatOf($parameters[0]))
            : $this->timestampOf($parameters[0], $this->dateFormatOf($attribute));

        return $first !== null && $second !== null && $compare($first, $second);
    }

    protected function dateFormatOf(string $attribute): ?string
    {
        $rule = $this->getRule($attribute, ['DateFormat']);

        return $rule[1][0] ?? null;
    }

    protected function timestampOf(mixed $value, ?string $format): ?int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        if ($format !== null) {
            $date = DateTime::createFromFormat('!'.$format, (string) $value);

            if ($date !== false) {
                return $date->getTimestamp();
            }
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : $timestamp;
    }

    // =========================================================================
    // Tamanhos e comparações
    // =========================================================================

    /** @param list<string> $parameters */
    protected function validateMin(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'min');

        return $this->isSizeable($value) && $this->sizeOf($attribute, $value) >= (float) $parameters[0];
    }

    /** @param list<string> $parameters */
    protected function validateMax(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'max');

        return $this->isSizeable($value) && $this->sizeOf($attribute, $value) <= (float) $parameters[0];
    }

    /** @param list<string> $parameters */
    protected function validateSize(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'size');

        return $this->isSizeable($value) && $this->sizeOf($attribute, $value) == (float) $parameters[0];
    }

    /** @param list<string> $parameters */
    protected function validateBetween(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(2, $parameters, 'between');

        if (! $this->isSizeable($value)) {
            return false;
        }

        $size = $this->sizeOf($attribute, $value);

        return $size >= (float) $parameters[0] && $size <= (float) $parameters[1];
    }

    /** @param list<string> $parameters */
    protected function validateGt(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->compareSizes($attribute, $value, $parameters, 'gt', static fn (float $a, float $b): bool => $a > $b);
    }

    /** @param list<string> $parameters */
    protected function validateGte(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->compareSizes($attribute, $value, $parameters, 'gte', static fn (float $a, float $b): bool => $a >= $b);
    }

    /** @param list<string> $parameters */
    protected function validateLt(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->compareSizes($attribute, $value, $parameters, 'lt', static fn (float $a, float $b): bool => $a < $b);
    }

    /** @param list<string> $parameters */
    protected function validateLte(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->compareSizes($attribute, $value, $parameters, 'lte', static fn (float $a, float $b): bool => $a <= $b);
    }

    /**
     * @param  list<string>  $parameters
     * @param  Closure(float, float): bool  $compare
     */
    protected function compareSizes(string $attribute, mixed $value, array $parameters, string $rule, Closure $compare): bool
    {
        $this->requireParameterCount(1, $parameters, $rule);

        if (! $this->isSizeable($value)) {
            return false;
        }

        if ($this->hasAttribute($parameters[0])) {
            $other = $this->getValue($parameters[0]);

            if (! $this->isSizeable($other)) {
                return false;
            }

            return $compare((float) $this->sizeOf($attribute, $value), (float) $this->sizeOf($attribute, $other));
        }

        return is_numeric($parameters[0]) && $compare((float) $this->sizeOf($attribute, $value), (float) $parameters[0]);
    }

    /** @param list<string> $parameters */
    protected function validateDigits(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'digits');

        return (is_string($value) || is_int($value)) && preg_match('/^[0-9]+$/', (string) $value) === 1
            && strlen((string) $value) === (int) $parameters[0];
    }

    /** @param list<string> $parameters */
    protected function validateDigitsBetween(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(2, $parameters, 'digits_between');

        if ((! is_string($value) && ! is_int($value)) || preg_match('/^[0-9]+$/', (string) $value) !== 1) {
            return false;
        }

        $length = strlen((string) $value);

        return $length >= (int) $parameters[0] && $length <= (int) $parameters[1];
    }

    /** @param list<string> $parameters */
    protected function validateMultipleOf(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'multiple_of');

        if (! is_numeric($value) || ! is_numeric($parameters[0])) {
            return false;
        }

        $divisor = (float) $parameters[0];

        if ($divisor == 0.0) {
            return (float) $value == 0.0;
        }

        $remainder = abs(fmod((float) $value, $divisor));

        return $remainder < 1e-9 || abs($remainder - abs($divisor)) < 1e-9;
    }

    /** @param list<string> $parameters */
    protected function validateDecimal(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'decimal');

        if (! is_numeric($value)) {
            return false;
        }

        $string = is_string($value) ? $value : (string) $value;

        if (preg_match('/^[+-]?\d*(?:\.(\d*))?$/', $string, $matches) !== 1) {
            return false;
        }

        $decimals = strlen($matches[1] ?? '');
        $min = (int) $parameters[0];
        $max = isset($parameters[1]) ? (int) $parameters[1] : $min;

        return $decimals >= $min && $decimals <= $max;
    }

    protected function isSizeable(mixed $value): bool
    {
        if ($value instanceof UploadedFile) {
            return $value->isValid();
        }

        return is_scalar($value) || is_array($value) || $value instanceof Countable || $value instanceof Stringable;
    }

    /**
     * Tamanho conforme o tipo: valor (numeric), nº de caracteres (string), nº de itens (array) ou KB (ficheiro).
     */
    protected function sizeOf(string $attribute, mixed $value): int|float
    {
        if (is_numeric($value) && $this->hasRule($attribute, ['Numeric', 'Integer', 'Decimal'])) {
            return $value + 0;
        }

        if (is_array($value) || $value instanceof Countable) {
            return count($value);
        }

        if ($value instanceof UploadedFile) {
            return $value->size() / 1024;
        }

        return mb_strlen(is_scalar($value) || $value instanceof Stringable ? (string) $value : '');
    }

    // =========================================================================
    // Relações entre campos
    // =========================================================================

    /** @param list<string> $parameters */
    protected function validateSame(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'same');

        return $this->hasAttribute($parameters[0]) && $value === $this->getValue($parameters[0]);
    }

    /** @param list<string> $parameters */
    protected function validateDifferent(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'different');

        foreach ($parameters as $other) {
            if ($this->hasAttribute($other) && $value === $this->getValue($other)) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $parameters */
    protected function validateConfirmed(string $attribute, mixed $value, array $parameters): bool
    {
        return $this->validateSame($attribute, $value, [$parameters[0] ?? $attribute.'_confirmation']);
    }

    // =========================================================================
    // Base de dados
    // =========================================================================

    /** @param list<string> $parameters */
    protected function validateUnique(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'unique');

        if (! is_scalar($value)) {
            return false;
        }

        $column = ($parameters[1] ?? 'NULL') !== 'NULL' ? $parameters[1] : $this->columnFor($attribute);
        $except = ($parameters[2] ?? 'NULL') !== 'NULL' ? $parameters[2] : null;
        $idColumn = ($parameters[3] ?? '') !== '' ? $parameters[3] : null;

        return $this->getPresenceVerifier()->getCount(
            $parameters[0],
            $column,
            $value,
            $except,
            $idColumn,
            $this->extraConditions(array_slice($parameters, 4)),
        ) === 0;
    }

    /** @param list<string> $parameters */
    protected function validateExists(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'exists');

        $column = ($parameters[1] ?? 'NULL') !== 'NULL' ? $parameters[1] : $this->columnFor($attribute);
        $verifier = $this->getPresenceVerifier();

        if (is_array($value)) {
            $distinct = array_values(array_unique($value, SORT_REGULAR));

            return $distinct !== [] && $verifier->getMultiCount($parameters[0], $column, $distinct) >= count($distinct);
        }

        return is_scalar($value) && $verifier->getCount($parameters[0], $column, $value) > 0;
    }

    protected function columnFor(string $attribute): string
    {
        $last = str_contains($attribute, '.') ? substr($attribute, (int) strrpos($attribute, '.') + 1) : $attribute;

        return ctype_digit($last) ? $attribute : $last;
    }

    /**
     * Pares `coluna,valor` extra das regras em texto: `unique:users,email,NULL,id,account_id,3`.
     *
     * @param  list<string>  $pairs
     * @return list<Closure(Builder): mixed>
     */
    protected function extraConditions(array $pairs): array
    {
        $conditions = [];

        foreach (array_chunk($pairs, 2) as $pair) {
            [$column, $value] = [$pair[0], $pair[1] ?? 'NULL'];

            $conditions[] = match (true) {
                $value === 'NULL' => static fn (Builder $query): mixed => $query->whereNull($column),
                $value === 'NOT_NULL' => static fn (Builder $query): mixed => $query->whereNull($column, 'and', true),
                str_starts_with($value, '!') => static fn (Builder $query): mixed => $query->where($column, '<>', substr($value, 1)),
                default => static fn (Builder $query): mixed => $query->where($column, '=', $value),
            };
        }

        return $conditions;
    }

    // =========================================================================
    // Ficheiros
    // =========================================================================

    /** @param list<string> $parameters */
    protected function validateFile(string $attribute, mixed $value, array $parameters): bool
    {
        return $value instanceof UploadedFile && $value->isValid();
    }

    /** @param list<string> $parameters */
    protected function validateImage(string $attribute, mixed $value, array $parameters): bool
    {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/x-ms-bmp', 'image/webp', 'image/avif'];

        if (in_array('allow_svg', $parameters, true)) {
            $allowed[] = 'image/svg+xml';
        }

        return $this->validateFile($attribute, $value, []) && in_array($this->detectMime($value), $allowed, true);
    }

    /** @param list<string> $parameters */
    protected function validateMimes(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'mimes');

        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return false;
        }

        $parameters = array_map('strtolower', $parameters);

        if (in_array('jpg', $parameters, true) || in_array('jpeg', $parameters, true)) {
            $parameters = array_unique([...$parameters, 'jpg', 'jpeg', 'jpe']);
        }

        $mime = $this->detectMime($value);

        if ($mime === null) {
            return false;
        }

        if (array_intersect($this->mimeExtensions[$mime] ?? [], $parameters) !== []) {
            return true;
        }

        // Formatos de texto não têm assinatura própria: para esses (e só esses) confia-se na extensão do nome.
        return in_array($mime, ['text/plain', 'text/csv', 'application/csv'], true)
            && in_array($value->extension(), ['txt', 'text', 'csv', 'tsv', 'md', 'log', 'json'], true)
            && in_array($value->extension(), $parameters, true);
    }

    /** @param list<string> $parameters */
    protected function validateMimetypes(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'mimetypes');

        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return false;
        }

        $mime = $this->detectMime($value);

        if ($mime === null) {
            return false;
        }

        foreach ($parameters as $allowed) {
            if ($allowed === $mime || (str_ends_with($allowed, '/*') && str_starts_with($mime, substr($allowed, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $parameters */
    protected function validateExtensions(string $attribute, mixed $value, array $parameters): bool
    {
        $this->requireParameterCount(1, $parameters, 'extensions');

        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return false;
        }

        return in_array($value->extension(), array_map('strtolower', $parameters), true);
    }

    protected function detectMime(UploadedFile $file): ?string
    {
        if (! is_file($file->path())) {
            return null;
        }

        if (class_exists(finfo::class)) {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file->path());

            return $mime === false ? null : $mime;
        }

        return $file->mimeType();
    }

    // =========================================================================
    // Auxiliares de dependência
    // =========================================================================

    /**
     * O valor do outro campo (`parameters[0]`) está entre os valores seguintes?
     *
     * @param  list<string>  $parameters
     */
    protected function dependentValuesMatch(array $parameters): bool
    {
        if (count($parameters) < 2) {
            return false;
        }

        $other = $this->getValue($parameters[0]);
        $values = array_slice($parameters, 1);

        if (is_bool($other)) {
            $values = array_map(static fn (string $v): bool|string => filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $v, $values);
        } elseif ($other === null) {
            $values = array_map(static fn (string $v): ?string => $v === 'null' ? null : $v, $values);
        }

        return in_array($other, $values, is_bool($other) || $other === null);
    }

    /**
     * Algum dos campos indicados está ausente/vazio?
     *
     * @param  list<string>  $attributes
     */
    protected function anyFailingRequired(array $attributes): bool
    {
        foreach ($attributes as $key) {
            if (! $this->validateRequired($key, $this->getValue($key))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Todos os campos indicados estão ausentes/vazios?
     *
     * @param  list<string>  $attributes
     */
    protected function allFailingRequired(array $attributes): bool
    {
        foreach ($attributes as $key) {
            if ($this->validateRequired($key, $this->getValue($key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $parameters
     */
    protected function requireParameterCount(int $count, array $parameters, string $rule): void
    {
        if (count($parameters) < $count) {
            throw new InvalidArgumentException("A regra de validação [{$rule}] requer pelo menos {$count} parâmetro(s).");
        }
    }
}
