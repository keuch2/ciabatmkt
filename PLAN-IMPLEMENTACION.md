# Plan de implementación — Plataforma de Dashboards Parametrizables (Ciabay)

Respuesta a la sección 13 del prompt, revisada el 2026-09-05.

**Decisiones tomadas tras la revisión:**

- Laravel 12 en lugar de 11 (11 sin soporte de seguridad desde marzo 2026).
- **MySQL 8.4 en lugar de PostgreSQL 16.** Homebrew local no pudo instalar PostgreSQL (el checkout de `/opt/homebrew` tiene el origin apuntando a otro repo y `brew install` falla). MySQL ya estaba instalado y el equipo lo conoce. Adaptaciones: índice único con parte funcional `(coalesce(user_id, nil))`, `insert ... on duplicate key update`, tres triggers (insert/update/delete) en lugar de uno, resolución de tres niveles en PHP iterando el manifiesto en lugar de `jsonb_array_elements`. Los escalares JSON en raíz funcionan igual en `json` de MySQL.
- El historial registra también los reset (`action = delete`); el actor del borrado se pasa por la variable de sesión `@ciabay_actor_id`.
- Usuarios no se borran: se desactivan con `is_active`.
- Repositorio propio: https://github.com/keuch2/ciabatmkt.git

**Estado:** Semana 1 completada y verificada (24 tests, typecheck, build, login por HTTP).

## 0. Estado del entorno local (verificado 2026-09-05)

| Requisito | Estado |
|---|---|
| PHP | 8.4.12 instalado (el prompt pide 8.3; Laravel soporta 8.4 sin cambios) |
| Composer | 2.8.4 |
| Node / npm | 25.5.0 / 11.8.0 |
| PostgreSQL 16 | **No instalado** (Homebrew solo tiene mysql@8.4). Extensión `pdo_pgsql` de PHP sí está presente. Hay que `brew install postgresql@16`. |
| Git | La carpeta está dentro del checkout git de `/opt/homebrew`. Hay que hacer `git init` propio como primer paso. |
| Instalador Laravel | No instalado; se usa `composer create-project`. |

## 1. Plan fase por fase

### Semana 1 — Fundación

Objetivo verificable: login por cookie contra `/api/auth/login`, `/api/auth/me` responde con rol, un endpoint protegido por `super_admin` devuelve 403 a un usuario común, las cuatro tablas y el trigger existen en PostgreSQL.

Decisiones de arranque:
- Un solo proyecto Laravel que sirve la SPA React con el plugin Vite oficial. Mismo origen, así Sanctum en modo stateful funciona sin CORS ni configuración de dominios cruzados.
- `users.id` en UUID (`HasUuids`), porque las FK del prompt lo exigen.
- `role` como columna `text` con `check (role in ('super_admin','user'))` en lugar de enum nativo de PG, para poder agregar roles sin `alter type`.

Archivos:
```
.env.example                                  DB_CONNECTION=pgsql, SESSION_DRIVER=database, MAIL_MAILER=log
composer.json / package.json / vite.config.ts / tsconfig.json / tailwind.config.js
bootstrap/app.php                             statefulApi(), alias de middleware 'super_admin'
config/dashboards.php                         tipos soportados, whitelist de CDN, límites
database/migrations/
  0001_01_01_000000_create_users_table.php    uuid, role + check, sin sessions en la misma migración
  0001_01_01_000001_create_sessions_and_cache_tables.php
  2026_09_05_000010_create_dashboards_table.php
  2026_09_05_000020_create_param_values_table.php     índice único con coalesce en DB::statement
  2026_09_05_000030_create_param_value_history_table.php  función + trigger en DB::statement
database/seeders/DatabaseSeeder.php, UserSeeder.php   1 super_admin + 2 usuarios
app/Enums/UserRole.php
app/Models/User.php, Dashboard.php, ParamValue.php, ParamValueHistory.php
app/Http/Middleware/EnsureSuperAdmin.php
app/Http/Controllers/Auth/LoginController.php, LogoutController.php, MeController.php,
                          ForgotPasswordController.php, ResetPasswordController.php
app/Http/Requests/Auth/LoginRequest.php, ForgotPasswordRequest.php, ResetPasswordRequest.php
app/Http/Resources/UserResource.php
routes/api.php, routes/web.php                web.php solo sirve la SPA (catch-all)
resources/views/app.blade.php
resources/js/main.tsx
resources/js/app/App.tsx, router.tsx, queryClient.ts
resources/js/api/client.ts                    fetch wrapper, XSRF, manejo 401/419/422
resources/js/api/auth.ts
resources/js/auth/AuthProvider.tsx, RequireAuth.tsx, RequireSuperAdmin.tsx
resources/js/layout/AppShell.tsx, Sidebar.tsx, Topbar.tsx
resources/js/pages/LoginPage.tsx, ForgotPasswordPage.tsx, ResetPasswordPage.tsx
resources/css/app.css
tests/Feature/Auth/LoginTest.php, MeTest.php, PasswordResetTest.php
tests/Feature/Auth/SuperAdminMiddlewareTest.php
tests/Feature/Schema/HistoryTriggerTest.php   insert + update en param_values generan filas de historial
phpunit.xml                                   apunta a una DB PostgreSQL de test, NO sqlite
README.md
```

