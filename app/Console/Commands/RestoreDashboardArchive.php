<?php

namespace App\Console\Commands;

use App\Models\Dashboard;
use App\Models\User;
use App\Services\Records\RecordStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Lista los archivos guardados al eliminar dashboards y vuelve a cargar sus registros en un
 * dashboard existente (el mismo id del manifiesto republicado, u otro que declare las mismas
 * colecciones).
 */
class RestoreDashboardArchive extends Command
{
    protected $signature = 'dashboards:restore
                            {archive? : Nombre del archivo (ver la lista sin argumentos)}
                            {--dashboard= : Id o slug del dashboard destino}
                            {--collection=* : Sólo estas colecciones}
                            {--replace : Reemplazar la colección destino en lugar de cargar sólo si está vacía}
                            {--dry-run : Mostrar qué se cargaría sin escribir}';

    protected $description = 'Lista y restaura los datos archivados de dashboards eliminados';

    public function handle(RecordStore $store): int
    {
        $disk = Storage::disk('local');
        $archive = $this->argument('archive');

        if ($archive === null) {
            $files = collect($disk->files('dashboard-archive'))->sort()->values();
            if ($files->isEmpty()) {
                $this->info('No hay dashboards archivados.');

                return self::SUCCESS;
            }
            $this->table(['Archivo', 'Tamaño', 'Registros'], $files->map(function ($f) use ($disk) {
                $payload = json_decode($disk->get($f));

                return [basename($f), number_format($disk->size($f) / 1024).' KB', count($payload->records ?? [])];
            })->all());
            $this->line('Restaurar: php artisan dashboards:restore <archivo> --dashboard=<id o slug>');

            return self::SUCCESS;
        }

        $path = 'dashboard-archive/'.basename($archive);
        if (! $disk->exists($path)) {
            $this->error("No existe el archivo {$archive}.");

            return self::FAILURE;
        }
        $payload = json_decode($disk->get($path));

        $key = $this->option('dashboard') ?: $payload->dashboard->slug;
        $dashboard = Dashboard::query()->where('id', $key)->orWhere('slug', $key)->first();
        if ($dashboard === null) {
            $this->error("No hay un dashboard «{$key}». Publicá primero el archivo HTML (mismo id de manifiesto) y volvé a correr el comando.");

            return self::FAILURE;
        }

        $only = $this->option('collection');
        $byCollection = collect($payload->records)->groupBy(fn ($r) => $r->collection)->when($only, fn ($c) => $c->only($only));
        $declared = array_column($dashboard->manifestCollections(), 'id');
        $actor = User::query()->where('role', 'super_admin')->orderBy('created_at')->firstOrFail();

        foreach ($byCollection as $collection => $records) {
            if (! in_array($collection, $declared, true)) {
                $this->warn("La colección «{$collection}» no está declarada en el manifiesto de «{$dashboard->slug}»: se omite ({$records->count()} registros).");

                continue;
            }
            $rows = $records->map(fn ($r) => ['id' => $r->id, 'data' => $r->data])->values()->all();
            if ($this->option('dry-run')) {
                $this->line("{$collection}: {$records->count()} registros se cargarían".($this->option('replace') ? ' (reemplazando)' : ' (sólo si está vacía)'));

                continue;
            }
            $n = $this->option('replace') ? $store->replace($dashboard, $collection, $rows, $actor) : $store->seed($dashboard, $collection, $rows, $actor);
            $this->info("{$collection}: {$n} registros cargados".($n === 0 && ! $this->option('replace') ? ' (la colección ya tenía datos; usá --replace para pisarlos)' : ''));
        }

        return self::SUCCESS;
    }
}
