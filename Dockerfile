FROM php:8.2-cli-alpine

RUN apk add --no-cache \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    mariadb-client \
    oniguruma-dev

RUN docker-php-ext-install pdo pdo_mysql mbstring pcntl bcmath

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 8000 8080

CMD sh ./start-server.sh
