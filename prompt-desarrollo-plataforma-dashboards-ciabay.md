# Prompt de Desarrollo — Plataforma de Dashboards Parametrizables

**Cliente:** Ciabay
**Firma:** Mister Co.
**Plazo:** 4 semanas
**Uso:** pegar este archivo completo como contexto inicial en Claude Code al abrir el repositorio vacío.

---

## 1. Qué vas a construir

Una plataforma web donde un super administrador publica dashboards interactivos (archivos HTML autocontenidos con JS), y usuarios autenticados modifican los valores de los widgets de esos dashboards. Cada valor modificado se guarda en base de datos, es personal de cada usuario, y queda registrado quién lo cambió y cuándo.

El punto central de la arquitectura: **el esquema de base de datos no cambia nunca al agregar dashboards nuevos**. Un dashboard con 3 parámetros y otro con 40 usan exactamente la misma estructura de tablas. Esto se logra porque cada dashboard declara sus propios parámetros en un manifiesto, y ese manifiesto es dato, no estructura.

No estás construyendo un editor de dashboards. Los dashboards vienen hechos desde afuera. Estás construyendo el contenedor que los ejecuta, el motor que interpreta sus parámetros y la capa que persiste los valores.

---

## 2. Stack

- **Backend:** Laravel 11, PHP 8.3
- **Frontend:** React 18 con Vite, TypeScript
- **Base de datos:** PostgreSQL 16
- **Autenticación:** Laravel Sanctum, sesiones con cookie
- **Estilos:** Tailwind CSS

No agregues dependencias fuera de esta lista sin justificarlo primero.

---

## 3. Modelo de datos

### `users`
Estándar de Laravel, más una columna `role` de tipo enum con valores `super_admin` y `user`.

### `dashboards`

```sql
create table dashboards (
  id           uuid primary key default gen_random_uuid(),
  slug         text unique not null,
  title        text not null,
  version      text not null,
  html         text not null,
  manifest     jsonb not null,
  is_published boolean not null default false,
  created_by   uuid not null references users(id),
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);
```

`html` guarda el archivo completo tal como fue cargado. `manifest` guarda el bloque JSON extraído del archivo, y es la fuente de verdad para validar escrituras.

### `param_values`

```sql
create table param_values (
  id            uuid primary key default gen_random_uuid(),
  dashboard_id  uuid not null references dashboards(id) on delete cascade,
  param_id      text not null,
  user_id       uuid references users(id) on delete cascade,
  value         jsonb not null,
  updated_by    uuid not null references users(id),
  updated_at    timestamptz not null default now()
);

create unique index param_values_unique on param_values (
  dashboard_id,
  param_id,
  coalesce(user_id, '00000000-0000-0000-0000-000000000000'::uuid)
);

create index param_values_lookup on param_values (dashboard_id, user_id);
```

`user_id` nulo significa **valor base**, definido por el super administrador y visible para todos. `user_id` con valor significa **override personal** de ese usuario.

`value` guarda el escalar crudo en JSONB, sin objeto envolvente. Un número es `500000000`, un booleano es `true`, un texto es `"Sucursal Centro"`. JSONB admite escalares en la raíz y preserva el tipo al leer.

### `param_value_history`

```sql
create table param_value_history (
  id            uuid primary key default gen_random_uuid(),
  dashboard_id  uuid not null references dashboards(id) on delete cascade,
  param_id      text not null,
  user_id       uuid references users(id) on delete set null,
  old_value     jsonb,
  new_value     jsonb not null,
  changed_by    uuid not null references users(id),
  changed_at    timestamptz not null default now()
);

create index param_history_lookup on param_value_history (dashboard_id, changed_at desc);
```

Se llena por trigger en `after insert or update` sobre `param_values`. No la escribas desde la aplicación.

---

## 4. Resolución de valores

Al abrir un dashboard, el valor efectivo de cada parámetro se resuelve en tres niveles, en este orden:

