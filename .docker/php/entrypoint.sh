#!/bin/sh

# Fix ownership of SQLite database files if they exist
if [ -f "/var/www/html/brc.db" ]; then
    echo "Setting correct ownership for SQLite database files..."
    chown www-data:www-data /var/www/html/brc.db*
fi

# Execute the original command (php-fpm)
exec "$@"