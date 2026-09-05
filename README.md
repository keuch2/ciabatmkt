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

Desarrollo, en dos terminales:

```bash
php artisan serve        # http://localhost:8000
npm run dev              # Vite con HMR
```

Producción o prueba sin HMR: `npm run build` y servir `public/` con Apache o nginx.

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

## Estructura

```
app/Enums/UserRole.php                 roles: super_admin | user
app/Http/Middleware/EnsureSuperAdmin   alias 'super_admin'
app/Http/Middleware/EnsureUserIsActive alias 'active'
app/Models/{User,Dashboard,ParamValue,ParamValueHistory}
database/migrations/                   users, dashboards, param_values, param_value_history + triggers
resources/js/api/client.ts             fetch + XSRF para Sanctum
resources/js/auth/                     AuthProvider y guards de ruta
resources/js/layout/AppShell.tsx       layout con navegación por rol
routes/api.php                         endpoints; routes/web.php sirve la SPA
```
