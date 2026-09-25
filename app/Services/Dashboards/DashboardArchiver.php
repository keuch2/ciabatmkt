<?php

namespace App\Services\Dashboards;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Antes de eliminar un dashboard, guarda en disco todo lo que se perdería con el borrado en
 * cascada: registros compartidos, su historial, valores de parámetros y su historial. El archivo
 * queda en storage/app/private/dashboard-archive y se puede volver a cargar con
 * `php artisan dashboards:restore`.
 */
class DashboardArchiver
{
    public const DIR = 'dashboard-archive';

    /** @return string ruta relativa del archivo dentro del disco local */
    public function archive(Dashboard $dashboard, ?User $actor): string
    {
        $payload = [
            'archived_at' => now()->toIso8601String(),
            'archived_by' => $actor ? ['id' => $actor->id, 'name' => $actor->name, 'email' => $actor->email] : null,
            'dashboard' => [
                'id' => $dashboard->id, 'slug' => $dashboard->slug, 'title' => $dashboard->title, 'version' => $dashboard->version,
                'description' => $dashboard->description, 'manifest' => $dashboard->manifest, 'html' => $dashboard->html,
            ],
            'records' => DB::table('dashboard_records')->where('dashboard_id', $dashboard->id)->orderBy('collection')->orderBy('created_at')->get()
                ->map(fn ($r) => ['collection' => $r->collection, 'id' => $r->record_id, 'data' => json_decode($r->data), 'version' => (int) $r->version, 'updated_at' => (string) $r->updated_at, 'updated_by' => $r->updated_by])->all(),
            'record_history' => DB::table('dashboard_record_history')->where('dashboard_id', $dashboard->id)->orderBy('changed_at')->get()
                ->map(fn ($h) => ['collection' => $h->collection, 'id' => $h->record_id, 'action' => $h->action, 'version' => (int) $h->version, 'old_data' => json_decode($h->old_data ?? 'null'), 'new_data' => json_decode($h->new_data ?? 'null'), 'changed_by' => $h->changed_by, 'changed_at' => (string) $h->changed_at])->all(),
            'param_values' => DB::table('param_values')->where('dashboard_id', $dashboard->id)->get()
                ->map(fn ($p) => ['param_id' => $p->param_id, 'user_id' => $p->user_id, 'value' => json_decode($p->value), 'updated_by' => $p->updated_by, 'updated_at' => (string) $p->updated_at])->all(),
        ];

        $name = sprintf('%s/%s_%s_%s.json', self::DIR, $dashboard->slug, now()->format('Ymd-His'), substr($dashboard->id, 0, 8));
        Storage::disk('local')->put($name, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $name;
    }
}
