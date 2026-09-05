<?php

namespace App\Services\Manifest;

/**
 * Resumen de cambios entre el manifiesto vigente y uno nuevo, para mostrar antes de confirmar
 * una actualización. Un cambio de tipo es la advertencia principal: los valores guardados con el
 * tipo viejo pueden quedar inválidos.
 */
class ManifestDiff
{
    /**
     * @return array{
     *   added: list<array{id: string, type: string, label: string}>,
     *   removed: list<array{id: string, type: string, label: string}>,
     *   type_changed: list<array{id: string, from: string, to: string}>,
     *   modified: list<array{id: string, fields: list<string>}>,
     *   unchanged: int,
     *   warnings: list<string>
     * }
     */
    public function compare(array $old, array $new): array
    {
        $oldParams = $this->indexed($old);
        $newParams = $this->indexed($new);

        $added = $removed = $typeChanged = $modified = [];
        $unchanged = 0;

        foreach ($newParams as $id => $param) {
            if (! isset($oldParams[$id])) {
                $added[] = $this->summary($param);

                continue;
            }
            $previous = $oldParams[$id];
            if (($previous['type'] ?? null) !== ($param['type'] ?? null)) {
                $typeChanged[] = ['id' => $id, 'from' => (string) ($previous['type'] ?? '?'), 'to' => (string) ($param['type'] ?? '?')];

                continue;
            }
            $fields = [];
            foreach (array_unique([...array_keys($previous), ...array_keys($param)]) as $field) {
                if ($field !== 'id' && $this->canonical($previous[$field] ?? null) !== $this->canonical($param[$field] ?? null)) {
                    $fields[] = $field;
                }
            }
            if ($fields === []) {
                $unchanged++;
            } else {
                $modified[] = ['id' => $id, 'fields' => $fields];
            }
        }

        foreach ($oldParams as $id => $param) {
            if (! isset($newParams[$id])) {
                $removed[] = $this->summary($param);
            }
        }

        $warnings = [];
        foreach ($typeChanged as $change) {
            $warnings[] = "El parámetro «{$change['id']}» cambia de tipo {$change['from']} a {$change['to']}: los valores guardados con el tipo anterior pueden quedar inválidos y se ignorarán hasta que cada usuario los vuelva a definir.";
        }
        foreach ($removed as $param) {
            $warnings[] = "El parámetro «{$param['id']}» desaparece: los valores guardados quedan huérfanos (no se borran; podés limpiarlos con dashboards:prune-orphans).";
        }
        foreach ($modified as $change) {
            if (in_array('min', $change['fields'], true) || in_array('max', $change['fields'], true) || in_array('options', $change['fields'], true) || in_array('maxLength', $change['fields'], true)) {
                $warnings[] = "El parámetro «{$change['id']}» cambia su rango u opciones: los valores guardados fuera del nuevo rango se ignorarán.";
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'type_changed' => $typeChanged,
            'modified' => $modified,
            'unchanged' => $unchanged,
            'warnings' => $warnings,
        ];
    }

    /** Representación estable de un valor: las claves de los objetos se ordenan recursivamente. */
    private function canonical(mixed $value): string
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }
            $value = array_map(fn ($v) => is_array($v) ? json_decode($this->canonical($v), true) : $v, $value);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, array<string, mixed>> */
    private function indexed(array $manifest): array
    {
        $out = [];
        foreach ($manifest['params'] ?? [] as $param) {
            if (is_array($param) && is_string($param['id'] ?? null)) {
                $out[$param['id']] = $param;
            }
        }

        return $out;
    }

    private function summary(array $param): array
    {
        return ['id' => (string) $param['id'], 'type' => (string) ($param['type'] ?? '?'), 'label' => (string) ($param['label'] ?? '')];
    }
}
