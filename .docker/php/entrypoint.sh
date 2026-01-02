#!/bin/sh

# Configure Xdebug based on environment
if [ "$XDEBUG_MODE" = "debug" ] || [ "$XDEBUG_MODE" = "trace" ] || [ "$XDEBUG_MODE" = "coverage" ] || [ "$XDEBUG_MODE" = "profile" ]; then
    echo "Development mode detected. Configuring Xdebug..."

    # Set default values if not provided
    XDEBUG_MODE=${XDEBUG_MODE:-debug}
    XDEBUG_CLIENT_HOST=${XDEBUG_CLIENT_HOST:-host.docker.internal}
    XDEBUG_CLIENT_PORT=${XDEBUG_CLIENT_PORT:-9003}
    XDEBUG_START_WITH_REQUEST=${XDEBUG_START_WITH_REQUEST:-yes}
    XDEBUG_LOG_LEVEL=${XDEBUG_LOG_LEVEL:-0}

    # Remove any existing Xdebug configuration to avoid conflicts
    rm -f /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

    # Write new Xdebug configuration that will override defaults
    cat > /usr/local/etc/php/conf.d/99-xdebug-custom.ini << EOF
; Custom Xdebug configuration for development
zend_extension=xdebug.so
xdebug.mode=${XDEBUG_MODE}
xdebug.client_host=${XDEBUG_CLIENT_HOST}
xdebug.client_port=${XDEBUG_CLIENT_PORT}
xdebug.start_with_request=${XDEBUG_START_WITH_REQUEST}
xdebug.log_level=${XDEBUG_LOG_LEVEL}
xdebug.log=/var/log/xdebug.log
xdebug.discover_client_host=false
xdebug.idekey=VSCODE
xdebug.connect_timeout_ms=2000
xdebug.max_nesting_level=512
EOF

    # Create log file and set permissions
    touch /var/log/xdebug.log
    chown www-data:www-data /var/log/xdebug.log

    echo "Xdebug enabled with mode: ${XDEBUG_MODE}"
    echo "Xdebug will connect to: ${XDEBUG_CLIENT_HOST}:${XDEBUG_CLIENT_PORT}"
else
    echo "Production mode or Xdebug disabled. Disabling Xdebug..."
    # Disable Xdebug by removing extension and clearing configuration
    rm -f /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
    echo "; Xdebug disabled in production" > /usr/local/etc/php/conf.d/99-xdebug-custom.ini
fi

# Execute the original command (php-fpm)
exec "$@"
