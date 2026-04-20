# =========================================
# STAGE 1 — Build Laravel (PHP 8.4)
# =========================================
FROM php:8.4-fpm AS builder

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev libpng-dev libxml2-dev \
    && docker-php-ext-install \
        pdo \
        zip \
        opcache \
        bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY src/composer.json src/composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --optimize-autoloader \
    --no-interaction

COPY src/ .

RUN composer dump-autoload --optimize

RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache


# =========================================
# STAGE 2 — Runtime
# =========================================
FROM php:8.4-fpm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    nginx curl \
    libzip-dev libpng-dev \
    && docker-php-ext-install \
        pdo \
        opcache \
        bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=builder /var/www/html /var/www/html

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/conf.d/default.conf

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Opcache
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 80

CMD ["php-fpm"]