# =========================================
# STAGE 1 — Build Laravel (PHP 8.3)
# =========================================
FROM php:8.3-fpm AS builder

WORKDIR /var/www/html

# Install dependencies
RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev libpng-dev libxml2-dev libpq-dev \
    libonig-dev libcurl4-openssl-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        zip \
        opcache \
        bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer first (biar cache optimal)
COPY src/composer.json src/composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --optimize-autoloader \
    --no-interaction

# Copy source code
COPY src/ .

# Run scripts setelah full copy
RUN composer dump-autoload --optimize

# Laravel optimization
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache


# =========================================
# STAGE 2 — Runtime
# =========================================
FROM php:8.3-fpm

WORKDIR /var/www/html

# Install runtime deps
RUN apt-get update && apt-get install -y \
    nginx supervisor cron curl libpq-dev redis-tools \
    libzip-dev libpng-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        opcache \
        bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy app dari builder
COPY --from=builder /var/www/html /var/www/html

# Copy config
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/laravel-cron /etc/cron.d/laravel-cron

# Permissions
RUN chmod 0644 /etc/cron.d/laravel-cron \
    && crontab /etc/cron.d/laravel-cron \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Opcache recommended config
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 80

CMD ["/usr/bin/supervisord"]