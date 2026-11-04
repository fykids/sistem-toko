# Stage 1: Build Frontend Assets (Node.js)
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: Runtime PHP/Laravel
FROM php:8.3-cli-alpine
WORKDIR /var/www/html

# Install dependensi sistem & ekstensi PHP
RUN apk add --no-cache libpng-dev libzip-dev zip unzip git \
    && docker-php-ext-install pdo pdo_mysql bcmath gd zip

# Copy Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy aplikasi & hasil build frontend
COPY . .
COPY --from=frontend /app/public/build ./public/build

# Install dependensi PHP tanpa dev
RUN composer install --no-dev --optimize-autoloader

EXPOSE 10000

# Jalankan server Laravel pada port standar Render (10000)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=10000"]