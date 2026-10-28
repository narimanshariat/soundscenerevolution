# PHP toolchain for Jigsaw builds and the import scripts. Nothing runs on the host.
FROM php:8.3-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev \
 && docker-php-ext-install pdo_mysql zip intl \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
