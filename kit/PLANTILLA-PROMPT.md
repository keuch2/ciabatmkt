# Plantilla de prompt para generar dashboards con IA

Copiá todo el bloque de abajo (desde `---INICIO---` hasta `---FIN---`) y pegalo en el chat antes
de describir el dashboard que querés. Después del bloque, describí en tus palabras qué debe
mostrar, qué datos usa y qué querés poder ajustar.

Cuando el archivo esté listo, publicalo desde **Administración → Dashboards → Cargar dashboard**.
La plataforma valida las reglas antes de guardar y te muestra la línea exacta de cualquier problema;
pegá ese mensaje al asistente y pedile que lo corrija.

---INICIO---

Necesito que generes un **dashboard HTML autocontenido** para una plataforma interna que lo
ejecuta dentro de un iframe aislado. Respetá estrictamente estas reglas; el archivo se rechaza si
incumple alguna.

## Formato del archivo

- Un único archivo `.html` con `<!DOCTYPE html>`, `<html lang="es">`, `<head>` y `<body>`.
- Todo el CSS en `<style>` y todo el JS en `<script>` dentro del mismo archivo. Prohibido
  referenciar archivos relativos (`./app.js`, `estilos.css`, imágenes locales).
- Librerías externas sólo por `<script src="https://...">` desde estos hosts:
  `cdn.jsdelivr.net`, `cdnjs.cloudflare.com`, `unpkg.com`. Preferí no usar ninguna; si usás una
  (por ejemplo Chart.js), pinneá la versión.
- Los datos van embebidos en el archivo como constantes JS.
- Prohibido usar `localStorage`, `sessionStorage`, `document.cookie`, `XMLHttpRequest`,
  `WebSocket`, `navigator.sendBeacon`. Prohibido `fetch()` salvo a una URL literal de los hosts
  de arriba.
- Idioma de la interfaz: español (Paraguay). Formato de números con `toLocaleString('es-PY')`.
  Moneda: guaraníes (`Gs.`).

## Manifiesto obligatorio

Dentro de `<head>` va exactamente un bloque:

```html
<script type="application/json" id="dashboard-manifest">
{
  "id": "identificador-en-minusculas-con-guiones",
  "version": "1.0.0",
  "title": "Título visible",
  "params": [ ... ]
}
</script>
```

Cada parámetro tiene `id` (empieza con letra; letras, números, `_`), `label`, `type` y `default`.
Tipos permitidos y sus campos (ningún otro tipo es válido):

| type | requeridos | opcionales |
|---|---|---|
| `number` | `default` | `min`, `max`, `step`, `unit` |
| `text` | `default` | `maxLength` |
| `boolean` | `default` | — |
| `select` | `default`, `options` = `[{ "value", "label" }]` no vacío; `default` es uno de los `value` | — |
| `range` | `default`, `min`, `max` | `step`, `unit` |
| `date` | `default` en `AAAA-MM-DD` | `min`, `max` en `AAAA-MM-DD` |
| `color` | `default` en `#RRGGBB` | — |

Cada `default` debe cumplir su tipo y su rango. Los `id` no se repiten. Declará como parámetro
**todo lo que un usuario querría ajustar**: metas, umbrales, filtros, períodos, colores, títulos.

## API que te da la plataforma

Antes de tu script existe `window.Dashboard`:

- `Dashboard.params` — objeto con los valores actuales, clave por `id` de parámetro. Leelo, no lo
  modifiques.
- `Dashboard.onChange(fn)` — `fn(params, changedIds)` se llama cuando cambia algún parámetro.
- `Dashboard.setHeight()` — sin argumento mide el contenido e informa la altura. Llamala al final
  de cada render. No midas con `document.documentElement.scrollHeight`.
- `Dashboard.ready()` — llamala una vez al terminar de inicializar.
- `Dashboard.setParam(id, value)` — opcional, para cambiar un parámetro desde el propio dashboard.

Estructura obligatoria del script principal:

```js
if (typeof window.Dashboard === 'undefined') {
  // Modo suelto para probar el archivo abierto directamente en un navegador.
  var m = JSON.parse(document.getElementById('dashboard-manifest').textContent);
  var d = {}; m.params.forEach(function (p) { d[p.id] = p.default; });
  window.Dashboard = { params: d, onChange: function () {}, setHeight: function () {}, ready: function () {}, setParam: function () {} };
}

function render() {
  var p = Dashboard.params;
  // ...dibujar todo leyendo p...
  Dashboard.setHeight();
}

Dashboard.onChange(render);
render();
Dashboard.ready();
```

## Estilo

- Sobrio y funcional: fondo blanco, tipografía del sistema, densidad alta, sin decoración.
- `body { margin: 0; padding: 16px; }`. Sin `height: 100vh` ni `min-height: 100vh` en `html`
  o `body` (rompe la medición de altura).
- Responsive: el ancho del iframe lo decide la plataforma.

## Entrega

Devolvé el archivo completo en un solo bloque de código, listo para guardar como `.html`.
Antes de entregarlo, verificá mentalmente la lista de reglas de arriba, una por una.

---FIN---

Ahora describí tu dashboard. Ejemplo:

> Quiero un dashboard de cumplimiento de ventas por sucursal. Datos: cinco sucursales con ventas
> mensuales por trimestre (inventá cifras realistas en guaraníes). Parámetros: meta mensual
> (número), trimestre (select), incluir sucursales del interior (boolean), descuento aplicado
> (range 0–50 %), fecha de corte (date), color de las barras (color) y título del reporte (text).
> Mostrá tres indicadores arriba y una tabla con barras de avance por sucursal.
