# 10 — Local Development (Laragon)

## 1. Prasyarat

| Tool     | Versi | Sumber                                                                             |
| -------- | ----- | ---------------------------------------------------------------------------------- |
| PHP      | 8.4   | Laragon `php-8.4.27` — **set sebagai versi aktif** di Menu → PHP → Version         |
| Composer | 2.x   | Laragon / ComposerSetup                                                            |
| Node.js  | 22+   | nodejs.org                                                                         |
| pnpm     | 10+   | `npm i -g pnpm`                                                                    |
| MySQL    | 8     | Laragon                                                                            |
| Redis    | 5+    | Laragon (Menu → Redis → Start). Opsional di awal: queue & cache memakai `database` |
| Mailpit  | 1.33  | `C:\laragon\bin\mailpit\` — penangkap email lokal (lihat §6b)                      |
| MinIO    | —     | Opsional, untuk penyimpanan file S3-compatible (lihat §6)                          |
| ngrok    | 3.x   | Laragon (`bin/ngrok`) — baru dibutuhkan di fase bot Telegram                       |

Ekstensi PHP: `pdo_mysql`, `mbstring`, `gd`, `intl`, `fileinfo`, `zip`, `bcmath` (semua sudah aktif di PHP 8.4 Laragon). Redis memakai `predis/predis`, tidak perlu ekstensi.

> Jika `php -v` di terminal masih 8.2, tambahkan PHP 8.4 ke PATH sesi: `export PATH=/c/laragon/bin/php/php-8.4.27-Win32-vs17-x64:$PATH` (Git Bash).

## 2. Database

| Database                 | Isi                                                     |
| ------------------------ | ------------------------------------------------------- |
| `budgeting_app`          | data aplikasi                                           |
| `budgeting_app_log`      | activity log (koneksi `activity`), disimpan **30 hari** |
| `budgeting_app_test`     | test                                                    |
| `budgeting_app_log_test` | test activity log                                       |

```bash
mysql -uroot -e "CREATE DATABASE budgeting_app; CREATE DATABASE budgeting_app_log; CREATE DATABASE budgeting_app_test; CREATE DATABASE budgeting_app_log_test;"
```

## 3. Setup Pertama

```bash
composer install
pnpm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

URL lokal (virtual host Laragon): `http://budgeting-app.test`.

## 4. Environment (.env)

```env
APP_NAME="Budgeting App"
APP_URL=http://budgeting-app.test
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=mysql
DB_DATABASE=budgeting_app

ACTIVITY_DB_DATABASE=budgeting_app_log
ACTIVITYLOG_RETENTION_DAYS=30

QUEUE_CONNECTION=database       # redis bila Redis sudah jalan
CACHE_STORE=database            # redis bila Redis sudah jalan
REDIS_CLIENT=predis

UPLOADS_DISK=local              # minio bila MinIO sudah jalan
MINIO_ENDPOINT=http://127.0.0.1:9000
MINIO_ACCESS_KEY=
MINIO_SECRET_KEY=
MINIO_BUCKET=budgeting-app

MAIL_MAILER=smtp                # Mailpit
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_FROM_ADDRESS="noreply@budgeting.test"

RECEIPT_PARSER=fake             # fake | gemini | claude
GEMINI_API_KEY=

# Fase bot Telegram
TELEGRAM_BOT_TOKEN=             # token bot DEV
TELEGRAM_BOT_USERNAME=
TELEGRAM_WEBHOOK_SECRET=
TELEGRAM_MODE=polling
```

## 5. Menjalankan Harian

```bash
composer run dev        # server + queue + logs + vite (php artisan dev)
php artisan schedule:work   # scheduler (pembersihan log 30 hari, backup, recurring)
```

## 6. Penyimpanan File (MinIO)

Semua upload (struk, export) memakai disk `config('filesystems.uploads')`, selalu privat. Di awal memakai `local` (`storage/app/private`). Untuk memakai MinIO:

1. Unduh `minio.exe` dari situs MinIO, simpan mis. di `C:\laragon\bin\minio\`.
2. Jalankan: `minio.exe server C:\laragon\data\minio --console-address :9001`.
3. Buka console `http://127.0.0.1:9001`, buat bucket `budgeting-app` (private) dan access key.
4. Isi `MINIO_ACCESS_KEY`, `MINIO_SECRET_KEY`, lalu set `UPLOADS_DISK=minio`.

Production cukup mengganti endpoint/kredensial ke storage S3-compatible lain (MinIO self-hosted, Cloudflare R2, AWS S3) — kode tidak berubah.

## 6b. Email Lokal (Mailpit)

Semua email aplikasi (verifikasi akun, reset password, undangan, notifikasi) dikirim ke **Mailpit** dan **tidak pernah sampai ke inbox sungguhan**.

- Jalankan: dobel-klik `C:\laragon\bin\mailpit\start-mailpit.bat` (SMTP `127.0.0.1:1025`).
- Lihat email: **http://localhost:8025** — klik email untuk membuka link verifikasi, dsb.
- Email tersimpan di `C:\laragon\data\mailpit\mailpit.db` (maks. 1000 email terakhir).
- Agar otomatis jalan saat Windows start: taruh shortcut `start-mailpit.bat` di folder Startup (`Win+R` → `shell:startup`).
- Jika Mailpit tidak berjalan, pengiriman email gagal (error koneksi ke port 1025). Untuk sementara bisa set `MAIL_MAILER=log` (email ditulis ke `storage/logs/laravel.log`).
- Production: ganti `MAIL_*` ke provider SMTP sungguhan (Brevo/Resend/Mailgun/SES) setelah domain terverifikasi.

## 7. Telegram (Fase 6)

```bash
php artisan telegram:poll                                  # mode polling (bot dev)
ngrok http --host-header=budgeting-app.test 80             # mode webhook
php artisan telegram:set-webhook https://<id>.ngrok-free.app
```

URL ngrok berubah setiap restart → jalankan ulang `telegram:set-webhook`. Gunakan **bot khusus development** (token berbeda dari production).

## 8. Backup & Monitoring

- Backup: `php artisan backup:run` (DB + file upload) dijadwalkan harian 02:00; simpan salinan di luar laptop.
- Activity log: `activitylog:clean` berjalan harian 01:00, menghapus entri > 30 hari.
- Debug: Laravel Telescope di `http://budgeting-app.test/telescope` (hanya `APP_ENV=local`).

## 9. Data Contoh

`php artisan db:seed --class=DemoSeeder` → keluarga "Keluarga Demo", 3 anggota (owner/member/viewer, password `password`), 3 bulan transaksi, budget, dan tujuan tabungan.

## 10. Kualitas Kode

```bash
composer test               # Pint (cek) + Larastan + Pest
./vendor/bin/pest --parallel
./vendor/bin/pint           # format PHP
pnpm run check              # format + lint frontend (Vite+)
pnpm run types:check        # vue-tsc
```