1. Override personal del usuario, si existe fila con su `user_id`
2. Valor base, si existe fila con `user_id` nulo
3. `default` declarado en el manifiesto

**Iterá siempre sobre los parámetros del manifiesto, nunca sobre las filas de `param_values`.** Si iterás sobre las filas, un parámetro que nadie tocó nunca no aparece. El manifiesto define qué existe; la tabla solo dice qué fue modificado.

```sql
select
  m.param->>'id' as param_id,
  coalesce(u.value, b.value) as stored_value,
  m.param->'default' as default_value
from dashboards d
cross join lateral jsonb_array_elements(d.manifest->'params') as m(param)
left join param_values b
  on b.dashboard_id = d.id
 and b.param_id = m.param->>'id'
 and b.user_id is null
left join param_values u
  on u.dashboard_id = d.id
 and u.param_id = m.param->>'id'
 and u.user_id = :user_id
where d.id = :dashboard_id;
```

**Valores huérfanos:** si el super administrador publica una versión nueva que elimina un parámetro, quedan filas con `param_id` inexistente. No las borres automáticamente ni falles por ellas. La consulta de arriba las ignora sola. Agregá un comando artisan de limpieza manual.

---

## 5. Contrato del dashboard

Cada dashboard es un HTML autocontenido que declara sus parámetros editables en un bloque JSON:

```html
<script type="application/json" id="dashboard-manifest">
{
  "id": "ventas-trimestral",
  "version": "1.0.0",
  "title": "Ventas Trimestral",
  "params": [
    {
      "id": "meta_ventas",
      "label": "Meta de ventas mensual",
      "type": "number",
      "default": 450000000,
      "min": 0,
      "max": 2000000000,
      "step": 10000000,
      "unit": "Gs."
    },
    {
      "id": "incluir_interior",
      "label": "Incluir sucursales del interior",
      "type": "boolean",
      "default": true
    },
    {
      "id": "trimestre",
      "label": "Trimestre",
      "type": "select",
      "default": "q1",
      "options": [
        { "value": "q1", "label": "Primer trimestre" },
        { "value": "q2", "label": "Segundo trimestre" }
      ]
    }
  ]
}
</script>
```

### Tipos soportados

