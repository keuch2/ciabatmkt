<?php

namespace App\Services\Manifest;

use App\Services\Params\ParamTypes;
use App\Services\Params\ParamValueValidator;

/**
 * Reglas 3 a 8 del validador de carga, sobre el manifiesto ya parseado.
 * Cada problema indica regla, ruta (params[2].default) y un mensaje accionable.
 */
class ManifestValidator
{
    public const ID_PATTERN = '/^[A-Za-z][A-Za-z0-9_-]{0,99}$/';

    public const SLUG_PATTERN = '/^[a-z0-9][a-z0-9-]{0,99}$/';

    public function __construct(private readonly ParamValueValidator $values) {}

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<ManifestProblem>
     */
    public function validate(array $manifest): array
    {
        $problems = [];

        // Regla 3: campos raíz.
        $id = $manifest['id'] ?? null;
        if (! is_string($id) || $id === '') {
            $problems[] = new ManifestProblem(3, 'id', 'El manifiesto debe tener un "id" de texto no vacío.');
        } elseif (! preg_match(self::SLUG_PATTERN, $id)) {
            $problems[] = new ManifestProblem(3, 'id', 'El "id" del manifiesto sólo admite minúsculas, números y guiones (ej. ventas-trimestral).');
        }
        if (! is_string($manifest['version'] ?? null) || trim($manifest['version']) === '') {
            $problems[] = new ManifestProblem(3, 'version', 'El manifiesto debe tener una "version" de texto no vacía.');
        }
        if (! is_string($manifest['title'] ?? null) || trim($manifest['title']) === '') {
            $problems[] = new ManifestProblem(3, 'title', 'El manifiesto debe tener un "title" de texto no vacío.');
        }
        if (! array_key_exists('params', $manifest) || ! is_array($manifest['params']) || ! array_is_list($manifest['params'])) {
            $problems[] = new ManifestProblem(3, 'params', 'El manifiesto debe tener "params" como un arreglo (puede estar vacío).');

            return $problems;
        }

        $seen = [];
        foreach ($manifest['params'] as $index => $param) {
            $path = "params[{$index}]";

            if (! is_array($param) || array_is_list($param)) {
                $problems[] = new ManifestProblem(3, $path, 'Cada parámetro debe ser un objeto con id, label, type y default.');

                continue;
            }

            $paramId = $param['id'] ?? null;
            if (! is_string($paramId) || ! preg_match(self::ID_PATTERN, $paramId)) {
                $problems[] = new ManifestProblem(3, "{$path}.id", 'Cada parámetro necesita un "id" que empiece con letra y use sólo letras, números, guión y guión bajo.');
            } else {
                $path = "params[{$index}] ({$paramId})";

                // Regla 4: ids únicos.
                if (isset($seen[$paramId])) {
                    $problems[] = new ManifestProblem(4, "{$path}.id", "El id «{$paramId}» está repetido (ya aparece en params[{$seen[$paramId]}]).");
                }
                $seen[$paramId] = $index;
            }

            if (! is_string($param['label'] ?? null) || trim($param['label']) === '') {
                $problems[] = new ManifestProblem(3, "{$path}.label", 'Cada parámetro necesita un "label" de texto no vacío.');
            }

            // Regla 5: tipo soportado.
            $type = $param['type'] ?? null;
            if (! ParamTypes::isSupported($type)) {
                $shown = is_string($type) ? "«{$type}»" : 'ausente';
                $problems[] = new ManifestProblem(5, "{$path}.type", "Tipo {$shown} no soportado. Tipos válidos: ".implode(', ', ParamTypes::ALL).'.');

                continue;
            }

            // Regla 6: campos requeridos y forma de los opcionales.
            $missing = array_filter(ParamTypes::REQUIRED[$type], fn ($f) => ! array_key_exists($f, $param));
            if ($missing !== []) {
                $problems[] = new ManifestProblem(6, $path, "Un parámetro de tipo «{$type}» requiere: ".implode(', ', $missing).'.');

                continue;
            }
            $shapeProblems = $this->validateShape($type, $param, $path);
            $problems = [...$problems, ...$shapeProblems];
            if ($shapeProblems !== []) {
                continue;
            }

            // Regla 8: select con opciones no vacías y default entre ellas.
            if ($type === ParamTypes::SELECT) {
                $selectProblems = $this->validateSelectOptions($param, $path);
                $problems = [...$problems, ...$selectProblems];
                if ($selectProblems !== []) {
                    continue;
                }
            }

            // Regla 7: el default cumple su propio tipo y rango.
            $error = $this->values->validate($param, $param['default']);
            if ($error !== null) {
                $problems[] = new ManifestProblem(7, "{$path}.default", 'El default no es válido: '.$error);
            }
        }

        return $problems;
    }

