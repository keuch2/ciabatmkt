# Especificación del dashboard — formato del manifiesto y API `Dashboard`

Un dashboard es **un solo archivo HTML autocontenido** con su propia interfaz. La plataforma lo
ejecuta dentro de un iframe aislado y le da persistencia: los datos que los usuarios cargan desde
esa interfaz se guardan en la base de datos y los ven todos. Este documento define el contrato
exacto. `ejemplos/traslado-maquinas-eventos.html` es un dashboard real adaptado a él;
`dashboard-referencia.html` muestra además los parámetros escalares opcionales.

Hay dos mecanismos de persistencia, y un dashboard puede usar uno, los dos o ninguno:

| Mecanismo | Para qué | Quién lo ve | Cómo se edita |
|---|---|---|---|
| **Colecciones de registros** (`Dashboard.data`) | Datos que se cargan: solicitudes, filas, fichas, listas | Todos los usuarios | Desde la interfaz propia del dashboard |
| **Parámetros escalares** (`Dashboard.params`) | Ajustes sueltos: una meta, un umbral, un color | Cada usuario ve su valor o el base | Con `Dashboard.setParam`, o desde la administración (valores base) |

Para un dashboard como el ejemplo del cliente, alcanza con las colecciones.

## 1. Estructura del archivo

```html
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Mi dashboard</title>

  <script type="application/json" id="dashboard-manifest">
  { ...manifiesto... }
  </script>

  <style> /* estilos en línea */ </style>
</head>
<body>
  ...marcado...
  <script> /* lógica en línea, usa window.Dashboard */ </script>
</body>
</html>
```

Reglas de forma:

- Exactamente **un** bloque `<script type="application/json" id="dashboard-manifest">`.
- Sin archivos relativos: nada de `src="./app.js"` ni `href="estilos.css"`.
- Scripts externos sólo desde los CDN autorizados, por `https`: `cdn.jsdelivr.net`,
  `cdnjs.cloudflare.com`, `unpkg.com` (el administrador puede ampliar la lista).
- Sin `localStorage`, `sessionStorage`, `document.cookie`, `XMLHttpRequest`, `WebSocket`,
  `sendBeacon`. `fetch()` sólo con URL literal a un CDN autorizado.
- Los datos van embebidos en el archivo o se cargan desde un CDN autorizado.
- Tamaño máximo por defecto: 2 MB.

## 2. El manifiesto

```json
{
  "id": "traslado-maquinas-eventos",
  "version": "1.0.0",
  "title": "Traslado de Máquinas",
  "collections": [
    { "id": "solicitudes", "label": "Solicitudes de traslado", "maxRecords": 1000 }
  ],
  "params": [ ... ]
}
```

| Campo | Tipo | Regla |
|---|---|---|
| `id` | texto | Identidad estable del dashboard. Minúsculas, números y guiones (`^[a-z0-9][a-z0-9-]*$`). Publicar otro archivo con el mismo `id` es **actualizar** ese dashboard. |
| `version` | texto | Libre, se muestra a los usuarios. Subilo en cada actualización. |
| `title` | texto | Nombre visible. |
| `collections` | arreglo, opcional | Colecciones de registros compartidos que el dashboard lee y escribe con `Dashboard.data`. |
| `params` | arreglo, opcional | Parámetros escalares. |

### 2.0 Una colección

| Campo | Regla |
|---|---|
| `id` | Minúsculas, empieza con letra; letras, números, `_` y `-`, hasta 60. Único en el dashboard. Es el nombre que se pasa a `Dashboard.data`. |
| `label` | Texto para la administración. |
| `maxRecords` | Opcional. Tope de registros; por defecto y como máximo 5000. |
| `maxBytes` | Opcional. Tope por registro en bytes de JSON; por defecto y como máximo 262144 (256 KB). |

Escribir en una colección que el manifiesto no declara se rechaza con 422. Además, el validador de
carga (regla 11) revisa el código: si encuentra `Dashboard.data.<operación>('nombre', …)`, o una
constante simple con ese nombre, y la colección no está declarada, rechaza el archivo indicando la
línea.

### 2.1 Un parámetro

Campos comunes a todos los tipos:

| Campo | Regla |
|---|---|
| `id` | Único dentro del dashboard. Empieza con letra; letras, números, `_` y `-` (`^[A-Za-z][A-Za-z0-9_-]*$`). Es la clave en `Dashboard.params`. |
| `label` | Texto visible en el panel lateral. |
| `type` | Uno de los siete tipos de abajo. Ningún otro es válido. |
| `default` | Valor inicial. Debe cumplir el tipo y sus restricciones. |