| Tipo | Campos requeridos | Campos opcionales |
|---|---|---|
| `number` | `default` | `min`, `max`, `step`, `unit` |
| `text` | `default` | `maxLength` |
| `boolean` | `default` | — |
| `select` | `default`, `options` | — |
| `range` | `default`, `min`, `max` | `step`, `unit` |
| `date` | `default` (AAAA-MM-DD) | `min`, `max` |
| `color` | `default` (#RRGGBB) | — |

Ningún tipo fuera de esta lista es válido. Si aparece uno, rechazá la carga del dashboard.

### API que el contenedor expone al dashboard

Dentro del iframe, la plataforma inyecta un objeto global `Dashboard` antes de que corra el script del dashboard:

- `Dashboard.params` — objeto plano con los valores resueltos, clave por `param_id`
- `Dashboard.onChange(callback)` — registra la función a llamar cuando cambie un parámetro
- `Dashboard.setHeight(px)` — informa al contenedor la altura del contenido
- `Dashboard.ready()` — señala que el dashboard terminó de inicializar

---

## 6. Aislamiento y comunicación

Cada dashboard se ejecuta en un `<iframe sandbox="allow-scripts">`. Sin `allow-same-origin`. El HTML se inyecta mediante `srcdoc`, con un preámbulo que define el objeto `Dashboard` y el puente de mensajes.

Toda comunicación es por `postMessage`. Definí estos mensajes y ninguno más:

**Del iframe al contenedor:**

```js
{ type: "dashboard:ready" }
{ type: "dashboard:height", height: 840 }
{ type: "param:change", paramId: "meta_ventas", value: 500000000 }
{ type: "dashboard:error", message: "..." }
```

**Del contenedor al iframe:**

```js
{ type: "params:init", params: { meta_ventas: 500000000, ... } }
{ type: "params:update", params: { meta_ventas: 520000000 } }
```

Validá el `origin` de cada mensaje recibido y descartá cualquiera con forma inesperada. Un dashboard mal escrito no puede tumbar el contenedor.

---

## 7. Endpoints

```
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
POST   /api/auth/forgot-password
POST   /api/auth/reset-password

GET    /api/dashboards                              lista publicados
GET    /api/dashboards/{id}                         html + manifest + valores resueltos
PUT    /api/dashboards/{id}/params/{paramId}        guarda un valor
DELETE /api/dashboards/{id}/params/{paramId}        borra override, vuelve al base
GET    /api/dashboards/{id}/history                 historial propio del usuario

POST   /api/admin/dashboards                        publicar (super_admin)
PUT    /api/admin/dashboards/{id}                   actualizar (super_admin)
DELETE /api/admin/dashboards/{id}                   (super_admin)
GET    /api/admin/dashboards/{id}/overview          escenarios por usuario (super_admin)
GET    /api/admin/dashboards/{id}/history           historial completo (super_admin)
GET    /api/admin/users                             (super_admin)
POST   /api/admin/users                             (super_admin)
PUT    /api/admin/users/{id}                        (super_admin)
```

### Escritura de un parámetro

`PUT /api/dashboards/{id}/params/{paramId}` recibe:

```json
{ "value": 500000000, "scope": "user" }
```

`scope` acepta `user` o `base`. El `user_id` **nunca** viaja en el cuerpo: sale de la sesión autenticada. Si `scope` es `base`, verificá que el solicitante sea `super_admin`; un usuario común solo escribe su propio override.

Antes de escribir, validá contra el manifiesto del dashboard:

1. Que `paramId` exista en `manifest->params`
2. Que el tipo del valor coincida con el tipo declarado
3. Que respete `min`, `max`, `maxLength` o las `options` según corresponda

Si algo falla, devolvé 422 con el motivo. La base de datos no impone el tipo porque el tipo es dinámico: toda la validación vive en esta capa.

Luego, upsert sobre el índice único:

```sql
insert into param_values (dashboard_id, param_id, user_id, value, updated_by)
values (:dashboard_id, :param_id, :user_id, :value::jsonb, :actor_id)
on conflict (dashboard_id, param_id, coalesce(user_id, '00000000-0000-0000-0000-000000000000'::uuid))
do update set value = excluded.value,
              updated_by = excluded.updated_by,
              updated_at = now();
```

### Reset de un parámetro

`DELETE` borra la fila de override del usuario. **No escribas el default.** Borrar hace que la resolución caiga sola al valor base, y si el super administrador cambia ese base después, el usuario lo ve reflejado. Escribir el default lo dejaría congelado.

---

## 8. Validador de carga

Cuando el super administrador publica un dashboard, antes de guardarlo verificá:

1. El archivo contiene un `<script type="application/json" id="dashboard-manifest">`
2. El contenido de ese bloque parsea como JSON válido
3. Tiene `id`, `version`, `title` y `params` como array
4. Todos los `param_id` son únicos dentro del dashboard
5. Todos los `type` están en la lista de tipos soportados
6. Cada parámetro tiene los campos requeridos por su tipo
7. Cada `default` es válido según su propio tipo y rango
8. Los `select` tienen `options` no vacío y el `default` está entre las opciones
9. No hay `localStorage`, `sessionStorage`, `document.cookie` ni `fetch` a dominios externos en el JS
10. Las etiquetas `<script src>` apuntan solo a la lista blanca de CDN permitidos

Si algo falla, rechazá con el detalle exacto de qué regla se incumplió y en qué parámetro. El super administrador tiene que poder corregir sin adivinar.

Al actualizar un dashboard existente, compará el manifiesto nuevo con el anterior y mostrá un resumen antes de confirmar: parámetros agregados, eliminados y con tipo cambiado. Advertí explícitamente si un `param_id` cambió de tipo, porque los valores guardados con el tipo viejo pueden quedar inválidos.

---

## 9. Interfaz

### Vista de dashboard (usuario)

Iframe del dashboard ocupando el área principal, con la altura ajustada según `dashboard:height`. Panel lateral con los controles de parámetros generados automáticamente a partir del manifiesto, agrupados en el orden en que aparecen. Cada control con su `label`, su `unit` si tiene, y un botón de reset individual visible solo cuando ese parámetro tiene override. Botón de restablecer todo. Indicador de guardado.

Los cambios se envían con debounce de 400ms, para que arrastrar un `range` no dispare cincuenta escrituras.

### Panel de super administración

Listado de dashboards con estado de publicación. Formulario de carga con validación previa y reporte de errores legible. Editor de valores base, que es la misma interfaz de parámetros pero escribiendo con `scope: base`. Gestión de usuarios. Vista consolidada de escenarios por usuario para un dashboard dado. Historial filtrable por dashboard, parámetro y usuario.

### Diseño

Sobrio y funcional, orientado a uso interno prolongado. Densidad de información alta, sin decoración innecesaria. Contraste suficiente para lectura sostenida.

---

## 10. Plan de ejecución

Trabajá en este orden. No avances a la fase siguiente sin que la anterior esté verificada.

**Semana 1 — Fundación.** Proyecto Laravel y React funcionando juntos. Migraciones completas con el trigger de historial. Autenticación con Sanctum y middleware de roles. Seeders con un super administrador y dos usuarios de prueba. Layout base de la aplicación.

**Semana 2 — Motor de publicación.** Validador de manifiesto con cobertura de las diez reglas. Endpoint de carga. Componente contenedor con iframe sandbox y puente de postMessage. Dashboard de referencia funcional que ejercite los siete tipos de parámetro. Verificación: el dashboard de referencia se publica y se renderiza dentro de la plataforma.

**Semana 3 — Motor de parámetros.** Generador de controles a partir del manifiesto, un componente por tipo. Endpoints de escritura y borrado con validación completa. Consulta de resolución de tres niveles. Historial escribiéndose por trigger. Verificación: dos usuarios distintos modifican el mismo parámetro y cada uno ve solo su valor; el reset devuelve al base.

**Semana 4 — Administración y cierre.** Panel de super administración completo. Vista consolidada e historial. Pruebas de integración sobre los flujos críticos. Kit de generación de dashboards. Despliegue a producción.

---

## 11. Entregables del kit

Además del código, el proyecto entrega:

- `kit/PLANTILLA-PROMPT.md` — el bloque que el super administrador pega antes de su pedido para generar dashboards conformes con IA
- `kit/dashboard-referencia.html` — dashboard funcional que usa los siete tipos, comentado
- `kit/ESPECIFICACION.md` — el formato del manifiesto y la API `Dashboard`, con ejemplos
- `kit/GUIA-OPERATIVA.md` — cómo publicar, actualizar y administrar valores base

---

## 12. Criterios de calidad

Escribí las migraciones con SQL explícito donde el schema builder de Laravel no alcance, especialmente para el índice único con `coalesce` y el trigger de historial.

Toda validación de parámetros vive en una sola clase de servicio, reutilizada por el validador de carga y por el endpoint de escritura. No la dupliques.

Tests de integración obligatorios sobre: resolución de tres niveles, rechazo de valores fuera de rango, aislamiento entre usuarios, reset que vuelve al base, y escritura de `scope: base` denegada a usuario común.

Los mensajes de error de la API son legibles y accionables. Nada de "validation failed" a secas.

Sin datos de prueba hardcodeados en el código de producción. Todo lo de prueba va en seeders.

---

## 13. Cómo empezar

Antes de escribir código, leé este documento completo y devolveme:

1. Un plan de implementación fase por fase con los archivos que vas a crear
2. Cualquier ambigüedad o contradicción que hayas encontrado acá
3. Las decisiones técnicas que ves como riesgosas y qué proponés en cambio

Recién después de que revise eso, empezamos con la Semana 1.
