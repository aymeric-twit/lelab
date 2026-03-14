FROM php:8.3-fpm

# Extensions PHP nécessaires
RUN apt-get update && apt-get install -y \
    libzip-dev unzip git curl libpng-dev libonig-dev \
    && docker-php-ext-install pdo pdo_mysql zip mbstring gd \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node.js (pour les plugins avec dépendances npm)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Dépendances PHP
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copier le code source
COPY . .

# Permissions storage
RUN chown -R www-data:www-data storage/ && chmod -R 775 storage/

EXPOSE 9000
CMD ["php-fpm"]
