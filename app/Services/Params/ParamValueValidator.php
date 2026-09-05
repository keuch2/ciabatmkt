<?php

namespace App\Services\Params;

/**
 * Única fuente de verdad para decidir si un valor es válido para un parámetro del manifiesto.
 * La usan el validador de carga (para el default) y el endpoint de escritura (para el valor
 * enviado por el usuario). No la dupliques.
 */
class ParamValueValidator
{
    /**
     * Devuelve null si el valor es válido, o un mensaje legible si no lo es.
     *
     * @param  array<string, mixed>  $param  Definición del parámetro tal como está en el manifiesto.
     */
    public function validate(array $param, mixed $value): ?string
    {
        $id = (string) ($param['id'] ?? '?');
        $label = "«{$id}»";

        return match ($param['type'] ?? null) {
            ParamTypes::NUMBER, ParamTypes::RANGE => $this->validateNumber($param, $value, $label),
            ParamTypes::TEXT => $this->validateText($param, $value, $label),
            ParamTypes::BOOLEAN => is_bool($value) ? null : "El valor de {$label} debe ser verdadero o falso.",
            ParamTypes::SELECT => $this->validateSelect($param, $value, $label),
            ParamTypes::DATE => $this->validateDate($param, $value, $label),
            ParamTypes::COLOR => $this->validateColor($value, $label),
            default => "El parámetro {$label} tiene un tipo no soportado.",
        };
    }

    public function isValid(array $param, mixed $value): bool
    {
        return $this->validate($param, $value) === null;
    }

    private function validateNumber(array $param, mixed $value, string $label): ?string
    {
        if (! $this->isNumber($value)) {
            return "El valor de {$label} debe ser un número.";
        }

        $min = $param['min'] ?? null;
        $max = $param['max'] ?? null;

        if ($this->isNumber($min) && $this->isNumber($max) && ($value < $min || $value > $max)) {
            return "El valor de {$label} debe estar entre {$this->fmt($min)} y {$this->fmt($max)}.";
        }
        if ($this->isNumber($min) && $value < $min) {
            return "El valor de {$label} no puede ser menor que {$this->fmt($min)}.";
        }
        if ($this->isNumber($max) && $value > $max) {
            return "El valor de {$label} no puede ser mayor que {$this->fmt($max)}.";
        }

        return null;
    }

    private function validateText(array $param, mixed $value, string $label): ?string
    {
        if (! is_string($value)) {
            return "El valor de {$label} debe ser un texto.";
        }

        $maxLength = $param['maxLength'] ?? null;
        if (is_int($maxLength) && mb_strlen($value) > $maxLength) {
            return "El valor de {$label} no puede superar {$maxLength} caracteres.";
        }

        return null;
    }

    private function validateSelect(array $param, mixed $value, string $label): ?string
    {
        $options = is_array($param['options'] ?? null) ? $param['options'] : [];
        $allowed = array_map(fn ($o) => is_array($o) ? ($o['value'] ?? null) : null, $options);

        if (! is_scalar($value) || ! in_array($value, $allowed, true)) {
            $list = implode(', ', array_map(fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE), $allowed));

            return "El valor de {$label} debe ser una de las opciones: {$list}.";
        }

        return null;
    }

    private function validateDate(array $param, mixed $value, string $label): ?string
    {
        if (! is_string($value) || ! $this->isIsoDate($value)) {
            return "El valor de {$label} debe ser una fecha válida con formato AAAA-MM-DD.";
        }

        $min = $param['min'] ?? null;
        $max = $param['max'] ?? null;

        if (is_string($min) && $this->isIsoDate($min) && $value < $min) {
            return "La fecha de {$label} no puede ser anterior a {$min}.";
        }
        if (is_string($max) && $this->isIsoDate($max) && $value > $max) {
            return "La fecha de {$label} no puede ser posterior a {$max}.";
        }

        return null;
    }

    private function validateColor(mixed $value, string $label): ?string
    {
        if (! is_string($value) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return "El valor de {$label} debe ser un color en formato #RRGGBB.";
        }

        return null;
    }

    private function isNumber(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && ! is_nan((float) $value) && is_finite((float) $value);
    }

    private function isIsoDate(string $value): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private function fmt(int|float $n): string
    {
        return is_float($n) && floor($n) !== $n ? rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.') : (string) (int) $n;
    }
}
