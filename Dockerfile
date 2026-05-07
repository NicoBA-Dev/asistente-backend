FROM php:8.2-apache

# 1. Instalar dependencias del sistema (añadimos zip, unzip y git)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql zip

# 2. Instalar driver de MongoDB (la versión que ya sabemos que funciona)
RUN pecl install mongodb-1.21.0 && docker-php-ext-enable mongodb

# 3. Configurar el sitio
COPY . /var/www/html
WORKDIR /var/www/html

# 4. Permisos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 5. Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# 6. Instalar Composer usando la imagen oficial (más rápido y seguro)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 7. Instalar dependencias (con optimización para poca RAM)
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

EXPOSE 80
CMD ["apache2-foreground"]