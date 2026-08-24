# syntax=docker/dockerfile:1
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative --no-scripts

FROM php:8.3-apache AS app
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql mysqli gd zip intl bcmath opcache pcntl exif redis \
 && a2enmod rewrite headers remoteip \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
 && sed -ri 's!Listen 80!Listen ${APP_PORT_INTERNAL}!' /etc/apache2/ports.conf \
 && sed -ri 's!<VirtualHost \*:80>!<VirtualHost *:${APP_PORT_INTERNAL}>!' /etc/apache2/sites-available/000-default.conf \
 && printf '<Directory /var/www/html/public>\n AllowOverride All\n</Directory>\nServerName localhost\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-enabled/coopos.conf \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'memory_limit=256M\nupload_max_filesize=20M\npost_max_size=25M\nopcache.validate_timestamps=0\nexpose_php=Off\n' > "$PHP_INI_DIR/conf.d/coopos.ini"
ENV APP_PORT_INTERNAL=8080
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN rm -f public/hot .env && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache
COPY docker/entrypoint.sh /usr/local/bin/coopos-entrypoint
RUN chmod +x /usr/local/bin/coopos-entrypoint
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 CMD curl -fsS -H 'Host: localhost' http://127.0.0.1:${APP_PORT_INTERNAL}/up || exit 1
ENTRYPOINT ["coopos-entrypoint"]
CMD ["apache2-foreground"]