    /** @return list<ManifestProblem> */
    private function validateShape(string $type, array $param, string $path): array
    {
        $problems = [];
        $isNumber = fn ($v) => is_int($v) || is_float($v);

        foreach (['min', 'max', 'step'] as $field) {
            if (in_array($type, [ParamTypes::NUMBER, ParamTypes::RANGE], true) && array_key_exists($field, $param) && ! $isNumber($param[$field])) {
                $problems[] = new ManifestProblem(6, "{$path}.{$field}", "El campo «{$field}» debe ser numérico.");
            }
        }
        if (in_array($type, [ParamTypes::NUMBER, ParamTypes::RANGE], true)) {
            if (array_key_exists('step', $param) && $isNumber($param['step']) && $param['step'] <= 0) {
                $problems[] = new ManifestProblem(6, "{$path}.step", 'El campo «step» debe ser mayor que cero.');
            }
            if ($isNumber($param['min'] ?? null) && $isNumber($param['max'] ?? null) && $param['min'] >= $param['max']) {
                $problems[] = new ManifestProblem(6, "{$path}.min", 'El campo «min» debe ser menor que «max».');
            }
            if (array_key_exists('unit', $param) && ! is_string($param['unit'])) {
                $problems[] = new ManifestProblem(6, "{$path}.unit", 'El campo «unit» debe ser un texto.');
            }
        }
        if ($type === ParamTypes::TEXT && array_key_exists('maxLength', $param) && (! is_int($param['maxLength']) || $param['maxLength'] < 1)) {
            $problems[] = new ManifestProblem(6, "{$path}.maxLength", 'El campo «maxLength» debe ser un entero mayor que cero.');
        }
        if ($type === ParamTypes::DATE) {
            foreach (['min', 'max'] as $field) {
                if (array_key_exists($field, $param) && (! is_string($param[$field]) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $param[$field]))) {
                    $problems[] = new ManifestProblem(6, "{$path}.{$field}", "El campo «{$field}» debe ser una fecha AAAA-MM-DD.");
                }
            }
        }

        return $problems;
    }

    /** @return list<ManifestProblem> */
    private function validateSelectOptions(array $param, string $path): array
    {
        $options = $param['options'];

        if (! is_array($options) || ! array_is_list($options) || $options === []) {
            return [new ManifestProblem(8, "{$path}.options", 'Un «select» necesita "options" con al menos una opción {value, label}.')];
        }

        $problems = [];
        $values = [];
        foreach ($options as $i => $option) {
            if (! is_array($option) || ! array_key_exists('value', $option) || ! is_scalar($option['value'])) {
                $problems[] = new ManifestProblem(8, "{$path}.options[{$i}]", 'Cada opción debe tener "value" escalar y "label" de texto.');

                continue;
            }
            if (! is_string($option['label'] ?? null) || trim($option['label']) === '') {
                $problems[] = new ManifestProblem(8, "{$path}.options[{$i}].label", 'Cada opción necesita un "label" de texto no vacío.');
            }
            $key = json_encode($option['value']);
            if (isset($values[$key])) {
                $problems[] = new ManifestProblem(8, "{$path}.options[{$i}].value", 'El valor '.$key.' está repetido en las opciones.');
            }
            $values[$key] = true;
        }

        if ($problems === [] && $this->values->validate($param, $param['default']) !== null) {
            $problems[] = new ManifestProblem(8, "{$path}.default", 'El default de un «select» debe ser el "value" de una de sus opciones.');
        }

        return $problems;
    }
}