### Semana 2 — Motor de publicación

Objetivo verificable: `kit/dashboard-referencia.html` se publica vía `POST /api/admin/dashboards`, se lista, y se renderiza dentro del iframe sandbox con `params:init` recibido y `dashboard:ready` emitido. Diez tests unitarios, uno por regla del validador, todos en rojo antes y en verde después.

```
app/Services/Manifest/ManifestExtractor.php        saca el bloque JSON del HTML (reglas 1 y 2)
app/Services/Manifest/ManifestValidator.php        reglas 3 a 8; devuelve lista de errores con ruta (params[3].default)
app/Services/Manifest/HtmlSecurityScanner.php      reglas 9 y 10
app/Services/Manifest/ManifestDiff.php             agregados / eliminados / tipo cambiado
app/Services/Params/ParamValueValidator.php        LA clase única de validación de valores por tipo
app/Services/Params/ParamTypes.php                 catálogo de tipos y campos requeridos/opcionales
app/Exceptions/DashboardValidationException.php    -> 422 con errores estructurados
app/Http/Requests/Admin/StoreDashboardRequest.php, UpdateDashboardRequest.php
app/Http/Controllers/Admin/DashboardAdminController.php    store, update, destroy, preview (dry-run del validador + diff)
app/Http/Resources/DashboardResource.php, DashboardSummaryResource.php
app/Http/Controllers/DashboardController.php       index, show (sin valores todavía)
resources/js/dashboard/messages.ts                 tipos de los 6 mensajes + type guards
resources/js/dashboard/preamble.ts                 código del objeto Dashboard, serializado a string
resources/js/dashboard/buildSrcdoc.ts              inyecta CSP + preámbulo antes del primer <script>
resources/js/dashboard/DashboardFrame.tsx          iframe sandbox, puente postMessage, altura
resources/js/pages/DashboardListPage.tsx, DashboardPage.tsx (sin panel de params)
kit/dashboard-referencia.html                      7 tipos, comentado
tests/Unit/Manifest/ManifestValidatorTest.php      un caso por regla + caso feliz
tests/Unit/Manifest/HtmlSecurityScannerTest.php
tests/Unit/Manifest/ManifestDiffTest.php
tests/Feature/Admin/PublishDashboardTest.php       publicación, rechazo con detalle, 403 a usuario común
```

### Semana 3 — Motor de parámetros

Objetivo verificable: los cinco tests de integración obligatorios del punto 12 en verde. Manualmente: dos usuarios cambian `meta_ventas`, cada uno ve el suyo; reset vuelve al base; el historial se llenó solo.

```
app/Services/Params/ParamResolver.php              consulta de tres niveles, itera sobre el manifiesto
app/Services/Params/ParamWriter.php                upsert raw SQL sobre el índice con coalesce
app/Services/Params/ParamRemover.php               delete del override
app/Http/Requests/UpdateParamValueRequest.php      value + scope
app/Http/Controllers/ParamValueController.php      update, destroy, destroyAll
app/Http/Controllers/HistoryController.php         historial propio
app/Policies/DashboardPolicy.php                   view (publicado o super_admin), writeBase
app/Console/Commands/PruneOrphanParamValues.php    dashboards:prune-orphans {--dry-run}
resources/js/api/dashboards.ts, params.ts
resources/js/params/ParamPanel.tsx
resources/js/params/controls/NumberControl.tsx, TextControl.tsx, BooleanControl.tsx,
                             SelectControl.tsx, RangeControl.tsx, DateControl.tsx, ColorControl.tsx
resources/js/params/controlFor.ts                  mapa tipo -> componente
resources/js/params/useParamState.ts               estado optimista, debounce 400ms por param, cancelación al resetear
resources/js/params/SaveIndicator.tsx
resources/js/pages/DashboardPage.tsx               integra frame + panel + params:update
tests/Feature/Params/ThreeLevelResolutionTest.php
tests/Feature/Params/OutOfRangeRejectedTest.php
tests/Feature/Params/UserIsolationTest.php
tests/Feature/Params/ResetFallsBackToBaseTest.php
tests/Feature/Params/BaseScopeDeniedToUserTest.php
tests/Feature/Params/OrphanValuesIgnoredTest.php
tests/Feature/Console/PruneOrphansTest.php
```

