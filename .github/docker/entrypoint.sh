#!/bin/sh

set -e

if [ ! -f /app/www/public/index.php ]; then
    echo 'Application files are missing. Do not mount over /app/www; mount .env, runtime/log and certificate output paths separately.' >&2
    exit 1
fi

# Only runtime files are writable by PHP; application code stays in the image.
# Do not clear runtime/log: it may be a persistent bind mount.
mkdir -p /app/www/runtime/log
chown -R www:www /app/www/runtime
mkdir -p /etc/cert
chown www:www /etc/cert

if [ ! -f /app/www/.env ] || [ ! -s /app/www/.env ]; then
    echo 'Mount a non-empty database configuration file at /app/www/.env. Use .example.env as a template and fill in the database settings before starting the container.' >&2
    exit 1
fi

# Only adjust writable configurations.
# A read-only production configuration must already be readable by www.
if [ -w /app/www/.env ]; then
    chown www:www /app/www/.env
fi

if ! su -s /bin/sh -c 'test -r /app/www/.env' www; then
    echo 'The .env file must be readable by the www user. Check the host file permissions.' >&2
    exit 1
fi

if [ -n "${LISTEN_PORT}" ]; then
    sed -i "s/listen 80 default_server;/listen ${LISTEN_PORT} default_server;/" /etc/nginx/nginx.conf
fi

exec "$@"
