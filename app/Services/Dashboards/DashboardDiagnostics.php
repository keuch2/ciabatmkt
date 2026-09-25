<?php

namespace App\Services\Dashboards;

use App\Models\Dashboard;
use App\Models\DashboardRecord;
use App\Models\DashboardRecordHistory;
use App\Models\DashboardWriteFailure;
use Illuminate\Support\Facades\DB;

/**
 * Chequeo integral de un dashboard para el super administrador: reglas del validador sobre el
 * HTML guardado, colecciones (declaradas, usadas, con datos), registros cerca del tope de
 * tamaño, escrituras rechazadas y conflictos recientes, y guardados sin cambios repetidos. Cada
 * hallazgo trae qué hacer. `report` es el texto para pegar en el prompt de corrección.
 */
class DashboardDiagnostics
{
    private const DAYS = 7;

    public function __construct(private readonly DashboardPublisher $publisher) {}

    public function run(Dashboard $dashboard): array
    {
        $findings = [];

        // 1. Reglas del validador sobre el archivo vigente.
        $analysis = $this->publisher->analyze($dashboard->html);
        foreach ($analysis->problemsArray() as $p) {
            $findings[] = $this->finding('error', 'validador', "[regla {$p['rule']}] {$p['path']}: {$p['message']}", 'Corregí el archivo y actualizalo.');
        }

        // 2. Colecciones: declaradas, con datos, usadas en el código.
        $declared = array_column($dashboard->manifestCollections(), 'id');
        $withData = DashboardRecord::query()->where('dashboard_id', $dashboard->id)
            ->selectRaw('collection, count(*) as n, max(length(data)) as max_bytes, max(updated_at) as last')
            ->groupBy('collection')->get()->keyBy('collection');
        $used = $this->collectionsUsed($dashboard->html);
        foreach ($withData as $collection => $row) {
            if (! in_array($collection, $declared, true)) {
                $findings[] = $this->finding('warning', 'colecciones', "La colección «{$collection}» tiene {$row->n} registros pero ya no está declarada en el manifiesto: el dashboard no puede leerlos.", 'Volvé a declararla en el manifiesto o exportá los datos desde Datos.');
            }
        }
        foreach ($declared as $collection) {
            if ($used !== null && ! in_array($collection, $used, true)) {
                $findings[] = $this->finding('info', 'colecciones', "La colección «{$collection}» está declarada pero el código no la usa con Dashboard.data.", 'Si es intencional, ignoralo; si no, el dashboard no está guardando esa sección.');
            }
        }

        // 3. Tamaño de registros cerca del tope.
        $caps = [];
        foreach ($dashboard->manifestCollections() as $c) {
            $caps[$c['id']] = (int) ($c['maxBytes'] ?? config('dashboards.max_record_bytes'));
        }
        foreach ($withData as $collection => $row) {
            $cap = $caps[$collection] ?? (int) config('dashboards.max_record_bytes');
            if ($cap > 0 && $row->max_bytes >= $cap * 0.8) {
                $pct = (int) round($row->max_bytes * 100 / $cap);
                $findings[] = $this->finding($pct >= 95 ? 'error' : 'warning', 'tamaño', "En «{$collection}» hay un registro que usa el {$pct}% del tope ({$this->kb($row->max_bytes)} de {$this->kb($cap)}).", 'Un registro que supere el tope se rechaza al guardar. Subí maxBytes en el manifiesto (hasta 256 KB), dividí el registro o reducí lo que embebe (fotos).');
            }
        }

        // 4. Escrituras rechazadas, conflictos y guardados sin cambios.
        $since = now()->subDays(self::DAYS);
        $failures = DashboardWriteFailure::query()->with('user')->where('dashboard_id', $dashboard->id)->where('created_at', '>=', $since)->orderByDesc('created_at')->get();
        $byCode = $failures->groupBy('code');
        foreach (['invalid' => 'escrituras rechazadas', 'forbidden' => 'escrituras sin permiso'] as $code => $label) {
            if ($byCode->has($code)) {
                $items = $byCode[$code];
                $messages = $items->groupBy('message')->map->count()->sortDesc()->take(3)->map(fn ($n, $m) => "{$m} ({$n})")->values()->all();
                $findings[] = $this->finding('error', 'escrituras', ucfirst($label).' en los últimos '.self::DAYS.' días: '.$items->count().'. Motivos: '.implode(' · ', $messages), 'Cada una es un dato que el usuario creyó guardar y no se guardó. El motivo indica qué corregir en el archivo.');
            }
        }
        if ($byCode->has('conflict')) {
            $items = $byCode['conflict'];
            $users = $items->pluck('user.name')->filter()->unique()->values()->implode(', ');
            $findings[] = $this->finding('warning', 'concurrencia', 'Conflictos de edición en los últimos '.self::DAYS." días: {$items->count()} (usuarios: {$users}).", 'Dos personas editaron el mismo registro a la vez; la segunda vio su cambio reemplazado. Si es frecuente, el dashboard debería guardar en registros más chicos (uno por fila) en lugar de uno grande.');
        }
        if ($byCode->has('noop')) {
            $items = $byCode['noop'];
            $perRecord = $items->groupBy(fn ($f) => $f->collection.'/'.$f->record_id)->map->count()->sortDesc();
            $suspicious = $perRecord->filter(fn ($n) => $n >= 5);
            if ($suspicious->isNotEmpty()) {
                $findings[] = $this->finding('warning', 'guardado', 'Guardados repetidos sin cambio real: '.$suspicious->map(fn ($n, $k) => "{$k} ({$n})")->take(5)->values()->implode(' · '), 'El dashboard envía el registro pero su contenido no cambia. Suele ser un dato que se escribe en una estructura que JSON no serializa (propiedades con nombre sobre una lista, Set, Map, Date) o que se pierde antes de guardar.');
            }
        }

        // 5. Actividad.
        $lastWrite = DashboardRecordHistory::query()->where('dashboard_id', $dashboard->id)->max('changed_at');
        $writes = DashboardRecordHistory::query()->where('dashboard_id', $dashboard->id)->where('changed_at', '>=', $since)->count();

        usort($findings, fn ($a, $b) => ['error' => 0, 'warning' => 1, 'info' => 2][$a['severity']] <=> ['error' => 0, 'warning' => 1, 'info' => 2][$b['severity']]);

        $summary = [
            'dashboard' => ['id' => $dashboard->id, 'slug' => $dashboard->slug, 'title' => $dashboard->title, 'version' => $dashboard->version, 'updated_at' => $dashboard->updated_at?->toIso8601String()],
            'collections' => array_map(fn ($c) => [
                'id' => $c['id'], 'label' => $c['label'] ?? $c['id'],
                'records' => (int) ($withData[$c['id']]->n ?? 0),
                'max_bytes' => (int) ($withData[$c['id']]->max_bytes ?? 0),
                'cap_bytes' => $caps[$c['id']],
                'last_write' => isset($withData[$c['id']]) && $withData[$c['id']]->last ? date(DATE_ATOM, strtotime($withData[$c['id']]->last)) : null,
                'used_in_code' => $used === null ? null : in_array($c['id'], $used, true),
            ], $dashboard->manifestCollections()),
            'writes_last_days' => $writes,
            'last_write' => $lastWrite ? date(DATE_ATOM, strtotime($lastWrite)) : null,
            'failures_last_days' => $byCode->map->count()->all(),
            'recent_failures' => $failures->where('code', '!=', 'noop')->take(20)->map(fn ($f) => [
                'at' => $f->created_at?->toIso8601String(), 'code' => $f->code, 'operation' => $f->operation, 'collection' => $f->collection,
                'record_id' => $f->record_id, 'user' => $f->user?->name, 'message' => $f->message, 'bytes' => $f->bytes,
            ])->values()->all(),
            'days' => self::DAYS,
        ];

        return ['status' => $findings === [] ? 'ok' : ($findings[0]['severity'] === 'error' ? 'error' : 'warning'), 'findings' => $findings, 'summary' => $summary, 'report' => $this->report($summary, $findings)];
    }

