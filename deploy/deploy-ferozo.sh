#!/usr/bin/env bash
#
# Despliegue a un hosting Ferozo (AlmaLinux + Apache + PHP-FPM) donde la app vive en una
# subcarpeta del dominio: https://ciabay.com/marketing
#
# Se ejecuta DESDE LA MÁQUINA LOCAL. Compila los assets acá (el servidor no tiene Node),
# sube el código por rsync a $APP_DIR (fuera de public_html), instala dependencias PHP,
# migra, cachea, y publica el front controller en $PUBLIC_DIR (dentro de public_html).
#
# Variables (todas con default):
#   DEPLOY_HOST, DEPLOY_PORT, DEPLOY_USER   conexión SSH
#   APP_DIR      directorio del proyecto en el servidor (fuera de public_html)
#   PUBLIC_DIR   carpeta pública en public_html que atiende la URL
#   OWNER        usuario:grupo dueño de los archivos (el del pool PHP-FPM)
#   PHP_BIN      PHP 8.3 CLI en el servidor
#   ENV_FILE     .env local para subir SÓLO si el servidor todavía no tiene uno
#   CANONICAL_HOST  host al que se redirige todo lo demás (vacío = sin redirección)
#
# Contraseña SSH: exportá SSHPASS=... para usar sshpass; si no, ssh pide la clave o usa tu llave.

set -euo pipefail

DEPLOY_HOST="${DEPLOY_HOST:-200.58.105.211}"
DEPLOY_PORT="${DEPLOY_PORT:-5221}"
DEPLOY_USER="${DEPLOY_USER:-root}"
APP_DIR="${APP_DIR:-/home/ciabay/ciabaymkt}"
PUBLIC_DIR="${PUBLIC_DIR:-/home/ciabay/public_html/marketing}"
OWNER="${OWNER:-ciabay:ciabay}"
PHP_BIN="${PHP_BIN:-/opt/ferozo/php8-3/bin/php-cli}"
ENV_FILE="${ENV_FILE:-deploy/.env.production.local}"
CANONICAL_HOST="${CANONICAL_HOST:-www.ciabay.com}"

cd "$(dirname "$0")/.."

SSH_OPTS=(-p "$DEPLOY_PORT" -o StrictHostKeyChecking=accept-new)
if [ -n "${SSHPASS:-}" ]; then
  SSH=(sshpass -e ssh "${SSH_OPTS[@]}")
  RSYNC_RSH="sshpass -e ssh ${SSH_OPTS[*]}"
else
  SSH=(ssh "${SSH_OPTS[@]}")
  RSYNC_RSH="ssh ${SSH_OPTS[*]}"
fi
REMOTE="$DEPLOY_USER@$DEPLOY_HOST"

echo "==> 1/6 build local de assets"
npm run build --silent

echo "==> 2/6 rsync del código a $REMOTE:$APP_DIR"
"${SSH[@]}" "$REMOTE" "mkdir -p '$APP_DIR' && chown $OWNER '$APP_DIR'"
rsync -az --delete -e "$RSYNC_RSH" \
  --exclude '.git' --exclude 'node_modules' --exclude 'vendor' --exclude '.env' \
  --exclude 'storage/logs/*' --exclude 'storage/framework/cache/*' --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' --exclude 'public/hot' --exclude 'deploy/.env.*.local' \
  ./ "$REMOTE:$APP_DIR/"

echo "==> 3/6 .env"
if "${SSH[@]}" "$REMOTE" "test -f '$APP_DIR/.env'"; then
  echo "    el servidor ya tiene .env: se conserva"
else
  [ -f "$ENV_FILE" ] || { echo "    falta $ENV_FILE para el primer despliegue"; exit 1; }
  rsync -az -e "$RSYNC_RSH" "$ENV_FILE" "$REMOTE:$APP_DIR/.env"
  "${SSH[@]}" "$REMOTE" "chmod 640 '$APP_DIR/.env'"
  echo "    .env inicial subido"
fi

echo "==> 4/6 composer, key, migrate, caches"
# El PHP CLI de Ferozo sólo lo puede ejecutar root; al final se devuelve la propiedad a $OWNER.
"${SSH[@]}" "$REMOTE" bash -s <<REMOTE_SCRIPT
set -euo pipefail
cd '$APP_DIR'
export COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_HOME=/root/.composer
$PHP_BIN /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --quiet
grep -q '^APP_KEY=.\+' .env || $PHP_BIN artisan key:generate --force --quiet
$PHP_BIN artisan migrate --force
$PHP_BIN artisan optimize:clear --quiet
$PHP_BIN artisan config:cache --quiet
$PHP_BIN artisan route:cache --quiet
$PHP_BIN artisan view:cache --quiet
chown -R $OWNER .
chmod -R ug+rwX storage bootstrap/cache
REMOTE_SCRIPT

echo "==> 5/6 carpeta pública $PUBLIC_DIR"
"${SSH[@]}" "$REMOTE" bash -s <<REMOTE_SCRIPT
set -euo pipefail
mkdir -p '$PUBLIC_DIR'
# .htaccess de Laravel sin la directiva Require (sólo hace falta en el entorno local)
grep -v '^Require all granted' '$APP_DIR/public/.htaccess' | grep -v 'Única carpeta accesible' > '$PUBLIC_DIR/.htaccess'
# Host canónico: todo lo que entre por ciabay.com se redirige a www.ciabay.com (cookies en un solo host).
if [ -n "$CANONICAL_HOST" ]; then
  sed -i "s|^    RewriteEngine On\$|    RewriteEngine On\n\n    # Host canónico\n    RewriteCond %{HTTP_HOST} !^$CANONICAL_HOST\$ [NC]\n    RewriteRule ^ https://$CANONICAL_HOST%{REQUEST_URI} [L,R=301]|" '$PUBLIC_DIR/.htaccess'
fi
cp -f '$APP_DIR/public/robots.txt' '$PUBLIC_DIR/robots.txt' 2>/dev/null || true
cp -f '$APP_DIR/public/favicon.ico' '$PUBLIC_DIR/favicon.ico' 2>/dev/null || true
ln -sfn '$APP_DIR/public/build' '$PUBLIC_DIR/build'
cat > '$PUBLIC_DIR/index.php' <<'PHP'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// La aplicación vive fuera de public_html; esta carpeta sólo expone el front controller.
\$app = '$APP_DIR';

if (file_exists(\$maintenance = \$app.'/storage/framework/maintenance.php')) {
    require \$maintenance;
}

require \$app.'/vendor/autoload.php';

(require_once \$app.'/bootstrap/app.php')
    ->handleRequest(Request::capture());
PHP
chown -R $OWNER '$PUBLIC_DIR'
REMOTE_SCRIPT

echo "==> 6/6 comprobación"
URL="$("${SSH[@]}" "$REMOTE" "grep '^APP_URL=' '$APP_DIR/.env' | cut -d= -f2- | tr -d '\"'")"
if [ -n "$URL" ]; then
  curl -s -o /dev/null -w "    $URL/up -> %{http_code}\n" "$URL/up"
fi
echo "Listo."
