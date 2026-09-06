# Despliegue en el VPS

Guía para un VPS Linux (Ubuntu 24.04 o Debian 12) con nginx, PHP-FPM y MySQL en la misma máquina.
Si el servidor ya tiene Apache, usá `apache-vhost.conf.example` en lugar de nginx.

## Requisitos

- PHP **8.3 o 8.4** con `php-fpm`, `php-mysql`, `php-mbstring`, `php-intl`, `php-xml`, `php-curl`, `php-zip`
- MySQL **8.4** (o 8.0.19+; se usan índices funcionales y `INSERT ... AS new`)
- Composer 2
- Node 22 + npm (sólo para compilar los assets; puede hacerse en otra máquina)
- nginx (o Apache con `mod_rewrite`)
- Un dominio con certificado TLS (Let's Encrypt con `certbot`)
- Un servidor SMTP para el correo de restablecimiento de contraseña

## 1. Base de datos

```sql
create database ciabaymkt character set utf8mb4 collate utf8mb4_unicode_ci;
create user 'ciabaymkt'@'localhost' identified by 'CAMBIAR';
grant all privileges on ciabaymkt.* to 'ciabaymkt'@'localhost';
```

Los triggers de historial se crean en las migraciones. El usuario MySQL necesita el privilegio
`TRIGGER` sobre la base (incluido en `all privileges`). Si `log_bin` está activo y el usuario no es
`SUPER`, configurá `log_bin_trust_function_creators = 1` en `my.cnf`.

## 2. Código

```bash
sudo mkdir -p /var/www/ciabaymkt && sudo chown $USER /var/www/ciabaymkt
git clone https://github.com/keuch2/ciabatmkt.git /var/www/ciabaymkt
cd /var/www/ciabaymkt
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build && rm -rf node_modules
cp .env.example .env
```

Editar `.env`:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dashboards.ciabay.com.py      # sin subcarpeta
# ASSET_URL: dejar comentado cuando la app vive en la raíz del dominio
APP_KEY=                                        # lo genera el siguiente paso
DB_CONNECTION=mysql
DB_DATABASE=ciabaymkt
DB_USERNAME=ciabaymkt
DB_PASSWORD=CAMBIAR
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=dashboards.ciabay.com.py
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=dashboards@ciabay.com.py
DASHBOARD_CDN_ALLOWLIST=cdn.jsdelivr.net,cdnjs.cloudflare.com,unpkg.com
```

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=UserSeeder --force   # sólo la primera vez; luego cambiar las claves
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

Cambiá la contraseña del super administrador apenas entres (**Administración → Usuarios**), o
creá tu usuario y desactivá los de prueba.

## 3. nginx

Copiar `nginx.conf.example` a `/etc/nginx/sites-available/ciabaymkt`, ajustar `server_name`, la
ruta del socket de PHP-FPM y los certificados, y activar:

```bash
sudo ln -s /etc/nginx/sites-available/ciabaymkt /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

## 4. Actualizar a una versión nueva

```bash
cd /var/www/ciabaymkt
php artisan down
git pull
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build && rm -rf node_modules
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## 5. Copias de seguridad

Lo único con estado es la base de datos (`dashboards`, `param_values`, `param_value_history`,
`users`). Un `mysqldump` diario alcanza:

```bash
mysqldump --single-transaction ciabaymkt | gzip > /var/backups/ciabaymkt-$(date +%F).sql.gz
```

## 6. Comprobación

- `https://dominio/up` responde 200.
- Login con el super administrador.
- Publicar `kit/dashboard-referencia.html` y abrirlo como usuario común.
- `php artisan dashboards:prune-orphans --dry-run` corre sin errores.

## Pendientes a confirmar con Ciabay

- Sistema operativo y versiones ya instaladas en el VPS.
- Dominio definitivo y quién gestiona el DNS y el certificado.
- Servidor SMTP para el correo de restablecimiento.