    /** @return list<string>|null null si el archivo no tiene llamadas reconocibles */
    private function collectionsUsed(string $html): ?array
    {
        $found = [];
        if (preg_match_all('/\bDashboard\s*\.\s*data\s*\.\s*(?:list|put|remove|seed|replace)\s*\(\s*(?:([\'"`])([^\'"`]*)\1|([A-Za-z_$][\w$]*))/', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $name = ($match[2] ?? '') !== '' ? $match[2] : null;
                if ($name === null && ($match[3] ?? '') !== '' && preg_match('/\b(?:const|let|var)\s+'.preg_quote($match[3], '/').'\s*=\s*([\'"`])([^\'"`]*)\1/', $html, $c)) {
                    $name = $c[2];
                }
                if ($name === null) {
                    return null; // hay llamadas con nombre dinámico: no se puede afirmar nada
                }
                $found[$name] = true;
            }
        }

        return array_keys($found);
    }

    private function finding(string $severity, string $area, string $message, string $action): array
    {
        return ['severity' => $severity, 'area' => $area, 'message' => $message, 'action' => $action];
    }

    private function kb(int $bytes): string
    {
        return number_format($bytes / 1024, 0).' KB';
    }

    private function report(array $summary, array $findings): string
    {
        $d = $summary['dashboard'];
        $lines = ["INFORME DE DIAGNÓSTICO · {$d['title']} (id {$d['slug']}, versión {$d['version']})", 'Generado: '.now()->format('Y-m-d H:i'), ''];
        $lines[] = 'Colecciones declaradas:';
        foreach ($summary['collections'] as $c) {
            $lines[] = sprintf('- %s: %d registros, registro más grande %s de %s%s', $c['id'], $c['records'], $this->kb($c['max_bytes']), $this->kb($c['cap_bytes']), $c['used_in_code'] === false ? ' · NO usada en el código' : '');
        }
        $lines[] = '';
        $lines[] = 'Escrituras en los últimos '.$summary['days']." días: {$summary['writes_last_days']}. Fallas: ".(json_encode($summary['failures_last_days'], JSON_UNESCAPED_UNICODE) ?: '{}');
        $lines[] = '';
        $lines[] = $findings === [] ? 'Hallazgos: ninguno.' : 'Hallazgos:';
        foreach ($findings as $f) {
            $lines[] = sprintf('- [%s · %s] %s → %s', strtoupper($f['severity']), $f['area'], $f['message'], $f['action']);
        }
        if ($summary['recent_failures'] !== []) {
            $lines[] = '';
            $lines[] = 'Últimas escrituras rechazadas:';
            foreach (array_slice($summary['recent_failures'], 0, 10) as $f) {
                $lines[] = sprintf('- %s %s %s/%s por %s: %s', substr((string) $f['at'], 0, 16), $f['code'], $f['collection'], $f['record_id'] ?? '-', $f['user'] ?? '-', $f['message']);
            }
        }

        return implode("\n", $lines);
    }
}
