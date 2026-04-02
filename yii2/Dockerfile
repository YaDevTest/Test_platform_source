# Stage 1: сборка
FROM php:8.2-fpm AS builder
WORKDIR /var/www/html
RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl zip unzip \
    libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip bcmath \
    && rm -rf /var/lib/apt/lists/*
RUN pecl install redis && docker-php-ext-enable redis
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer create-project --prefer-dist yiisoft/yii2-app-basic /var/www/html "2.0.*" \
    && composer require yiisoft/yii2-redis

# Stage 2: финальный образ
FROM php:8.2-fpm
WORKDIR /var/www/html
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip bcmath \
    && rm -rf /var/lib/apt/lists/*
RUN pecl install redis && docker-php-ext-enable redis
COPY --from=builder /var/www/html ./
COPY config/ ./config/
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/runtime \
    && chmod -R 755 /var/www/html/web/assets
EXPOSE 9000
CMD ["php-fpm"]