# Environnement local uniquement ; aucun changement de l’hébergement Alwaysdata.
FROM php:8.4-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev libonig-dev libxml2-dev libssl-dev pkg-config \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip mbstring dom xml xmlwriter \
    && pecl install mongodb-2.1.10 \
    && docker-php-ext-enable mongodb \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY . .
# Les fichiers .env de l’ordinateur sont exclus du contexte de construction.
COPY docker/app.env .env
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts \
    && php bin/console importmap:install \
    && php bin/console assets:install public \
    && mkdir -p var public/uploads/couvertures \
    && chown -R www-data:www-data var public/uploads

EXPOSE 80
CMD ["apache2-foreground"]
