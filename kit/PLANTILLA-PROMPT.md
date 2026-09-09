# Plantilla de prompt: integración con la plataforma

Este bloque se pega **al inicio** del prompt con el que le pedís a un asistente de IA que cree tu
dashboard. Sólo explica cómo el dashboard lee los valores editables y cómo la plataforma los
guarda en su base de datos. **No dice nada sobre el diseño**: el aspecto, la estructura, los
datos y las gráficas los definís vos en tu pedido, como quieras.

Cómo usarlo:

1. Copiá el bloque de abajo (desde `---INICIO---` hasta `---FIN---`) y pegalo en el chat.
2. Debajo, escribí tu pedido normal: qué muestra el dashboard, con qué datos, con qué estilo.
3. Publicá el archivo resultante desde **Administración → Dashboards → Cargar dashboard**. Si el
   validador marca algún problema, pegá ese mensaje al asistente y pedile que lo corrija.

---INICIO---

El dashboard que te voy a pedir a continuación se publica en una plataforma interna que lo
ejecuta dentro de un iframe y **guarda en su base de datos los valores que cada usuario ajusta**.
Este bloque describe únicamente esa integración. El diseño, la estructura, los datos y el estilo
los defino yo en el pedido que sigue; no impongas ni cambies nada de eso por lo que leas acá.

## 1. Qué se guarda: los parámetros

Todo valor que un usuario quiera ajustar sin editar el archivo (una meta, un umbral, un
porcentaje, un período, un filtro, una fecha de corte, un título, un color, un interruptor de
mostrar/ocultar) es un **parámetro**. Los datos de fondo (tablas, series) no lo son: van embebidos
en el archivo. La plataforma genera sola los controles de edición para cada parámetro y los
muestra fuera del dashboard; **no agregues controles propios** salvo que yo te los pida.

## 2. Manifiesto

Dentro de `<head>` va exactamente un bloque que declara los parámetros:

```html
<script type="application/json" id="dashboard-manifest">
{
  "id": "identificador-en-minusculas-con-guiones",
  "version": "1.0.0",
  "title": "Título del dashboard",
  "params": [ ... ]
}
</script>
```

Cada parámetro tiene `id` (empieza con letra; letras, números, `_`), `label` (texto que ve el
usuario), `type` y `default` (valor inicial). Tipos permitidos y sus campos; **ningún otro tipo es
válido**:

| type | requeridos | opcionales |
|---|---|---|
| `number` | `default` | `min`, `max`, `step`, `unit` |
| `text` | `default` | `maxLength` |
| `boolean` | `default` | — |
| `select` | `default`, `options` = `[{ "value", "label" }]` no vacío; `default` es uno de los `value` | — |
| `range` | `default`, `min`, `max` | `step`, `unit` |
| `date` | `default` en `AAAA-MM-DD` | `min`, `max` en `AAAA-MM-DD` |
| `color` | `default` en `#RRGGBB` | — |

Cada `default` debe cumplir su tipo y su rango. Los `id` no se repiten. Poné `min`/`max`
razonables en los numéricos.

## 3. Cómo se leen y se guardan los valores

La plataforma inyecta `window.Dashboard` **antes** de que corra cualquier script del archivo:

- `Dashboard.params` — objeto con los valores actuales, clave por `id` de parámetro. Todo lo que
  dependa de un parámetro se lee de acá en el momento de dibujar, nunca de una constante.
- `Dashboard.onChange(fn)` — `fn(params, changedIds)` se llama cuando el usuario cambia un
  parámetro. Volvé a dibujar lo que corresponda leyendo `Dashboard.params`.
- `Dashboard.setParam(id, value)` — si el dashboard tiene algún control propio para un
  parámetro, en su evento de cambio llamá a esto: la plataforma valida, guarda y avisa por
  `onChange`. Nunca guardes valores por tu cuenta.
- `Dashboard.setHeight()` — sin argumento, mide el contenido e informa la altura al contenedor.
  Llamala al final de cada dibujo. No midas con `document.documentElement.scrollHeight`.
- `Dashboard.ready()` — llamala una vez, al terminar la inicialización.

Estructura mínima del script principal:

```js
if (typeof window.Dashboard === 'undefined') {
  // Permite abrir el archivo suelto en un navegador, sin la plataforma.
  var __m = JSON.parse(document.getElementById('dashboard-manifest').textContent);
  var __d = {}; __m.params.forEach(function (p) { __d[p.id] = p.default; });
  window.Dashboard = { params: __d, onChange: function () {}, setHeight: function () {}, ready: function () {}, setParam: function () {} };
}

function render() {
  var p = Dashboard.params;
  // ...dibujar leyendo p...
  Dashboard.setHeight();
}

Dashboard.onChange(render);
render();
Dashboard.ready();
```

## 4. Requisitos técnicos del entorno aislado

El archivo se rechaza al publicarlo si incumple alguno:

- Un único archivo `.html` autocontenido: CSS y JS dentro del mismo archivo, sin referencias a
  archivos relativos (`./app.js`, `estilos.css`, imágenes locales; las imágenes van como `data:`
  URI).
- Librerías y fuentes externas sólo desde `cdn.jsdelivr.net`, `cdnjs.cloudflare.com` o
  `unpkg.com`, por `https`.
- Prohibido `localStorage`, `sessionStorage`, `document.cookie`, `XMLHttpRequest`, `WebSocket`,
  `navigator.sendBeacon`. Prohibido `fetch()` salvo a una URL literal de los hosts de arriba. Lo
  que haga falta persistir es un parámetro.
- Sin `height: 100vh` ni `min-height: 100vh` en `html` o `body`: el alto lo fija la plataforma a
  partir de `Dashboard.setHeight()`.

## Entrega

El archivo completo en un solo bloque de código, y debajo la lista de parámetros declarados
(id, tipo, default). Antes de entregar, verificá las reglas de arriba una por una.

---FIN---

Después del bloque, escribí tu pedido con total libertad. Por ejemplo:

> Quiero un dashboard de cumplimiento de ventas por sucursal, estilo minimalista con fondo
> oscuro y gráficas de barras horizontales. Datos: cinco sucursales con ventas por trimestre.
> Debe poder ajustarse la meta mensual, el trimestre, si se incluyen sucursales del interior y
> el color de las barras.
