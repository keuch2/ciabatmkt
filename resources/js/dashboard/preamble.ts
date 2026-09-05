import type { ParamScalar } from '@/api/dashboards';

/**
 * Código que se inyecta en el iframe ANTES de cualquier script del dashboard.
 * Define el objeto global `Dashboard` y el puente de mensajes con el contenedor.
 *
 * Los parámetros iniciales van embebidos para que `Dashboard.params` esté completo
 * de forma síncrona: el dashboard puede leerlos en su primer script sin esperar mensajes.
 */
export function buildPreamble(initialParams: Record<string, ParamScalar>): string {
    // Escapar "<" evita que un valor de texto con "</script>" corte el bloque.
    const json = JSON.stringify(initialParams).replace(/</g, '\\u003c');

    return `(function () {
  var params = ${json};
  var listeners = [];
  var explicitHeight = false;
  var lastHeight = -1;

  function post(message) { window.parent.postMessage(message, '*'); }

  // Alto real del contenido. No usa documentElement.scrollHeight porque ese valor nunca baja
  // del alto del viewport del iframe y genera un bucle con el contenedor.
  function contentHeight() {
    var body = document.body;
    if (!body) return 0;
    var style = window.getComputedStyle(body);
    var margins = (parseFloat(style.marginTop) || 0) + (parseFloat(style.marginBottom) || 0);
    return Math.ceil(Math.max(body.scrollHeight, body.offsetHeight) + margins);
  }

  function measure() {
    var h = contentHeight();
    if (h > 0 && h !== lastHeight) { lastHeight = h; post({ type: 'dashboard:height', height: h }); }
  }

  function apply(next, notify) {
    var changed = [];
    for (var key in next) {
      if (Object.prototype.hasOwnProperty.call(next, key)) { params[key] = next[key]; changed.push(key); }
    }
    if (notify && changed.length) {
      listeners.forEach(function (cb) {
        try { cb(params, changed); }
        catch (e) { post({ type: 'dashboard:error', message: String((e && e.message) || e) }); }
      });
    }
    if (!explicitHeight) window.requestAnimationFrame(measure);
  }

  window.Dashboard = {
    params: params,
    onChange: function (cb) { if (typeof cb === 'function') listeners.push(cb); },
    contentHeight: contentHeight,
    setHeight: function (px) {
      explicitHeight = true;
      var h = px === undefined ? contentHeight() : Number(px);
      if (!isFinite(h) || h < 0) return;
      // Un 0 significa que el documento todavía no tiene layout (script corriendo durante el
      // parseo inicial): se vuelve a medir en el siguiente frame en lugar de informar 0.
      if (h === 0) { window.requestAnimationFrame(function () { var m = contentHeight(); if (m > 0) { lastHeight = m; post({ type: 'dashboard:height', height: m }); } }); return; }
      lastHeight = Math.round(h);
      post({ type: 'dashboard:height', height: lastHeight });
    },
    ready: function () { post({ type: 'dashboard:ready' }); if (!explicitHeight) window.requestAnimationFrame(measure); },
    setParam: function (paramId, value) { post({ type: 'param:change', paramId: String(paramId), value: value }); },
    reportError: function (message) { post({ type: 'dashboard:error', message: String(message) }); }
  };

  window.addEventListener('message', function (event) {
    if (event.source !== window.parent) return;
    var data = event.data;
    if (!data || typeof data !== 'object' || !data.params || typeof data.params !== 'object') return;
    if (data.type === 'params:init') apply(data.params, false);
    else if (data.type === 'params:update') apply(data.params, true);
  });

  window.addEventListener('error', function (event) {
    post({ type: 'dashboard:error', message: String(event.message || 'Error en el dashboard') });
  });
  window.addEventListener('unhandledrejection', function (event) {
    var r = event.reason;
    post({ type: 'dashboard:error', message: String((r && r.message) || r || 'Error en el dashboard') });
  });

  function observe() {
    if (typeof ResizeObserver !== 'function') return;
    var ro = new ResizeObserver(function () { if (!explicitHeight) measure(); });
    ro.observe(document.documentElement);
    if (document.body) ro.observe(document.body);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', observe); else observe();
  // Red de seguridad: si al terminar de cargar nunca se informó una altura útil, se mide igual.
  window.addEventListener('load', function () { if (!explicitHeight || lastHeight <= 0) measure(); });
})();`;
}
