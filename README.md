# Ciabay Dashboards

Plataforma donde un super administrador publica dashboards HTML autocontenidos y los usuarios
ajustan los parámetros que cada dashboard declara en su manifiesto. Los valores se guardan por
usuario, con historial de quién cambió qué y cuándo.

Especificación completa: `prompt-desarrollo-plataforma-dashboards-ciabay.md`.
Plan y decisiones: `PLAN-IMPLEMENTACION.md`.

## Stack

- Laravel 12, PHP 8.3+, Sanctum con sesión por cookie
- React 18, TypeScript, Vite 7, Tailwind CSS 4
- MySQL 8.4 (JSON, índice funcional, triggers)

## Puesta en marcha local (Homebrew)

```bash
composer install
npm install
cp .env.example .env          # ajustar DB_* si hace falta
php artisan key:generate
mysql -uroot -e "create database if not exists ciabaymkt character set utf8mb4 collate utf8mb4_unicode_ci"
mysql -uroot -e "create database if not exists ciabaymkt_test character set utf8mb4 collate utf8mb4_unicode_ci"
php artisan migrate --seed
```

En el Apache de Homebrew (DocumentRoot `/opt/homebrew/var/www`) la app se abre en
**http://localhost/ciabaymkt/public/**. Para eso `.env` lleva:

```
APP_URL=http://localhost/ciabaymkt/public
ASSET_URL=http://localhost/ciabaymkt/public
```

y los assets se compilan con `npm run build` (repetir tras cada cambio de frontend si no se usa `npm run dev`).

Alternativa sin Apache: `php artisan serve` en http://localhost:8000 con `APP_URL=http://localhost:8000` y sin `ASSET_URL`.
`npm run dev` levanta Vite con HMR en cualquiera de los dos casos.

### Cuentas de prueba (seeder)

| Rol | Correo | Contraseña |
|---|---|---|
| Super administrador | admin@ciabay.local | ciabay2026 |
| Usuario | ana@ciabay.local | ciabay2026 |
| Usuario | bruno@ciabay.local | ciabay2026 |

## Verificación

```bash
php artisan test         # PHPUnit contra la base ciabaymkt_test (MySQL, no SQLite)
npm run typecheck        # tsc
npm run build            # Vite
```

Los tests corren contra MySQL porque el esquema depende de un índice funcional con `coalesce`,
de columnas JSON y de los triggers de historial, que SQLite no reproduce.

## Documentación

- `kit/ESPECIFICACION.md`: formato del manifiesto y API `Dashboard` que debe cumplir un dashboard.
- `kit/PLANTILLA-PROMPT.md`: bloque para pegar en un asistente de IA y obtener dashboards conformes.
- `kit/GUIA-OPERATIVA.md`: cómo publicar, actualizar, definir valores base y administrar usuarios.
- `kit/dashboard-referencia.html`: dashboard de ejemplo con los siete tipos de parámetro.
- `deploy/DEPLOY.md`: despliegue en el VPS (nginx o Apache, MySQL, PHP-FPM).
- `PLAN-IMPLEMENTACION.md`: plan, decisiones y estado por semana.

## Comandos

```bash
php artisan dashboards:prune-orphans --dry-run   # valores guardados de parámetros que ya no existen
```

## Estructura

```
app/Enums/UserRole.php                 roles: super_admin | user
app/Http/Middleware/EnsureSuperAdmin   alias 'super_admin'
app/Http/Middleware/EnsureUserIsActive alias 'active'
app/Models/{User,Dashboard,ParamValue,ParamValueHistory}
app/Services/Manifest/                 extractor, validador (reglas 3-8), escáner (9-10), diff
app/Services/Params/                   ParamValueValidator (única validación de valores), resolver, writer
app/Services/Dashboards/               DashboardPublisher (orquesta las diez reglas)
app/Services/Manifest/                 extractor, validador (reglas 3-8), escáner (9-10), diff
app/Services/Params/                   ParamValueValidator (única validación de valores), resolver, writer
app/Services/Dashboards/               DashboardPublisher (orquesta las diez reglas)
database/migrations/                   users, dashboards, param_values, param_value_history + triggers
resources/js/api/client.ts             fetch + XSRF para Sanctum
resources/js/auth/                     AuthProvider y guards de ruta
resources/js/layout/AppShell.tsx       layout con navegación por rol
resources/js/dashboard/                iframe sandbox, preámbulo Dashboard, puente postMessage
resources/js/params/                   controles por tipo, panel, estado con debounce
resources/js/admin/                    publicación, usuarios, escenarios, historial
resources/js/dashboard/                iframe sandbox, preámbulo Dashboard, puente postMessage
resources/js/params/                   controles por tipo, panel, estado con debounce
resources/js/admin/                    publicación, usuarios, escenarios, historial
routes/api.php                         endpoints; routes/web.php sirve la SPA
```
