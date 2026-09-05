<?php

namespace App\Services\Params;

/**
 * Catálogo de tipos de parámetro soportados y los campos que exige cada uno.
 * Cualquier tipo fuera de esta lista invalida la carga del dashboard.
 */
final class ParamTypes
{
    public const NUMBER = 'number';

    public const TEXT = 'text';

    public const BOOLEAN = 'boolean';

    public const SELECT = 'select';

    public const RANGE = 'range';

    public const DATE = 'date';

    public const COLOR = 'color';

    /** @var list<string> */
    public const ALL = [
        self::NUMBER, self::TEXT, self::BOOLEAN, self::SELECT, self::RANGE, self::DATE, self::COLOR,
    ];

    /** Campos obligatorios por tipo, además de id, label y type. */
    public const REQUIRED = [
        self::NUMBER => ['default'],
        self::TEXT => ['default'],
        self::BOOLEAN => ['default'],
        self::SELECT => ['default', 'options'],
        self::RANGE => ['default', 'min', 'max'],
        self::DATE => ['default'],
        self::COLOR => ['default'],
    ];

    /** Campos opcionales por tipo. */
    public const OPTIONAL = [
        self::NUMBER => ['min', 'max', 'step', 'unit'],
        self::TEXT => ['maxLength'],
        self::BOOLEAN => [],
        self::SELECT => [],
        self::RANGE => ['step', 'unit'],
        self::DATE => ['min', 'max'],
        self::COLOR => [],
    ];

    public static function isSupported(mixed $type): bool
    {
        return is_string($type) && in_array($type, self::ALL, true);
    }
}
