FROM php:8.4.5-fpm-alpine

# Set environment variables
# Default timezone, can be overridden at runtime
ENV TZ=UTC
# PHP settings, can be overridden or extended with a custom php.ini
ENV PHP_UPLOAD_MAX_FILESIZE=10M
ENV PHP_POST_MAX_SIZE=12M
ENV PHP_MAX_INPUT_VARS=3000
ENV PHP_MEMORY_LIMIT=256M

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
# $PHPIZE_DEPS includes tools like autoconf, automake, libtool, etc., needed for PECL
# imagemagick for image processing
# git for composer and version retrieval
# icu-dev for intl extension
# libzip-dev for zip extension
# sqlite-dev for sqlite3 and pdo_sqlite extensions
# freetype-dev, libjpeg-turbo-dev, libpng-dev for gd extension
RUN apk add --no-cache \
  $PHPIZE_DEPS \
  linux-headers \
  imagemagick \
  git \
  icu-dev \
  libzip-dev \
  sqlite-dev \
  freetype-dev \
  libjpeg-turbo-dev \
  libpng-dev \
  oniguruma-dev \
  bash

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) bcmath
RUN docker-php-ext-install -j$(nproc) exif
RUN docker-php-ext-install -j$(nproc) gd
RUN docker-php-ext-install -j$(nproc) intl
RUN docker-php-ext-install -j$(nproc) sockets
RUN docker-php-ext-install -j$(nproc) zip

# Install redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Configure PHP settings
RUN echo "date.timezone=\${TZ}" > /usr/local/etc/php/conf.d/app-timezone.ini && \
    echo "upload_max_filesize=\${PHP_UPLOAD_MAX_FILESIZE}" >> /usr/local/etc/php/conf.d/app-uploads.ini && \
    echo "post_max_size=\${PHP_POST_MAX_SIZE}" >> /usr/local/etc/php/conf.d/app-uploads.ini && \
    echo "max_input_vars=\${PHP_MAX_INPUT_VARS}" >> /usr/local/etc/php/conf.d/app-vars.ini && \
    echo "memory_limit=\${PHP_MEMORY_LIMIT}" >> /usr/local/etc/php/conf.d/app-memory.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-plugins --no-scripts --no-dev --no-autoloader --prefer-dist && \
    composer dump-autoload --optimize

COPY . .

# Create FIFO for cron logs (ensure it's created before cron daemon tries to use it indirectly)
# And give it open permissions so any user (like www-data if specified in crontab) can write to it.
RUN mkfifo /var/log/cron.fifo && chmod 666 /var/log/cron.fifo

# Ensure the .env file is processed if it exists and set permissions
# The .env file itself should be mounted or created at runtime, not baked into the image.
# This line is a placeholder if your app uses a specific script to load .env or expects it.
# RUN if [ -f .env ]; then cp .env .env.docker; fi

# Set permissions for directories that PHP needs to write to (e.g., cache, logs, uploads)
# For now, just ensure the whole app directory is owned by www-data
RUN chown -R www-data:www-data /var/www/html

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
