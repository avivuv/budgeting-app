# 10 — Local Development (Laragon)

## 1. Prasyarat
| Tool | Versi | Sumber |
|---|---|---|
| PHP | 8.4 | Laragon (`php-8.4.27`) — set sebagai versi aktif di menu Laragon |
| Composer | 2.x | Laragon |
| Node.js | 20+ | Laragon / nodejs.org |
| MySQL | 8 | Laragon |
| Redis | 7 | Laragon (aktifkan di Menu → Redis) |
| ngrok | 3.x | Laragon (`bin/ngrok`), login dengan authtoken |

Ekstensi PHP yang perlu aktif: `pdo_mysql`, `mbstring`, `gd` atau `imagick`, `fileinfo`, `zip`, `redis` (atau pakai `predis/predis`).

## 2. Setup Pertama
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

Database: buat `budgeting_app` via HeidiSQL / Laragon. URL lokal otomatis: `http://budgeting-app.test`.

## 3. Environment (.env)
```env
APP_NAME="Budgeting App"
APP_URL=http://budgeting-app.test
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=mysql
DB_DATABASE=budgeting_app
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=predis

TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=
TELEGRAM_WEBHOOK_SECRET=        # string acak
TELEGRAM_MODE=polling           # polling | webhook

RECEIPT_PARSER=fake             # fake | claude
ANTHROPIC_API_KEY=
RECEIPT_MODEL=
RECEIPT_FALLBACK_MODEL=
```

## 4. Menjalankan Harian
Jalankan bersamaan (atau pakai `composer run dev` yang menggabungkan semuanya):
```bash
npm run dev                                    # Vite
php artisan queue:work --queue=telegram,receipts,default
php artisan schedule:work                      # scheduler
php artisan telegram:poll                      # bot (mode polling)
```

## 5. Telegram dengan Webhook (opsional)
```bash
ngrok http --host-header=budgeting-app.test 80
php artisan telegram:set-webhook https://<id>.ngrok-free.app
```
URL ngrok berubah setiap restart → jalankan ulang `telegram:set-webhook`. Untuk kembali ke polling: `php artisan telegram:delete-webhook`.

## 6. Data Contoh
`php artisan db:seed --class=DemoSeeder` → keluarga "Keluarga Demo", 3 anggota (owner/member/viewer, password `password`), 3 bulan transaksi acak, budget, dan tujuan tabungan.

## 7. Kualitas Kode
```bash
php artisan test        # Pest
./vendor/bin/pint       # format
./vendor/bin/phpstan    # Larastan
npm run type-check      # vue-tsc
```
