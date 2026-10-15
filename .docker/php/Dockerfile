# Base stage with minimal dependencies and extensions
FROM php:8.4.13-fpm-alpine3.22

WORKDIR /var/www/html

# extra extensions
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions gmp intl xdebug-3.5.0

# `magick` is used to generate site logos
RUN apk add --no-cache imagemagick imagemagick-svg

# Create FIFO for cron logs
RUN mkfifo /var/log/cron.fifo && chmod 666 /var/log/cron.fifo

# Configure PHP error logging
RUN echo "date.timezone=\${TZ}" > /usr/local/etc/php/conf.d/app-timezone.ini && \
    echo "upload_max_filesize=\${PHP_UPLOAD_MAX_FILESIZE}" >> /usr/local/etc/php/conf.d/app-uploads.ini && \
    echo "post_max_size=\${PHP_POST_MAX_SIZE}" >> /usr/local/etc/php/conf.d/app-uploads.ini && \
    echo "max_input_vars=\${PHP_MAX_INPUT_VARS}" >> /usr/local/etc/php/conf.d/app-vars.ini && \
    echo "memory_limit=\${PHP_MEMORY_LIMIT}" >> /usr/local/etc/php/conf.d/app-memory.ini && \
    echo "log_errors=On" >> /usr/local/etc/php/conf.d/app-errors.ini && \
    echo "error_log=/var/log/php_errors.log" >> /usr/local/etc/php/conf.d/app-errors.ini && \
    echo "error_reporting=E_ALL" >> /usr/local/etc/php/conf.d/app-errors.ini && \
    touch /var/log/php_errors.log && \
    chown www-data:www-data /var/log/php_errors.log && \
    chown www-data:www-data /var/log/cron.fifo

ENV PHP_UPLOAD_MAX_FILESIZE=10M
ENV PHP_POST_MAX_SIZE=12M
ENV PHP_MAX_INPUT_VARS=3000
ENV PHP_MEMORY_LIMIT=256M

# Install Composer
# vendor/ lives on the host, since docker-compose bind-mounts the project over /var/www/html.
# install/update it with: docker compose run --rm php composer install --no-dev
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Copy entrypoint script
COPY ./.docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
