<?php

namespace App\Console\Commands;

use App\Models\Dashboard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Borra valores guardados cuyo param_id ya no existe en el manifiesto vigente del dashboard.
 * La resolución los ignora sola; este comando es limpieza manual, nunca automática.
 */
class PruneOrphanParamValues extends Command
{
    protected $signature = 'dashboards:prune-orphans
                            {--dry-run : Sólo listar, sin borrar}
                            {--dashboard= : Limitar a un dashboard (id o slug)}';

    protected $description = 'Elimina valores de parámetros que ya no existen en el manifiesto de su dashboard';

    public function handle(): int
    {
        $dashboards = Dashboard::query()
            ->when($this->option('dashboard'), fn ($q, $key) => $q->where('id', $key)->orWhere('slug', $key))
            ->orderBy('slug')
            ->get();

        if ($dashboards->isEmpty()) {
            $this->warn('No se encontraron dashboards.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $total = 0;

        foreach ($dashboards as $dashboard) {
            $known = array_map(fn ($p) => $p['id'], $dashboard->manifestParams());

            $orphans = DB::table('param_values')
                ->where('dashboard_id', $dashboard->id)
                ->whereNotIn('param_id', $known ?: [''])
                ->selectRaw('param_id, count(*) as filas')
                ->groupBy('param_id')
                ->orderBy('param_id')
                ->get();

            foreach ($orphans as $orphan) {
                $rows[] = [$dashboard->slug, $orphan->param_id, $orphan->filas];
                $total += $orphan->filas;
            }

            if (! $dryRun && $orphans->isNotEmpty()) {
                DB::table('param_values')
                    ->where('dashboard_id', $dashboard->id)
                    ->whereNotIn('param_id', $known ?: [''])
                    ->delete();
            }
        }

        if ($rows === []) {
            $this->info('No hay valores huérfanos.');

            return self::SUCCESS;
        }

        $this->table(['Dashboard', 'Parámetro', 'Filas'], $rows);
        $this->line($dryRun
            ? "Modo prueba: {$total} fila(s) quedarían eliminadas. Ejecutá sin --dry-run para borrarlas."
            : "Eliminadas {$total} fila(s) huérfana(s). El historial se conserva.");

        return self::SUCCESS;
    }
}
