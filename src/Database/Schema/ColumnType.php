<?php

declare(strict_types=1);

namespace Slendie\Database\Schema;

/**
 * Tipos lógicos de coluna; cada grammar traduz-os para o tipo SQL do seu driver.
 */
enum ColumnType: string
{
    case Increments = 'increments';
    case BigIncrements = 'bigIncrements';
    case SmallIncrements = 'smallIncrements';
    case Uuid = 'uuid';
    case String = 'string';
    case Char = 'char';
    case Text = 'text';
    case MediumText = 'mediumText';
    case LongText = 'longText';
    case Integer = 'integer';
    case BigInteger = 'bigInteger';
    case SmallInteger = 'smallInteger';
    case TinyInteger = 'tinyInteger';
    case Float = 'float';
    case Double = 'double';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'dateTime';
    case DateTimeTz = 'dateTimeTz';
    case Timestamp = 'timestamp';
    case TimestampTz = 'timestampTz';
    case Time = 'time';
    case Year = 'year';
    case Json = 'json';
    case Jsonb = 'jsonb';
    case Binary = 'binary';
    case Enum = 'enum';
    case IpAddress = 'ipAddress';
    case MacAddress = 'macAddress';

    public function isIncrements(): bool
    {
        return match ($this) {
            self::Increments, self::BigIncrements, self::SmallIncrements => true,
            default => false,
        };
    }
}