### 2.2 Tipos

| Tipo | Requeridos | Opcionales | Valor que recibe el dashboard |
|---|---|---|---|
| `number` | `default` | `min`, `max`, `step`, `unit` | número |
| `text` | `default` | `maxLength` | texto |
| `boolean` | `default` | — | `true` / `false` |
| `select` | `default`, `options` | — | el `value` de la opción elegida (texto o número, tal como está en `options`) |
| `range` | `default`, `min`, `max` | `step`, `unit` | número |
| `date` | `default` (`AAAA-MM-DD`) | `min`, `max` (`AAAA-MM-DD`) | texto `AAAA-MM-DD` |
| `color` | `default` (`#RRGGBB`) | — | texto `#rrggbb` en minúsculas |

Notas:

- `min`/`max`/`step` de `number` y `range` son numéricos; `min` < `max`; `step` > 0.
- `options` de `select`: arreglo no vacío de `{ "value": ..., "label": "..." }`. Los `value` no se
  repiten. El `default` debe ser uno de ellos, con el mismo tipo (`"q1"` no es lo mismo que `1`).
- `maxLength` de `text`: entero mayor que cero.
- `unit` es sólo decorativo (se muestra junto al control).
- `step` es una ayuda para el control; la plataforma no rechaza valores que no caen en el paso.

### 2.3 Ejemplo completo

```json
{
  "id": "ventas-sucursal",
  "version": "1.0.0",
  "title": "Ventas por sucursal",
  "params": [
    { "id": "meta_ventas", "label": "Meta de ventas mensual", "type": "number",
      "default": 450000000, "min": 0, "max": 2000000000, "step": 10000000, "unit": "Gs." },
    { "id": "nombre_reporte", "label": "Título del reporte", "type": "text",
      "default": "Ventas por sucursal", "maxLength": 60 },
    { "id": "incluir_interior", "label": "Incluir sucursales del interior", "type": "boolean",
      "default": true },
    { "id": "trimestre", "label": "Trimestre", "type": "select", "default": "q1",
      "options": [ { "value": "q1", "label": "Primer trimestre" }, { "value": "q2", "label": "Segundo trimestre" } ] },
    { "id": "descuento", "label": "Descuento promocional", "type": "range",
      "default": 10, "min": 0, "max": 50, "step": 5, "unit": "%" },
    { "id": "fecha_corte", "label": "Fecha de corte", "type": "date",
      "default": "2026-06-30", "min": "2026-01-01", "max": "2026-12-31" },
    { "id": "color_principal", "label": "Color de las barras", "type": "color", "default": "#1f4e79" }
  ]
}
```

## 3. La API `Dashboard`

La plataforma inyecta el objeto global `window.Dashboard` **antes** de que corra cualquier script
del dashboard. `Dashboard.params` y `Dashboard.user` están disponibles de forma síncrona; las
operaciones de `Dashboard.data` devuelven promesas.

### `Dashboard.data` — registros compartidos

Un registro es un objeto JSON con un id propio (texto de hasta 100 caracteres: letras, números,
`-`, `_`, `.`, `:`). La plataforma guarda el registro tal cual, con versión, autor y fecha.

```js
// Lista completa: [{ id, data, version, updated_at, updated_by: { id, name } }]
const registros = await Dashboard.data.list('solicitudes');

// Crear o reemplazar un registro por completo
await Dashboard.data.put('solicitudes', solicitud.id, solicitud);

// Borrar
await Dashboard.data.remove('solicitudes', id);

// Datos iniciales que trae el archivo: sólo se cargan si la colección está vacía
const { seeded, records } = await Dashboard.data.seed('solicitudes', items.map(it => ({ id: it.id, data: it })));

// Reemplazar toda la colección (restaurar un respaldo). Sólo super administrador.
const { replaced, records } = await Dashboard.data.replace('solicitudes', [{ id, data }, ...]);

// Cambios hechos por otros usuarios (llegan solos cada ~10 s) o conflicto de edición
Dashboard.data.onChange(({ collection, changed, deleted, reason }) => {
  // changed: registros nuevos o modificados; deleted: ids borrados; reason: 'sync' | 'conflict'
});
```

Reglas:

