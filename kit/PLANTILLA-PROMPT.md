# Plantilla de prompt: integración con la plataforma

Este bloque se pega **al inicio** del prompt con el que le pedís a un asistente de IA que cree tu
dashboard. Sólo explica cómo el dashboard guarda y lee sus datos en la plataforma. **No dice nada
sobre el diseño**: el aspecto, la estructura, los datos y la interfaz los definís vos en tu pedido,
como quieras.

Cómo usarlo:

1. Copiá el bloque de abajo (desde `---INICIO---` hasta `---FIN---`) y pegalo en el chat.
2. Debajo, escribí tu pedido normal: qué muestra el dashboard, qué carga el usuario, con qué estilo.
3. Publicá el archivo resultante desde **Administración → Dashboards → Cargar dashboard**. Si el
   validador marca algún problema, pegá ese mensaje al asistente y pedile que lo corrija.

---INICIO---

El dashboard que te voy a pedir a continuación se publica en una plataforma interna que lo
ejecuta dentro de un iframe y **guarda en su base de datos los datos que los usuarios cargan desde
la interfaz del propio dashboard**, compartidos entre todos los usuarios. Este bloque describe
únicamente esa integración. El diseño, la estructura, la interfaz y el estilo los defino yo en el
pedido que sigue; no impongas ni cambies nada de eso por lo que leas acá.

## 1. Qué se guarda: colecciones de registros

Todo lo que el usuario cree, edite o borre desde la interfaz (solicitudes, fichas, filas, listas)
vive en **colecciones de registros**. Un registro es un objeto JSON con un `id` propio (texto de
hasta 100 caracteres: letras, números, `-`, `_`, `.`, `:`). Elegí una granularidad razonable: un
registro por entidad que el usuario maneja como unidad (por ejemplo, una solicitud con sus filas
adentro), no un registro gigante con todo ni uno por celda.

Nunca uses `localStorage`, `sessionStorage` ni cookies: la persistencia es sólo a través de la
API de abajo.

## 2. Manifiesto

Dentro de `<head>` va exactamente un bloque que declara las colecciones:

```html
<script type="application/json" id="dashboard-manifest">
{
  "id": "identificador-en-minusculas-con-guiones",
  "version": "1.0.0",
  "title": "Título del dashboard",
  "collections": [
    { "id": "solicitudes", "label": "Solicitudes", "maxRecords": 1000 }
  ]
}
</script>
```

`collections[].id`: minúsculas, empieza con letra, letras/números/`_`/`-`, hasta 60, único.
`label`: texto para la administración. `maxRecords` (opcional, hasta 5000) y `maxBytes`
(opcional, hasta 262144) son topes por colección y por registro.

## 3. API de datos

La plataforma inyecta `window.Dashboard` **antes** de que corra cualquier script del archivo.
Todas las operaciones de datos devuelven promesas.

- `Dashboard.data.list(coleccion)` → `[{ id, data, version, updated_at, updated_by }]`.
- `Dashboard.data.put(coleccion, id, objeto)` → crea o reemplaza el registro completo. Llamala
  con un debounce corto (250 a 500 ms) por registro cada vez que el usuario cambia algo. Si otro
  usuario modificó ese registro en el medio, la promesa falla con `error.code === 'conflict'` y
  `error.record` (versión actual); no reintentes a ciegas: mostrá el registro actualizado.
- `Dashboard.data.remove(coleccion, id)` → borra.
- `Dashboard.data.seed(coleccion, [{ id, data }, ...])` → carga los datos iniciales que trae el
  archivo **sólo si la colección está vacía**. Si el pedido incluye datos de ejemplo o precargados,
  cargalos con esto al arrancar, nunca con `put` incondicional.
- `Dashboard.data.replace(coleccion, [{ id, data }, ...])` → reemplaza toda la colección
  (restaurar un respaldo). Sólo funciona para super administradores: mostrá esa acción únicamente
  si `Dashboard.user.role === 'super_admin'`.
- `Dashboard.data.onChange(cb)` → `cb({ collection, changed, deleted, reason })` cuando otros
  usuarios cambian algo (`reason: 'sync'`, llega cada ~10 s) o hubo un conflicto de edición
  (`reason: 'conflict'`). Actualizá el estado en memoria y volvé a dibujar.
- `Dashboard.user` → `{ id, name, role }` del usuario actual.
- `Dashboard.clipboard.write(textoOBlob)` → segundo intento para copiar al portapapeles si
  `navigator.clipboard` falla dentro del iframe.
