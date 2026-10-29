# VALO's Time image (used by Render): the official solidtime image of the version this fork
# is based on, plus this fork's code and its built frontend assets.
ARG BASE_IMAGE=solidtime/solidtime:0.22.1

FROM ${BASE_IMAGE} AS base

FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
COPY resources/js/packages/ui/package.json resources/js/packages/ui/package.json
COPY resources/js/packages/api/package.json resources/js/packages/api/package.json
RUN npm ci --no-audit --no-fund
COPY . .
# Vite resolves the Ziggy client from the PHP vendor directory
COPY --from=base /var/www/html/vendor ./vendor
RUN npm run build

FROM base
USER root
COPY --chown=laravel:laravel . /var/www/html
COPY --from=assets --chown=laravel:laravel /app/public/build /var/www/html/public/build
# Small instances: two workers, recycled regularly
RUN sed -i 's/--port=8000/--port=8000 --workers=2 --max-requests=500/' /etc/supervisor/conf.d/supervisord.frankenphp.conf \
    && composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache \
    && chown -R laravel:laravel storage bootstrap/cache
USER laravel
