FROM composer:2@sha256:5190d9b445add2fa26090108273cb1ef1e1af98175201c737b85b3c4f90fbf7c AS dependencies
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-dev --no-scripts

FROM php:8.4-cli@sha256:ff91dd4b2730676b1673c4cb033b1c0702e1654798ab9bd264db05dab2927228
WORKDIR /app
COPY .env composer.json ./
COPY bin/ bin/
COPY config/ config/
COPY public/ public/
COPY src/ src/
COPY templates/ templates/
COPY --from=dependencies /app/vendor/ vendor/
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
