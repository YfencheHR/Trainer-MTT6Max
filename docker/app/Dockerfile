FROM php:8.4-fpm

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    curl \
    git \
    zip \
    unzip \
    postgresql-client \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install pcntl pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copiar proyecto
COPY app /app

# Instalar dependencias PHP bloqueadas para este proyecto
RUN composer install --no-interaction --prefer-dist

# Crear directorio storage si no existe
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views
RUN mkdir -p bootstrap/cache

# Permisos
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
