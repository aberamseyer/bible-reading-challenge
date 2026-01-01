# Base stage with all dependencies and extensions
FROM php:8.4.5-fpm-alpine AS base

WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache \
    $PHPIZE_DEPS \
    linux-headers \
    icu-dev \
    libxml2-dev \
    libzip-dev \
    sqlite-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    bash \
    shadow

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

RUN docker-php-ext-install -j$(nproc) \
    bcmath \
    ctype \
    dom \
    exif \
    gd \
    intl \
    pdo_sqlite \
    sockets \
    zip

# Install PECL extensions
RUN pecl install redis && docker-php-ext-enable redis
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Create FIFO for cron logs
RUN mkfifo /var/log/cron.fifo && chmod 666 /var/log/cron.fifo

# Configure PHP error logging
RUN echo "log_errors=On" >> /usr/local/etc/php/conf.d/app-errors.ini && \
    echo "error_log=/var/log/php_errors.log" >> /usr/local/etc/php/conf.d/app-errors.ini && \
    echo "error_reporting=E_ALL" >> /usr/local/etc/php/conf.d/app-errors.ini && \
    touch /var/log/php_errors.log && \
    chown www-data:www-data /var/log/php_errors.log && \
    chown www-data:www-data /var/log/cron.fifo

ENV TZ=UTC
ENV PHP_UPLOAD_MAX_FILESIZE=10M
ENV PHP_POST_MAX_SIZE=12M
ENV PHP_MAX_INPUT_VARS=3000
ENV PHP_MEMORY_LIMIT=256M

# Configure PHP settings
RUN echo "date.timezone=\${TZ}" > /usr/local/etc/php/conf.d/app-timezone.ini && \
    echo "upload_max_filesize=\${PHP_UPLOAD_MAX_FILESIZE}" >> /usr/local/etc/php/conf.d/app-uploads.ini && \
    echo "post_max_size=\${PHP_POST_MAX_SIZE}" >> /usr/local/etc/php/conf.d/app-uploads.ini && \
    echo "max_input_vars=\${PHP_MAX_INPUT_VARS}" >> /usr/local/etc/php/conf.d/app-vars.ini && \
    echo "memory_limit=\${PHP_MEMORY_LIMIT}" >> /usr/local/etc/php/conf.d/app-memory.ini

# Install production dependencies first (better caching)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-plugins --no-scripts --no-dev --prefer-dist

# Generate optimized autoloader
RUN composer dump-autoload --optimize --classmap-authoritative

# Copy entrypoint script
COPY ./.docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]