- **Concurrencia.** `put` envía la versión que el dashboard leyó por última vez. Si otro usuario
  cambió ese registro en el medio, la escritura se rechaza: la promesa falla con
  `error.code === 'conflict'`, `error.record` trae la versión actual y `onChange` la entrega con
  `reason: 'conflict'`. Registros distintos nunca chocan entre sí.
- **Guardar el registro completo.** `put` reemplaza el registro; no hay actualizaciones parciales.
  Con un debounce corto (250 a 500 ms) por registro alcanza para escribir mientras el usuario tipea.
- **Un `put` sin cambios reales** no genera versión nueva ni historial.
- Los errores de validación (`error.code === 'invalid'`) traen un mensaje legible: colección no
  declarada, id inválido, registro demasiado grande, colección llena.
- Todo cambio queda en el historial con el usuario que lo hizo; el super administrador lo ve y
  puede exportar cada colección como JSON.

### `Dashboard.user`

`{ id, name, role }` del usuario que está viendo el dashboard. `role` es `"super_admin"` o
`"user"`. Sirve para ocultar acciones administrativas, por ejemplo restaurar un respaldo.

### `Dashboard.capture(elemento, opciones)` y `html2canvas`

Convierte un elemento del dashboard en un `<canvas>`, para exportar a imagen o a PDF:

```js
const canvas = await Dashboard.capture(document.getElementById('reporte'), { scale: 2, backgroundColor: '#ffffff' });
const png = canvas.toDataURL('image/png');          // imagen
pdf.addImage(png, 'PNG', 0, 0, anchoMm, altoMm);    // o a un PDF con jsPDF
```

La librería **html2canvas no puede funcionar dentro del iframe aislado** (necesita un iframe hijo
del mismo origen y el sandbox lo impide). Por eso la plataforma define `window.html2canvas` con
una implementación propia y compatible: `html2canvas(elemento, { scale, backgroundColor, width,
height })` devuelve una promesa con el canvas, igual que la original. Un dashboard que ya usa
html2canvas funciona sin cambios, aunque cargue el script del CDN: esa asignación se ignora.

Detalles: captura estilos computados, pseudo-elementos, valores de formularios, canvas e imágenes
`data:` o de los CDN autorizados, y embebe las fuentes web latinas. El elemento debe estar visible
(con tamaño) al capturarlo. En Safari el canvas resultante puede quedar bloqueado para exportar.

### `Dashboard.clipboard.write(valor)`

Copia un texto o un `Blob` (por ejemplo una imagen PNG) al portapapeles **a través de la
plataforma**. Usalo como segundo intento cuando `navigator.clipboard` falle dentro del iframe.
Devuelve una promesa; debe llamarse en respuesta a un clic del usuario.

### `Dashboard.params` (parámetros escalares, opcional)

Objeto plano con los valores efectivos, clave por `id` de parámetro. La plataforma lo mantiene
actualizado: después de un cambio, `Dashboard.params.meta_ventas` ya tiene el valor nuevo.

```js
var meta = Dashboard.params.meta_ventas;   // 450000000
```

Nunca lo reemplaces ni le agregues claves: leelo.

### `Dashboard.onChange(callback)`

Registra una función que se llama cada vez que cambia uno o más parámetros (desde el panel
lateral o desde el propio dashboard). Recibe `(params, changedIds)`: el objeto completo ya
actualizado y la lista de ids que cambiaron.

```js
Dashboard.onChange(function (params, changed) {
  render();   // lo más simple: volver a dibujar todo leyendo Dashboard.params
});
```

Podés registrar varias. Si una lanza una excepción, la plataforma la muestra al usuario y sigue
con las demás.

### `Dashboard.setHeight(px)`

Informa al contenedor la altura del contenido, para que el iframe se ajuste sin barras de
desplazamiento. Llamala después de cada render.

- `Dashboard.setHeight()` **sin argumento** mide el contenido por vos (recomendado).
- `Dashboard.setHeight(820)` fija un alto explícito.
- `Dashboard.contentHeight()` devuelve la medición sin enviarla.

Si nunca llamás a `setHeight`, la plataforma mide sola con un `ResizeObserver`. No uses
`document.documentElement.scrollHeight` para medir: nunca baja del alto del iframe y genera
un bucle.

### `Dashboard.ready()`

Avisa que el dashboard terminó de inicializar. Hasta ese momento el usuario ve un indicador de
carga sobre el iframe. Si no la llamás en 8 segundos, el iframe se muestra igual con un aviso.

### `Dashboard.setParam(id, value)`

