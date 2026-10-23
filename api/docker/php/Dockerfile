# Imagem de DESENVOLVIMENTO da API (app e scheduler usam a mesma).
# Extensões alinhadas à hospedagem compartilhada: sem redis/pcntl.
FROM php:8.4-cli-bookworm

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_mysql intl zip exif bcmath opcache

ARG UID=1000
ARG GID=1000
RUN groupadd --gid "${GID}" app && useradd --uid "${UID}" --gid app --create-home app

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/api
USER app

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
