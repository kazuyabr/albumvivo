#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/logs storage/uploads storage/tmp storage/cache storage/database 2>/dev/null || true

# Em bind mount Linux o Apache (www-data) precisa de escrita em storage/.
# Em Docker Desktop (macOS/Windows) a operação é inofensiva.
chown -R www-data:www-data storage 2>/dev/null || true
chmod -R u+rwX storage 2>/dev/null || true

exec "$@"
