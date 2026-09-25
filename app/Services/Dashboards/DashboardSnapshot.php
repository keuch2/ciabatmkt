<?php

namespace App\Services\Dashboards;

use App\Models\Dashboard;
use App\Models\DashboardRecord;
use App\Services\Params\ParamResolver;

/**
 * Copia del HTML vigente con los datos actuales de la plataforma adentro: registros de cada
 * colección y valores base de los parámetros. Al abrirla suelta en un navegador muestra lo mismo
 * que se ve en la plataforma (sin guardar cambios); si se vuelve a subir a la plataforma, el
 * bloque inyectado no hace nada porque `Dashboard` ya existe, y los datos no se tocan.
 */
class DashboardSnapshot
{
    public const MARKER_ID = 'ciabay-snapshot';

    public function __construct(private readonly ParamResolver $params) {}

    public function build(Dashboard $dashboard): string
    {
        $html = $this->strip($dashboard->html);

        $collections = [];
        foreach ($dashboard->manifestCollections() as $c) {
            $collections[$c['id']] = DashboardRecord::query()
                ->where('dashboard_id', $dashboard->id)->where('collection', $c['id'])
                ->orderBy('created_at')->orderBy('record_id')
                ->get()->map(fn (DashboardRecord $r) => ['id' => $r->record_id, 'data' => $r->data, 'version' => $r->version, 'updated_at' => $r->updated_at?->toIso8601String(), 'updated_by' => null])
                ->values()->all();
        }

        $snapshot = [
            'dashboard' => ['id' => $dashboard->slug, 'title' => $dashboard->title, 'version' => $dashboard->version],
            'generated_at' => now()->toIso8601String(),
            'generated_label' => now()->format('d/m/Y H:i'),
            'params' => $this->params->values($dashboard, null),
            'collections' => (object) $collections,
        ];
        // "<" escapado: un texto con "</script>" adentro cortaría el bloque.
        $json = str_replace('<', '\\u003c', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $script = '<script id="'.self::MARKER_ID.'">'."\n".$this->shim($json)."\n</script>\n";

        if (preg_match('/<head\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $at = $m[0][1] + strlen($m[0][0]);

            return substr($html, 0, $at).$script.substr($html, $at);
        }
        if (preg_match('/<html\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $at = $m[0][1] + strlen($m[0][0]);

            return substr($html, 0, $at)."\n<head>\n".$script."</head>\n".substr($html, $at);
        }

        return $script.$html;
    }

    /** Quita un bloque de copia anterior, por si se vuelve a generar sobre un archivo ya copiado. */
    public function strip(string $html): string
    {
        return preg_replace('~<script id="'.preg_quote(self::MARKER_ID, '~').'">.*?</script>\n?~s', '', $html) ?? $html;
    }

    private function shim(string $json): string
    {
        return <<<JS
/* Copia generada por la plataforma Ciabay Dashboards con los datos cargados por los usuarios.
   Sólo actúa cuando el archivo se abre suelto (sin la plataforma): los cambios no se guardan.
   Si este archivo se vuelve a subir a la plataforma, este bloque se ignora y los datos guardados
   no se modifican. */
if (typeof window.Dashboard === 'undefined') {
  var __snapshot = {$json};
  var __store = {};
  Object.keys(__snapshot.collections).forEach(function (c) {
    __store[c] = {};
    __snapshot.collections[c].forEach(function (r) { __store[c][r.id] = r; });
  });
  function __copy(v) { return JSON.parse(JSON.stringify(v)); }
  function __rows(c) { return Object.keys(__store[c] || {}).map(function (id) { return __copy(__store[c][id]); }); }
  window.Dashboard = {
    snapshot: __snapshot,
    params: __copy(__snapshot.params),
    user: { id: 'copia', name: 'Copia sin conexión', role: 'user' },
    onChange: function () {}, setParam: function () {}, setHeight: function () {}, ready: function () {},
    reportError: function (m) { if (window.console) console.error(m); },
    contentHeight: function () { return document.body ? document.body.scrollHeight : 0; },
    capture: function (el, o) { return typeof window.html2canvas === 'function' ? window.html2canvas(el, o) : Promise.reject(new Error('Sin html2canvas')); },
    clipboard: { write: function (v) { return typeof v === 'string' ? navigator.clipboard.writeText(v) : navigator.clipboard.write([new ClipboardItem({ 'image/png': v })]); } },
    data: {
      list: function (c) { return Promise.resolve(__rows(c)); },
      put: function (c, id, d) { __store[c] = __store[c] || {}; var v = (__store[c][id] ? __store[c][id].version : 0) + 1; __store[c][id] = { id: id, data: __copy(d), version: v, updated_at: new Date().toISOString(), updated_by: null }; return Promise.resolve(__copy(__store[c][id])); },
      remove: function (c, id) { var had = !!(__store[c] && __store[c][id]); if (had) delete __store[c][id]; return Promise.resolve(had); },
      seed: function (c, rs) { var n = 0; if (!__store[c] || !Object.keys(__store[c]).length) { __store[c] = {}; rs.forEach(function (r) { __store[c][r.id] = { id: r.id, data: __copy(r.data), version: 1 }; n++; }); } return Promise.resolve({ seeded: n, records: __rows(c) }); },
      replace: function (c, rs) { __store[c] = {}; rs.forEach(function (r) { __store[c][r.id] = { id: r.id, data: __copy(r.data), version: 1 }; }); return Promise.resolve({ replaced: rs.length, records: __rows(c) }); },
      onChange: function () {}
    }
  };
  document.addEventListener('DOMContentLoaded', function () {
    var b = document.createElement('div');
    b.setAttribute('style', 'position:fixed;left:0;right:0;bottom:0;z-index:2147483647;background:#0f2666;color:#fff;font:13px/1.4 system-ui,sans-serif;padding:8px 14px;display:flex;justify-content:space-between;gap:12px;align-items:center;box-shadow:0 -2px 8px rgba(0,0,0,.25)');
    b.innerHTML = '<span>Copia sin conexión de <b>' + __snapshot.dashboard.title + '</b> con los datos de la plataforma al ' + __snapshot.generated_label + '. Lo que edites acá <b>no se guarda</b>.</span><button type="button" style="border:0;background:#fff;color:#0f2666;border-radius:6px;padding:4px 10px;cursor:pointer;font-weight:600">Cerrar</button>';
    b.querySelector('button').onclick = function () { b.remove(); };
    document.body.appendChild(b);
  });
}
JS;
    }
}
