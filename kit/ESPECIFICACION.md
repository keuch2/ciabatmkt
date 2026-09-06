# Especificación del dashboard — formato del manifiesto y API `Dashboard`

Un dashboard es **un solo archivo HTML autocontenido**. La plataforma lo ejecuta dentro de un
iframe aislado, le entrega los valores de sus parámetros y guarda lo que cada usuario modifica.
Este documento define el contrato exacto. El archivo `dashboard-referencia.html` lo implementa
completo y comentado.

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
  "id": "ventas-trimestral",
  "version": "1.0.0",
  "title": "Ventas Trimestral",
  "params": [ ... ]
}
```

| Campo | Tipo | Regla |
|---|---|---|
| `id` | texto | Identidad estable del dashboard. Minúsculas, números y guiones (`^[a-z0-9][a-z0-9-]*$`). Publicar otro archivo con el mismo `id` es **actualizar** ese dashboard. |
| `version` | texto | Libre, se muestra a los usuarios. Subilo en cada actualización. |
| `title` | texto | Nombre visible. |
| `params` | arreglo | Parámetros editables, en el orden en que se mostrarán. Puede estar vacío. |

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
del dashboard. Está disponible de forma síncrona: podés leer `Dashboard.params` en la primera línea
de tu script.

### `Dashboard.params`

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

## 4. Flujo mínimo

```js
function render() {
  var p = Dashboard.params;
  // ...dibujar con p...
  Dashboard.setHeight();
}
Dashboard.onChange(render);
render();
Dashboard.ready();
```

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

Consecuencias prácticas: no hay cookies ni almacenamiento del navegador, no se puede navegar al
padre, no se puede abrir formularios ni ventanas, y sólo se puede hablar con la plataforma por el
objeto `Dashboard`. Todo lo que el dashboard necesita persistir es un parámetro.

## 6. Protocolo de mensajes (referencia interna)

El objeto `Dashboard` envuelve estos mensajes `postMessage`. No hace falta usarlos directamente.

Del iframe al contenedor:

```js
{ type: "dashboard:ready" }
{ type: "dashboard:height", height: 840 }
{ type: "param:change", paramId: "meta_ventas", value: 500000000 }
{ type: "dashboard:error", message: "..." }
```

Del contenedor al iframe:

```js
{ type: "params:init", params: { meta_ventas: 500000000, ... } }
{ type: "params:update", params: { meta_ventas: 520000000 } }
```

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
