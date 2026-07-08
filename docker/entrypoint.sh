#!/bin/sh
set -e

cd /var/www/html

php artisan config:clear

# Tunggu database siap sebelum menjalankan migrasi & queue worker.
# Postgres bersifat eksternal, jadi saat container boot DB belum tentu reachable.
echo "Menunggu database siap..."
i=0
until php artisan db:show >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -ge 30 ]; then
        echo "Database belum siap setelah 30 percobaan, lanjut tetap start (worker akan retry)."
        break
    fi
    echo "Database belum siap, coba lagi dalam 2 detik... ($i/30)"
    sleep 2
done

# Jalankan migrasi (idempoten, aman diulang tiap deploy).
php artisan migrate --force || echo "Migrasi gagal/di-skip, cek log."

exec /usr/bin/supervisord