- `Dashboard.setHeight()` → sin argumento, mide el contenido e informa la altura. Llamala al
  final de cada dibujo. No midas con `document.documentElement.scrollHeight`.
- `Dashboard.ready()` → llamala una vez, cuando los datos iniciales ya están cargados y dibujados.

Estructura mínima del script principal:

```js
if (typeof window.Dashboard === 'undefined') {
  // Sustituto en memoria para abrir el archivo suelto en un navegador, sin la plataforma.
  var __mem = {};
  window.Dashboard = {
    user: { id: 'local', name: 'Local', role: 'super_admin' },
    setHeight: function () {}, ready: function () {}, clipboard: { write: function () { return Promise.reject(new Error('sin plataforma')); } },
    data: {
      list: function (c) { return Promise.resolve(Object.values(__mem[c] || {})); },
      put: function (c, id, d) { (__mem[c] = __mem[c] || {})[id] = { id: id, data: d, version: 1 }; return Promise.resolve(__mem[c][id]); },
      remove: function (c, id) { if (__mem[c]) delete __mem[c][id]; return Promise.resolve(true); },
      seed: function (c, rs) { __mem[c] = {}; rs.forEach(function (r) { __mem[c][r.id] = { id: r.id, data: r.data, version: 1 }; }); return Promise.resolve({ seeded: rs.length, records: Object.values(__mem[c]) }); },
      replace: function (c, rs) { return this.seed(c, rs).then(function (r) { return { replaced: r.seeded, records: r.records }; }); },
      onChange: function () {}
    }
  };
}

const COLL = 'solicitudes';
let items = [];

async function boot() {
  let records = await Dashboard.data.list(COLL);
  if (!records.length) records = (await Dashboard.data.seed(COLL, DATOS_INICIALES.map(it => ({ id: it.id, data: it })))).records;
  items = records.map(r => r.data);
  render();
  Dashboard.setHeight();
  Dashboard.ready();
}

let timer;
function save(item) {
  clearTimeout(timer);
  timer = setTimeout(() => Dashboard.data.put(COLL, item.id, item).catch(e => { if (e.code !== 'conflict') mostrarError(e.message); }), 300);
}

Dashboard.data.onChange(ev => {
  ev.deleted.forEach(id => { items = items.filter(x => x.id !== id); });
  ev.changed.forEach(r => { const i = items.findIndex(x => x.id === r.id); if (i >= 0) items[i] = r.data; else items.push(r.data); });
  render();
});

boot();
```

## 4. Requisitos técnicos del entorno aislado

El archivo se rechaza al publicarlo si incumple alguno:

- Un único archivo `.html` autocontenido: CSS y JS dentro del mismo archivo, sin referencias a
  archivos relativos (`./app.js`, `estilos.css`, imágenes locales; las imágenes van como `data:`
  URI).
- Librerías y fuentes externas sólo desde `cdn.jsdelivr.net`, `cdnjs.cloudflare.com`,
  `unpkg.com`, `fonts.googleapis.com` y `fonts.gstatic.com`, por `https`.
- Prohibido `localStorage`, `sessionStorage`, `document.cookie`, `XMLHttpRequest`, `WebSocket`,
  `navigator.sendBeacon`. Prohibido `fetch()` salvo a una URL literal de los hosts de arriba.
- Sin `height: 100vh` ni `min-height: 100vh` en `html` o `body`: el alto lo fija la plataforma a
  partir de `Dashboard.setHeight()`. Los elementos `position: fixed` quedan fijos respecto del
  iframe, no de la ventana.
- `alert`, `confirm`, `prompt`, descargas iniciadas por el usuario y `navigator.clipboard` están
  permitidos.

## Entrega

El archivo completo en un solo bloque de código, y debajo la lista de colecciones declaradas con
la forma de cada registro. Antes de entregar, verificá las reglas de arriba una por una.

---FIN---

Después del bloque, escribí tu pedido con total libertad. Por ejemplo:

> Quiero un formulario de solicitudes de traslado de máquinas para eventos: cabecera con evento,
> sucursal y fechas, y una tabla de máquinas con código, chasis, categoría, descripción, origen,
> destino, flete y estado. Varias solicitudes en pestañas. Estilo institucional azul y verde,
> con logo. Botones para copiar un reporte de texto y una imagen para WhatsApp.
