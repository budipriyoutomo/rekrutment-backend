#!/bin/sh
set -e

php /var/www/html/artisan config:clear

exec /usr/bin/supervisord
