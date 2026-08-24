#!/bin/sh
set -e
cd /var/www/html
ROLE="${CONTAINER_ROLE:-app}"
# Allow host-network deployments to override Apache listen port at runtime
if [ -n "${APP_PORT_INTERNAL:-}" ]; then
  sed -ri "s/^Listen .*/Listen ${APP_PORT_INTERNAL}/" /etc/apache2/ports.conf
  sed -ri "s/<VirtualHost \\*:[0-9]+>/<VirtualHost *:${APP_PORT_INTERNAL}>/" /etc/apache2/sites-available/000-default.conf
fi
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
run() { su -s /bin/sh www-data -c "php artisan $*"; }
if [ "$ROLE" = "app" ]; then
  i=0; until run "db:show --no-interaction" >/dev/null 2>&1 || [ $i -ge 30 ]; do i=$((i+1)); sleep 2; done
  if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then run "migrate --force --no-interaction"; fi
  [ -e public/storage ] || php artisan storage:link --no-interaction || true
fi
run "config:cache"; run "route:cache"; run "view:cache"; run "event:cache" || true
exec "$@"
