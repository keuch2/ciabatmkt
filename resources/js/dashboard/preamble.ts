import type { ParamScalar } from '@/api/dashboards';
import { installCapture } from './captureShim';

export interface Viewer {
    id: string;
    name: string;
    role: 'super_admin' | 'user';
}

/**
 * Código que se inyecta en el iframe ANTES de cualquier script del dashboard.
 * Define el objeto global `Dashboard` y el puente de mensajes con el contenedor:
 *
 * - `Dashboard.params` / `onChange` / `setParam`: parámetros escalares (opcionales).
 * - `Dashboard.data`: registros compartidos por colección, persistidos por la plataforma.
 * - `Dashboard.user`: quién está viendo el dashboard.
 * - `Dashboard.clipboard.write`: portapapeles a través del contenedor.
 * - `Dashboard.capture` / `window.html2canvas`: captura de un elemento a canvas, compatible con el sandbox.
 * - `Dashboard.setHeight` / `ready` / `reportError`.
 *
 * Los parámetros iniciales y el usuario van embebidos para estar disponibles de forma síncrona.
 */
export function buildPreamble(initialParams: Record<string, ParamScalar>, viewer: Viewer | null): string {
    // Escapar "<" evita que un valor de texto con "</script>" corte el bloque.
    const json = JSON.stringify(initialParams).replace(/</g, '\\u003c');
    const user = JSON.stringify(viewer).replace(/</g, '\\u003c');

    // La captura va como función serializada: corre dentro del iframe antes que cualquier script.
    return `(${installCapture.toString()})();
(function () {
  var params = ${json};
  var listeners = [];
  var explicitHeight = false;
  var lastHeight = -1;
  var pending = {};
  var seq = 0;
  var cache = {};
  var dataListeners = [];

  function post(message) { window.parent.postMessage(message, '*'); }
  function nextId() { seq += 1; return 'r' + seq + '-' + Date.now().toString(36); }
  function request(message) {
    return new Promise(function (resolve, reject) {
      var id = nextId();
      pending[id] = { resolve: resolve, reject: reject };
      message.requestId = id;
      post(message);
    });
  }

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

  function remember(collection, record) {
    if (!cache[collection]) cache[collection] = {};
    cache[collection][record.id] = { version: record.version, data: record.data };
  }
  function forget(collection, id) { if (cache[collection]) delete cache[collection][id]; }
  function notifyData(event) {
    dataListeners.forEach(function (cb) {
      try { cb(event); }
      catch (e) { post({ type: 'dashboard:error', message: String((e && e.message) || e) }); }
    });
  }

  var data = {
    // Lista completa de una colección. Devuelve [{ id, data, version, updated_at, updated_by }].
    list: function (collection) {
      return request({ type: 'data:request', op: 'list', collection: String(collection) }).then(function (result) {
        cache[collection] = {};
        result.records.forEach(function (r) { remember(collection, r); });
        return result.records;
      });
    },
    // Crea o reemplaza un registro. Si otro usuario lo cambió desde la última lectura, rechaza
    // con error.code === 'conflict' y error.record (la versión actual), y avisa por onChange.
    put: function (collection, id, value) {
      var known = cache[collection] && cache[collection][id];
      var message = { type: 'data:request', op: 'put', collection: String(collection), recordId: String(id), data: value };
      if (known) message.version = known.version;
      return request(message).then(function (result) {
        remember(collection, result.record);
        return result.record;
      }, function (error) {
        if (error && error.code === 'conflict' && error.record) {
          remember(collection, error.record);
          notifyData({ collection: collection, changed: [error.record], deleted: [], reason: 'conflict' });
        }
        throw error;
      });
    },
    remove: function (collection, id) {
      return request({ type: 'data:request', op: 'remove', collection: String(collection), recordId: String(id) }).then(function (result) {
        forget(collection, id);
        return result.deleted;
      });
    },
    // Datos iniciales del archivo: sólo se cargan si la colección está vacía.
    seed: function (collection, records) {
      return request({ type: 'data:request', op: 'seed', collection: String(collection), records: records }).then(function (result) {
        cache[collection] = {};
        result.records.forEach(function (r) { remember(collection, r); });
        return { seeded: result.seeded, records: result.records };
      });
    },
    // Reemplaza toda la colección (restaurar respaldo). Sólo super administrador.
    replace: function (collection, records) {
      return request({ type: 'data:request', op: 'replace', collection: String(collection), records: records }).then(function (result) {
        cache[collection] = {};
        result.records.forEach(function (r) { remember(collection, r); });
        return { replaced: result.replaced, records: result.records };
      });
    },
    // cb({ collection, changed: [records], deleted: [ids], reason: 'sync' | 'conflict' })
    onChange: function (cb) { if (typeof cb === 'function') dataListeners.push(cb); }
  };

  window.Dashboard = {
    params: params,
    user: ${user},
    data: data,
    // Elemento → canvas sin iframes (html2canvas no funciona en el sandbox). window.html2canvas apunta a lo mismo.
    capture: window.__ciabayCapture,
    clipboard: {
      write: function (value) {
        var message = { type: 'clipboard:write' };
        if (typeof value === 'string') message.text = value; else message.blob = value;
        return request(message);
      }
    },
    onChange: function (cb) { if (typeof cb === 'function') listeners.push(cb); },
    setParam: function (paramId, value) { post({ type: 'param:change', paramId: String(paramId), value: value }); },
    contentHeight: contentHeight,
    setHeight: function (px) {
      explicitHeight = true;
      var h = px === undefined ? contentHeight() : Number(px);
      if (!isFinite(h) || h < 0) return;
      if (h === 0) { window.requestAnimationFrame(function () { var m = contentHeight(); if (m > 0) { lastHeight = m; post({ type: 'dashboard:height', height: m }); } }); return; }
      lastHeight = Math.round(h);
      post({ type: 'dashboard:height', height: lastHeight });
    },
    ready: function () { post({ type: 'dashboard:ready' }); if (!explicitHeight) window.requestAnimationFrame(measure); },
    reportError: function (message) { post({ type: 'dashboard:error', message: String(message) }); }
  };

  window.addEventListener('message', function (event) {
    if (event.source !== window.parent) return;
    var m = event.data;
    if (!m || typeof m !== 'object') return;
    if (m.type === 'params:init' && m.params) { apply(m.params, false); return; }
    if (m.type === 'params:update' && m.params) { apply(m.params, true); return; }
    if ((m.type === 'data:response' || m.type === 'clipboard:response') && pending[m.requestId]) {
      var p = pending[m.requestId]; delete pending[m.requestId];
      if (m.ok) p.resolve(m.result); else p.reject(m.error || { code: 'clipboard', message: m.message || 'No se pudo copiar.' });
      return;
    }
    if (m.type === 'data:changes' && typeof m.collection === 'string') {
      var fresh = [];
      (m.changed || []).forEach(function (r) {
        var known = cache[m.collection] && cache[m.collection][r.id];
        if (!known || known.version !== r.version) { remember(m.collection, r); fresh.push(r); }
      });
      var gone = [];
      (m.deleted || []).forEach(function (id) { if (cache[m.collection] && cache[m.collection][id]) { forget(m.collection, id); gone.push(id); } });
      if (fresh.length || gone.length) notifyData({ collection: m.collection, changed: fresh, deleted: gone, reason: 'sync' });
    }
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
