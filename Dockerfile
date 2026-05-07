FROM php:8.2-apache

# 1. Instalar dependencias del sistema
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

# 2. Instalar driver de MongoDB
RUN pecl install mongodb-1.21.0 && docker-php-ext-enable mongodb

# 3. Cambiar el DocumentRoot de Apache a la carpeta /public de Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 4. Configurar el sitio
COPY . /var/www/html
WORKDIR /var/www/html

# 5. Permisos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 6. Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# 7. Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 8. Instalar dependencias
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

EXPOSE 80
# Crear un script de inicio para automatizar las migraciones
RUN echo '#!/bin/bash\nphp artisan migrate --force\napache2-foreground' > /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Ejecutar el script al iniciar el contenedor
CMD ["/usr/local/bin/start.sh"]