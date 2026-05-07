FROM php:8.2-apache

# Instalar extensiones necesarias para Postgres y MongoDB
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Instalar driver de MongoDB
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Configurar el sitio
COPY . /var/www/html
WORKDIR /var/www/html

# Permisos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Instalar dependencias de Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

EXPOSE 80
CMD ["apache2-foreground"]