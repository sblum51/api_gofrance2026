# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.5
ARG FRANKENPHP_VERSION=1
ARG COMPOSER_VERSION=2

# -------- image composer (uniquement pour récupérer le binaire) --------
FROM composer:${COMPOSER_VERSION} AS composer_bin

# -------- base FrankenPHP (partagée entre les étapes) --------
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-bookworm AS base

ARG APCU_VERSION=5.1.24

WORKDIR /app

RUN install-php-extensions \
    intl \
    opcache \
    pdo_pgsql \
    zip \
    gd \
    apcu-${APCU_VERSION} \
  && rm -rf /var/cache/apt/* /var/lib/apt/lists/*

COPY --from=composer_bin /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    APP_ENV=prod \
    APP_DEBUG=0 \
    PHP_INI_SCAN_DIR="$PHP_INI_DIR/conf.d"

COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-gofrance.ini"
COPY docker/Caddyfile /etc/caddy/Caddyfile

# FrankenPHP ≥ 1.4 tourne sous l'utilisateur non-root `app` (UID 33). Caddy a
# malgré tout besoin de provisionner sa PKI locale sous /data/caddy/pki au
# démarrage, même si le HTTPS est désactivé au niveau du site : l'endpoint
# d'administration l'exige. On pré-crée les dossiers en écriture pour que les
# volumes montés par-dessus en héritent.
RUN mkdir -p /data/caddy /config/caddy \
    && chmod -R 0777 /data /config

# -------- étape vendor (couche mise en cache) --------
FROM base AS vendor

COPY composer.json composer.lock symfony.lock ./
# Le secret `composer_auth` injecte ~/.composer/auth.json au build sans le
# laisser dans l'image finale : nécessaire pour éviter la limite de débit
# anonyme de GitHub (erreurs « HTTP/2 400 » sur les téléchargements).
# Optionnel : sans le fichier, composer retombe sur des appels anonymes.
RUN --mount=type=cache,target=/root/.composer/cache \
    --mount=type=secret,id=composer_auth,target=/root/.composer/auth.json,required=false \
    composer install \
      --no-dev --no-scripts --no-autoloader --prefer-dist --classmap-authoritative \
    || composer install \
      --no-dev --no-scripts --no-autoloader --prefer-dist --classmap-authoritative

# -------- image de production --------
FROM base AS prod

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN --mount=type=cache,target=/root/.composer/cache \
    composer dump-autoload --classmap-authoritative --no-dev \
  && composer run-script post-install-cmd --no-dev

# var/storage est monté depuis l'hôte au runtime (médias + JSON générés), mais
# les dossiers doivent exister et appartenir à www-data dans l'image, sinon le
# premier upload échoue avant même que le volume ne soit peuplé.
RUN mkdir -p var/cache var/log var/share \
      var/storage/media/logos var/storage/media/covers \
      var/storage/media/parcours var/storage/media/parcours-points \
      var/storage/data \
  && chown -R www-data:www-data var \
  && chmod -R u+rwX,g+rwX var

EXPOSE 80 443 443/udp

HEALTHCHECK --interval=10s --timeout=3s --start-period=20s --retries=3 \
  CMD curl -fsS http://localhost:2019/metrics > /dev/null || exit 1

USER www-data