Pide a la plataforma cambiar un parámetro desde el dashboard (por ejemplo, al hacer clic en un
gráfico). El valor pasa por la misma validación y se guarda como override del usuario. El
dashboard recibe el cambio por `onChange`, igual que si viniera del panel.

### `Dashboard.reportError(message)`

Muestra un error al usuario en el contenedor. Los errores no capturados (`window.onerror`) y las
promesas rechazadas se reportan solos.

## 4. Flujo mínimo con una colección

```js
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
function save(item) {                       // llamar en cada cambio del usuario
  clearTimeout(timer);
  timer = setTimeout(() => Dashboard.data.put(COLL, item.id, item).catch(e => { if (e.code !== 'conflict') mostrarError(e.message); }), 300);
}

Dashboard.data.onChange(ev => {             // cambios de otros usuarios
  ev.deleted.forEach(id => { items = items.filter(x => x.id !== id); });
  ev.changed.forEach(r => { const i = items.findIndex(x => x.id === r.id); if (i >= 0) items[i] = r.data; else items.push(r.data); });
  render();
});

boot();
```

Si el archivo se abre suelto en un navegador no existe `window.Dashboard`: conviene un pequeño
sustituto en memoria al principio del script (ver el ejemplo del kit).

## 5. Aislamiento

El dashboard corre en `<iframe sandbox="allow-scripts">` sin `allow-same-origin`, con esta
política de seguridad de contenido:

```
default-src 'none';
script-src 'unsafe-inline' 'unsafe-eval' https://<cdn autorizados>;
style-src 'unsafe-inline' https://<cdn autorizados>;
img-src data: blob: https://<cdn autorizados>;
font-src data: https://<cdn autorizados>;
connect-src https://<cdn autorizados>;
worker-src blob:
```

El sandbox permite scripts, diálogos (`alert`, `confirm`, `prompt`), descargas iniciadas por el
usuario y ventanas nuevas; delega el permiso de portapapeles. No hay cookies ni almacenamiento del
navegador y no se puede navegar al padre. Todo lo que el dashboard necesita persistir pasa por
`Dashboard.data` o por un parámetro.

## 6. Protocolo de mensajes (referencia interna)

El objeto `Dashboard` envuelve estos mensajes `postMessage`. No hace falta usarlos directamente.

Del iframe al contenedor:

```js
{ type: "dashboard:ready" }
{ type: "dashboard:height", height: 840 }
{ type: "param:change", paramId: "meta_ventas", value: 500000000 }
{ type: "dashboard:error", message: "..." }
{ type: "data:request", requestId, op: "list"|"put"|"remove"|"seed"|"replace", collection, recordId?, data?, version?, records? }
{ type: "clipboard:write", requestId, text? , blob? }
```

Del contenedor al iframe:

```js
{ type: "params:init", params: { meta_ventas: 500000000, ... } }
{ type: "params:update", params: { meta_ventas: 520000000 } }
{ type: "data:response", requestId, ok: true, result } | { type: "data:response", requestId, ok: false, error: { code, message, record? } }
{ type: "data:changes", collection, changed: [records], deleted: [ids] }
{ type: "clipboard:response", requestId, ok, message? }
```

El contenedor ejecuta cada petición de datos contra la API con la sesión del usuario: el iframe
nunca ve cookies ni credenciales.

## 7. Cómo se resuelve un valor

Al abrir un dashboard, cada parámetro toma el primero que exista de:

1. el valor propio del usuario,
2. el valor base definido por el super administrador,
3. el `default` del manifiesto.

Si un valor guardado ya no cumple el manifiesto vigente (por ejemplo, cambió el rango o el tipo en
una versión nueva), se ignora y se marca como obsoleto hasta que el usuario guarde uno nuevo.

## 8. Versionar un dashboard

Para publicar una versión nueva, subí un archivo con el **mismo `id`** y una `version` distinta.
La plataforma muestra un resumen antes de confirmar: parámetros agregados, eliminados y con tipo
cambiado.

- Agregar un parámetro: los usuarios lo ven con su `default`.
- Eliminar un parámetro: los valores guardados quedan huérfanos (no molestan; el administrador
  puede limpiarlos).
- Cambiar el tipo o el rango de un parámetro: los valores guardados que ya no cumplen se ignoran.
- Cambiar el `id` de un parámetro equivale a eliminarlo y crear otro.
- Quitar una colección del manifiesto no borra sus registros, pero el dashboard deja de poder
  leerlos. La administración los sigue mostrando y exportando.
