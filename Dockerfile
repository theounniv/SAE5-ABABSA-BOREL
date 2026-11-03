FROM composer:2 AS dependencies
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-dev --no-scripts

FROM php:8.4-cli
WORKDIR /app
COPY .env composer.json ./
COPY bin/ bin/
COPY config/ config/
COPY public/ public/
COPY src/ src/
COPY templates/ templates/
COPY --from=dependencies /app/vendor/ vendor/
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