### Semana 4 — Administración y cierre

```
app/Http/Controllers/Admin/OverviewController.php       matriz usuarios x params con origen del valor
app/Http/Controllers/Admin/AdminHistoryController.php   filtros: param_id, user_id, desde, hasta; paginado
app/Http/Controllers/Admin/UserAdminController.php      index, store, update (incluye is_active)
app/Http/Requests/Admin/StoreUserRequest.php, UpdateUserRequest.php
app/Http/Resources/HistoryEntryResource.php, OverviewResource.php
resources/js/admin/AdminDashboardsPage.tsx
resources/js/admin/DashboardUploadPage.tsx              carga + preview de errores + diff antes de confirmar
resources/js/admin/ManifestDiffSummary.tsx
resources/js/admin/BaseValuesPage.tsx                   reusa ParamPanel con scope=base
resources/js/admin/UsersPage.tsx, UserForm.tsx
resources/js/admin/OverviewPage.tsx
resources/js/admin/HistoryPage.tsx
kit/PLANTILLA-PROMPT.md, kit/ESPECIFICACION.md, kit/GUIA-OPERATIVA.md
tests/Feature/Admin/OverviewTest.php, AdminHistoryTest.php, UsersTest.php, UpdateDashboardDiffTest.php
deploy/nginx.conf.example, deploy/DEPLOY.md
.github/workflows/ci.yml                                PHPUnit contra servicio postgres:16 + tsc + vite build
```

## 2. Ambigüedades y contradicciones encontradas

1. **`users` "estándar de Laravel" vs FK en UUID.** El estándar usa `bigint`. Las tablas `dashboards` y `param_values` referencian `users(id)` como `uuid`. Asumo UUID en `users`.
2. **Validar el `origin` del iframe es imposible tal como está escrito.** Con `sandbox="allow-scripts"` sin `allow-same-origin` el iframe tiene origen opaco y `event.origin` llega como la cadena `"null"`. La validación real es `event.source === iframe.contentWindow` más el chequeo de forma del mensaje. Del contenedor al iframe hay que usar `targetOrigin "*"` por el mismo motivo. Propongo implementar eso y documentarlo en la especificación.
3. **Reset no queda en el historial.** El trigger es `after insert or update`; el `DELETE` del override no genera fila y `new_value` es `not null`. Si el historial debe mostrar "el usuario volvió al base", hay que agregar `or delete` al trigger, hacer `new_value` nullable y sumar una columna `action` (`insert|update|delete`). Necesito que decidas; propongo incluirlo.
4. **Borrado de usuarios rompe las FK.** `param_values.updated_by` y `param_value_history.changed_by` son `not null` sin `on delete`. No hay endpoint `DELETE /api/admin/users`, así que asumo que los usuarios no se borran: agrego `is_active` y el login rechaza inactivos. Coherente con el requisito de auditoría.
5. **Estado de publicación en el POST.** El endpoint se llama "publicar" pero `is_published` es `false` por defecto. Asumo que `POST` acepta `is_published` en el cuerpo con default `true`, y que `PUT` permite despublicar sin subir HTML nuevo.
6. **`{id}` en rutas y `id` del manifiesto.** El manifiesto tiene `id: "ventas-trimestral"`; la tabla tiene `id` uuid y `slug`. Asumo: `manifest.id` se guarda en `slug`, las rutas usan el uuid. `POST` con un slug ya existente devuelve 409 apuntando al `PUT` correspondiente.
7. **Firma de `Dashboard.onChange(callback)`.** No dice qué recibe. Propongo `callback(params, changedIds)` con el objeto completo ya mergeado, y que `params:update` traiga solo las claves que cambiaron. El preámbulo mantiene `Dashboard.params` actualizado.
8. **Lista blanca de CDN no definida.** Propongo `cdn.jsdelivr.net`, `cdnjs.cloudflare.com`, `unpkg.com`, configurable en `config/dashboards.php`. Confirmar.
9. **Regla 9 es heurística, no frontera de seguridad.** Un regex sobre el JS se evade con `window['local'+'Storage']`. Además, en un iframe de origen opaco `localStorage` ya lanza `SecurityError` y no hay cookies. Propongo mantener la regla como lint amigable y poner la frontera real en una CSP dentro del `srcdoc`: `default-src 'none'; script-src 'unsafe-inline' <cdns>; style-src 'unsafe-inline'; img-src data:; connect-src 'none'`.
10. **Valores guardados que quedaron inválidos tras cambio de tipo.** El punto 8 advierte pero no dice qué hacer al resolver. Propongo que `ParamResolver` valide el valor almacenado contra el manifiesto vigente y, si no pasa, caiga al `default` y lo marque como `stale` en la respuesta para que la UI lo indique.
11. **Historial "propio" del usuario.** Asumo filas donde `user_id = yo`, no `changed_by = yo`. Son equivalentes salvo que en el futuro un admin edite overrides ajenos.
12. **Escrituras repetidas con el mismo valor.** Con debounce igual puede llegar un `PUT` con el valor ya guardado. Propongo condición `when (old.value is distinct from new.value)` en el trigger de update para no llenar el historial de ruido.
13. **Envío de correo.** Forgot/reset password requieren un mailer; el prompt no define SMTP. Local con `MAIL_MAILER=log`; para producción necesito credenciales.
14. **Alta de usuarios.** No dice si el admin fija contraseña o se manda invitación. Asumo que el admin fija una inicial y el usuario puede usar "olvidé mi contraseña".
15. **Forma del `overview`.** No está definida. Propongo matriz `usuarios x parámetros` con valor efectivo y origen (`override|base|default`) por celda.

