FROM php:8.4.5-fpm-alpine AS builder

WORKDIR /var/www/html

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
  bash

# Install Composer from the official image (this is good)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP extensions
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

RUN pecl install redis && docker-php-ext-enable redis

# Copy composer files
COPY composer.json composer.lock ./

RUN composer install --no-interaction --no-plugins --no-scripts --no-dev --prefer-dist

# Copy application code - AFTER composer install for better layer caching
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Stage 2: Final Image
FROM php:8.4.5-fpm-alpine

ENV TZ=UTC
ENV PHP_UPLOAD_MAX_FILESIZE=10M
ENV PHP_POST_MAX_SIZE=12M
ENV PHP_MAX_INPUT_VARS=3000
ENV PHP_MEMORY_LIMIT=256M

WORKDIR /var/www/html

# Install runtime system dependencies for the PHP extensions
# These are the non-dev versions of libraries.
RUN apk add --no-cache \
  imagemagick \
  icu-libs \
  libxml2 \
  libzip \
  sqlite-libs \
  freetype \
  libjpeg-turbo \
  libpng \
  bash \
  shadow

# Copy PHP configuration from builder (includes enabled extensions)
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Copy installed PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/

# Configure PHP settings (same as before)
RUN echo "date.timezone=\${TZ}" > /usr/local/etc/php/conf.d/app-timezone.ini && \
  echo "upload_max_filesize=\${PHP_UPLOAD_MAX_FILESIZE}" >> /usr/local/etc/php/conf.d/app-uploads.ini && \
  echo "post_max_size=\${PHP_POST_MAX_SIZE}" >> /usr/local/etc/php/conf.d/app-uploads.ini && \
  echo "max_input_vars=\${PHP_MAX_INPUT_VARS}" >> /usr/local/etc/php/conf.d/app-vars.ini && \
  echo "memory_limit=\${PHP_MEMORY_LIMIT}" >> /usr/local/etc/php/conf.d/app-memory.ini

# Copy Composer's vendor directory from builder
COPY --from=builder /var/www/html/vendor /var/www/html/vendor

# Copy application code from builder (already filtered by .dockerignore)
COPY --from=builder /var/www/html /var/www/html

# Create FIFO for cron logs
RUN mkfifo /var/log/cron.fifo && chmod 666 /var/log/cron.fifo

# permission-fixing script
COPY .docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
