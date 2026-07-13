#!/bin/sh
set -e

cd /var/www/html

# Semua perintah artisan di bawah dijalankan sebagai www-data, bukan root.
# Kalau dijalankan sebagai root, file yang dibuat Laravel saat runtime
# (terutama storage/logs/laravel.log) jadi milik root:root 0644 di dalam
# direktori milik www-data. Akibatnya queue-worker yang jalan sebagai www-data
# gagal menulis log, crash saat start, dan Supervisor menandainya FATAL secara
# permanen (startretries habis) -> worker harus di-start manual.
artisan() {
    su -s /bin/sh -c "php artisan $*" www-data
}

# Jaring pengaman untuk container yang sudah terlanjur punya file root-owned
# dari image/deploy sebelumnya.
mkdir -p storage/logs storage/framework/cache storage/framework/views \
         storage/framework/sessions storage/app
chown -R www-data:www-data storage bootstrap/cache

artisan config:clear

# Tunggu database siap sebelum menjalankan migrasi & queue worker.
# Postgres bersifat eksternal, jadi saat container boot DB belum tentu reachable.
echo "Menunggu database siap..."
i=0
until artisan db:show >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -ge 30 ]; then
        echo "Database belum siap setelah 30 percobaan, lanjut tetap start (worker akan retry)."
        break
    fi
    echo "Database belum siap, coba lagi dalam 2 detik... ($i/30)"
    sleep 2
done

# Jalankan migrasi (idempoten, aman diulang tiap deploy).
artisan migrate --force || echo "Migrasi gagal/di-skip, cek log."

exec /usr/bin/supervisord
