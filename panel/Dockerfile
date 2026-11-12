FROM php:8.4-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    nodejs \
    npm \
    linux-headers \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql pgsql pcntl bcmath zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=8000"]
