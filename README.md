# Rekrutment Backend

API Laravel untuk aplikasi rekrutmen (Talent Flow). Kode aplikasi ada di `src/`;
konvensi pengembangan di `src/AGENTS.md`.

## Catatan deploy — remediasi keamanan (branch `fix/security-audit`)

Jalankan berurutan saat merilis perubahan ini ke production:

1. **Periksa `.env` production**
   - `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`.
   - `TRUSTED_PROXIES=*` (atau IP/CIDR Traefik) — wajib. Tanpa ini semua request
     terbaca ber-IP proxy dan rate limit login (20/menit per IP) memblokir semua user.
   - `DB_QUEUE_RETRY_AFTER=240` (harus lebih besar dari `--timeout=180` worker).
2. **Pastikan tidak ada slip gaji ganda** (NIK + periode sama). Migrasi unique index
   `salary_slips_nik_periode_unique` berhenti dan menampilkan daftar duplikat bila
   masih ada; hapus duplikatnya dari menu Salary Slips lalu ulangi.
3. **Migrasi**: `php artisan migrate --force`
4. **Jadikan dokumen pelamar lama private** (dulu tersimpan publik di S3):
   ```bash
   php artisan storage:privatize-applicant-documents          # preview
   php artisan storage:privatize-applicant-documents --apply  # eksekusi
   ```
5. **Akun email Slip Gaji**: pastikan di Settings → Akun Email ada satu akun
   *Slip Gaji* yang aktif dan ditandai default. Slip gaji tidak lagi terkirim lewat
   mailer `.env` (email rekrutmen); tanpa akun ini pengiriman gagal dengan pesan jelas.
6. **Restart worker** agar `--timeout=180` & kode job baru dipakai: rebuild container
   (supervisord.conf berubah) atau `supervisorctl restart queue-worker`.
7. **User lama yang belum aktif**: akun hasil self-register tetap nonaktif sampai
   diaktifkan admin lewat Settings → Users (filter "Menunggu aktivasi").