## 3. Decisiones técnicas riesgosas y alternativa propuesta

1. **Laravel 11 está fuera de soporte de seguridad desde marzo de 2026.** Hoy es septiembre de 2026. Propongo Laravel 12: misma estructura de proyecto, misma API de Sanctum, sin cambios en el plan. Es la única desviación del stack que recomiendo con firmeza.
2. **PostgreSQL no está en la máquina local.** Instalar `postgresql@16` por Homebrew antes de arrancar. Los tests deben correr contra PostgreSQL, nunca contra SQLite: el trigger, el índice con `coalesce` y `jsonb` no existen ahí. CI con servicio `postgres:16`.
3. **El repo está dentro del checkout git de Homebrew.** Primer paso: `git init` propio en `ciabaymkt`, como se hizo con Hidroe y Valores. Sin eso, cualquier `git add` cae en el repo de Homebrew.
4. **Inyección del preámbulo en el `srcdoc`.** Si el preámbulo va después de algún `<script>` del dashboard, `Dashboard` no existe cuando el dashboard lo usa. Propongo insertar CSP y preámbulo como primer hijo de `<head>`, o prepender si no hay `<head>`, y verificar en el validador que el manifiesto aparece una sola vez.
5. **Dashboards que nunca llaman `setHeight`.** El iframe quedaría en altura cero. Propongo que el preámbulo instale un `ResizeObserver` sobre `documentElement` y reporte `dashboard:height` solo si el dashboard nunca llamó `setHeight` explícitamente.
6. **Carrera entre debounce y reset.** Si el usuario arrastra un `range` y toca reset dentro de los 400 ms, el `PUT` pendiente puede pisar el `DELETE`. `useParamState` cancela el temporizador del parámetro al resetear y serializa las peticiones por `param_id`.
7. **Tipado estricto de JSON en PHP.** `500000000` en JSON llega como `int`, `1.5` como `float`, `"500"` como `string`. `ParamValueValidator` acepta `int|float` para `number` y rechaza cadenas numéricas, `bool` estricto para `boolean`, comparación estricta contra `options` para `select`, `Y-m-d` real para `date`, `#RRGGBB` para `color` normalizado a minúsculas.
8. **PHP 8.4 local vs 8.3 pedido.** No afecta el código, pero producción y local deben coincidir para no llevarse sorpresas con deprecaciones. Fijar `"php": "^8.3"` en composer y decidir la versión del servidor antes de la Semana 4.
9. **Destino de despliegue no definido.** El plan de la Semana 4 incluye despliegue, pero no sé si es un VPS, hosting compartido o contenedor. Necesito el dato para armar `deploy/`.
10. **`GET /api/dashboards/{id}` devuelve el HTML completo en cada apertura.** Con dashboards grandes es costoso. Aceptable para uso interno; si crece, se separa en `GET .../html` cacheable con `ETag` por `version`.
