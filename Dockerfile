# syntax=docker/dockerfile:1
# uhifadhi production image — FrankenPHP (Caddy) + PHP 8.4. Single artifact: the
# PHP app, built and run from one image.
#
# This is the deploy shape every installation inherits, so it stays at the level a
# bare kernel needs. Capability that needs more from the image brings it as a
# layer of its own:
#   * an asset pipeline (importmap:install / tailwind:build / asset-map:compile)
#     arrives with the canopy — a kernel with no assets has nothing to compile;
#   * raster/GIS tooling (GDAL et al) arrives with the module that ingests rasters.
# Add those build steps when you add the bundle that needs them.
FROM dunglas/frankenphp:1-php8.4 AS base

WORKDIR /app

# System libs + PHP extensions. pdo_pgsql is here from the start on purpose: the
# platform's database is PostgreSQL/PostGIS, and the seed is copied once and never
# updated — leaving it out would mean every installation editing this file the day
# it installs its first entity-bearing module.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev ca-certificates \
    && install-php-extensions pdo_pgsql intl zip opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Production php.ini + opcache tuning.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY .docker/opcache.ini $PHP_INI_DIR/conf.d/zz-opcache.ini
COPY .docker/uploads.ini $PHP_INI_DIR/conf.d/zz-uploads.ini

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    SERVER_NAME=:80 \
    COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 1) Dependency layer — cached until composer.{json,lock} change. Module bundles
#    install from private GitHub vcs repos, so composer needs a token once the
#    first one is added: COMPOSER_AUTH (auth.json JSON, read natively by composer)
#    comes in as a BuildKit secret — never baked into a layer. Absent, the build
#    carries on unauthenticated, which is all the bare seed needs.
COPY composer.json composer.lock symfony.lock ./
RUN --mount=type=secret,id=COMPOSER_AUTH \
    COMPOSER_AUTH="$(cat /run/secrets/COMPOSER_AUTH 2>/dev/null || true)" \
    composer install --no-dev --no-scripts --no-progress --prefer-dist --no-autoloader

# 2) Application source.
COPY . .

# 3) Optimise the autoloader.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && mkdir -p var && chown -R www-data:www-data var \
    && chmod +x .docker/docker-entrypoint.sh

# Cache is warmed at container start (runtime env available). See entrypoint.
ENTRYPOINT ["/app/.docker/docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
