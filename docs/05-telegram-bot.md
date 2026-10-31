# 05 — Telegram Bot

> **Dikerjakan di fase terakhir** ([09-roadmap.md](09-roadmap.md)). Semua Service (transaksi, struk, laporan, budget) sudah jadi dan teruji lewat web & REST API lebih dulu; bot hanya menjadi _entry point_ baru yang memanggil Service tersebut. Notifikasi sebelum bot ada dikirim lewat notifikasi web (database) & email; saat bot jadi, channel Telegram ditambahkan ke Notification yang sama.

## 1. Mode Operasi

| Mode        | Kapan                                 | Perintah                                  |
| ----------- | ------------------------------------- | ----------------------------------------- |
| **Polling** | Development cepat tanpa ngrok         | `php artisan telegram:poll`               |
| **Webhook** | Development dengan ngrok / production | `php artisan telegram:set-webhook {url?}` |

Webhook: `POST /telegram/webhook`, diverifikasi header `X-Telegram-Bot-Api-Secret-Token` = `TELEGRAM_WEBHOOK_SECRET` (middleware `VerifyTelegramSecret`).

**Alur penerimaan update (idempoten, BR-J08):**

```
WebhookController
  1. Cache::add("tg:update:{update_id}", 1, 24h)  → false? sudah diproses → return 200
  2. dispatch ProcessTelegramUpdateJob(update)     (queue: telegram)
  3. return 200 segera                            (Telegram tidak mengirim ulang)
ProcessTelegramUpdateJob → TelegramBot::handle(update)
```

Mode polling memakai jalur yang sama (`telegram:poll` → dedupe → job), sehingga perilaku dev = production.

**Dua bot terpisah**: `@NamaBotDevBot` (lokal, polling) dan `@NamaBot` (production, webhook). Token berbeda di `.env` masing-masing, karena satu bot tidak bisa memakai polling dan webhook sekaligus.

Setup bot di @BotFather:

- `/newbot` → simpan token ke `TELEGRAM_BOT_TOKEN`.
- `/setprivacy` → **Enable** (bot di grup hanya menerima perintah `/...`, mention, dan reply ke bot). Input transaksi di grup memakai `/c` atau mention/reply — lihat §4.
- `/setcommands` → daftar perintah di §5.
- `/setjoingroups` → Enable.

## 2. Menghubungkan Akun & Grup

### 2.1 Chat pribadi

1. Web: _Pengaturan → Telegram → Hubungkan_ → `TelegramLinkService::createCode(user, 'private')`.
2. Tampilkan tombol deep link `https://t.me/<bot>?start=<code>` + QR.
3. Bot menerima `/start <code>` → validasi → simpan `users.telegram_user_id` + `telegram_chats` (type `private`).
4. Bot membalas sambutan + contoh input.

Jika user `/start` tanpa kode / belum terhubung → bot membalas link login web.

### 2.2 Grup keluarga

1. Owner/Admin menambahkan bot ke grup.
2. Web: _Pengaturan → Telegram → Hubungkan Grup_ → kode `purpose = group` (terikat ke `family_id`).
3. Di grup: `/hubungkan <code>` → bot cek pengirim adalah user terhubung dengan role ≥ admin → simpan `telegram_chats` (type `group`).
4. Anggota grup yang belum menghubungkan akun pribadinya: saat mencoba input, bot membalas _"Hubungkan akunmu dulu: [link]"_ (pesan dikirim di grup, mention user).

Event `my_chat_member` (bot dikeluarkan) → `telegram_chats.is_active = false`.

### 2.3 Resolusi konteks (`TelegramContext`)

```
update.from.id  → users.telegram_user_id → $user          (wajib)
update.chat.id  → TelegramChatRepository::resolveByChatId → $family   (grup, BR-I07)
                  atau $user->telegramDefaultFamily                   (pribadi, BR-J09)
cek: user anggota family, family tidak suspended, role ≠ viewer untuk input
→ TenantContext::set($family)
```

## 3. Parser Input Teks

`App\Telegram\Parsers\TransactionParser` → menghasilkan `ParsedTransactionData` (bisa sebagian kosong).

### 3.1 Aturan

| Elemen      | Contoh                                                          | Hasil                                  |
| ----------- | --------------------------------------------------------------- | -------------------------------------- |
| Nominal     | `50000`, `50.000`, `50rb`, `50k`, `1,5jt`, `1.5 juta`, `2jt300` | integer rupiah                         |
| Pemasukan   | diawali `+` atau keyword `gaji, terima, dapat, masuk`           | `income`                               |
| Pengeluaran | default                                                         | `expense`                              |
| Transfer    | `transfer 500rb bca ke gopay` / `tf 500rb bca>gopay`            | `transfer`                             |
| Dompet      | `@bca`, `pakai gopay`, nama dompet disebut                      | match nama wallet (fuzzy)              |
| Tanggal     | `kemarin`, `2 hari lalu`, `tgl 25`, `25/10`                     | default hari ini (zona waktu keluarga) |
| Tag         | `#liburan`                                                      | tag                                    |
| Kategori    | `!transportasi` eksplisit, atau dari keywords                   | match kategori                         |
| Catatan     | sisa teks setelah token dibuang                                 | `note`                                 |

Satu pesan beberapa baris = beberapa transaksi:

```
bensin 50rb
parkir 5rb
makan siang 35rb @gopay
```

### 3.2 Tebak kategori

1. Kategori eksplisit (`!nama`).
2. Kecocokan `categories.keywords` (kata utuh, case-insensitive).
3. Riwayat: kategori yang paling sering dipakai untuk catatan serupa di keluarga ini.
4. Gagal → tanya dengan tombol kategori teratas.

### 3.3 Konfirmasi

Draft disimpan di cache `tg:draft:{uuid}` (TTL 10 menit). Preview:

```
💸 Pengeluaran · Rp 50.000
🏷 Transportasi · 👛 Tunai · 📅 31 Okt 2026
📝 bensin
[✅ Simpan] [🏷 Kategori] [👛 Dompet] [📅 Tanggal] [❌ Batal]
```

Setting keluarga `telegram.auto_save = true` → langsung simpan bila parser yakin (kategori & nominal ditemukan), tetap tampilkan tombol `↩️ Undo`.

Callback data format: `<action>:<draftId>[:<value>]` (≤ 64 byte), mis. `cat:9f3a:12`.

**Idempotensi konfirmasi (BR-B13):** `confirm` mengambil draft dengan `Cache::pull()` (atomik). Tekan dua kali → pull kedua mendapat `null` → bot menjawab "Sudah tersimpan" tanpa membuat transaksi baru. Tombol di pesan lama dihapus (`editMessageReplyMarkup`) setelah tersimpan.

### 3.4 Koreksi lewat reply (BR-J10)

Pesan konfirmasi bot menyimpan `telegram_message_id` ↔ draft/transaksi. User cukup **reply** pesan tersebut:

| Reply                       | Efek                          |
| --------------------------- | ----------------------------- |
| `ubah 45rb` / `45rb`        | ganti nominal                 |
| `kategori makan` / `!makan` | ganti kategori                |
| `dompet bca` / `@bca`       | ganti dompet                  |
| `tanggal kemarin`           | ganti tanggal                 |
| `catatan bensin motor`      | ganti catatan                 |
| `hapus`                     | hapus transaksi (soft delete) |

Perubahan pada transaksi yang sudah tersimpan memanggil `TransactionService::update()` (izin BR-B09, activity log BR-B14), lalu bot mengedit pesan konfirmasi dengan nilai baru.

## 4. Perilaku di Grup vs Pribadi

|                         | Pribadi                                                                 | Grup                                                                  |
| ----------------------- | ----------------------------------------------------------------------- | --------------------------------------------------------------------- |
| Input teks              | Semua pesan yang mengandung nominal                                     | `/c bensin 50rb`, mention `@bot bensin 50rb`, atau reply ke pesan bot |
| Struk / bukti transaksi | Kirim foto, screenshot, atau file (PDF/gambar sebagai dokumen) langsung | Foto/file dengan caption `/struk` atau mention bot                    |
| Pencatat                | user pengirim                                                           | user pengirim                                                         |
| `/saldo`                | tampil lengkap                                                          | sesuai `families.settings.group_show_balance`                         |
| Tombol konfirmasi       | —                                                                       | hanya bisa ditekan oleh pengirim asli (cek `from.id`)                 |

## 5. Daftar Perintah

| Perintah                      | Fungsi                                                                 |
| ----------------------------- | ---------------------------------------------------------------------- |
| `/start [kode]`               | Sambutan / hubungkan akun                                              |
| `/hubungkan <kode>`           | Hubungkan grup ke keluarga                                             |
| `/c <teks>`                   | Catat transaksi (wajib di grup)                                        |
| `/struk`                      | Petunjuk/scan struk                                                    |
| `/saldo`                      | Saldo per dompet & total                                               |
| `/hariini` `/minggu` `/bulan` | Ringkasan periode                                                      |
| `/budget`                     | Sisa budget per kategori (progress bar teks)                           |
| `/terakhir`                   | 5 transaksi terakhir + tombol hapus                                    |
| `/undo`                       | Hapus transaksi terakhir milik pengirim (≤ 24 jam)                     |
| `/laporan [bulan]`            | Kirim PDF laporan bulanan                                              |
| `/keluarga`                   | Ganti keluarga default chat pribadi (jika anggota >1 keluarga, BR-J09) |
| `/bantuan`                    | Contoh format input                                                    |

## 6. Notifikasi Keluar

| Notifikasi                  | Pemicu                                       | Tujuan                        |
| --------------------------- | -------------------------------------------- | ----------------------------- |
| Budget 80% / 100%           | `TransactionCreated` → `CheckBudgetListener` | grup (jika ada) + owner/admin |
| Transaksi baru oleh anggota | opsional (setting)                           | grup                          |
| Pengingat tagihan           | scheduler harian 07:00                       | grup + pembuat rule           |
| Ringkasan harian            | 21:00 (setting)                              | grup                          |
| Ringkasan mingguan          | Minggu 20:00                                 | grup                          |
| Laporan bulanan             | tanggal awal periode 08:00                   | grup + owner (PDF)            |

Notifikasi memakai Laravel Notification dengan channel `database` + `mail` (sudah ada sebelum bot) dan `TelegramChannel` (ditambahkan di fase bot). Pesan Telegram dikirim via `SendTelegramMessageJob` (queue `telegram`) dengan rate limit Telegram (≈30 pesan/detik global, 20/menit per grup) memakai `RateLimiter`.

## 7. Struktur Kode

```
app/Telegram/
├── TelegramBot.php              # dispatch update → handler berdasarkan tipe
├── TelegramContext.php
├── Handlers/
│   ├── StartHandler.php
│   ├── LinkGroupHandler.php
│   ├── TextTransactionHandler.php
│   ├── ReceiptHandler.php       # photo & document (gambar/PDF)
│   ├── ReplyCorrectionHandler.php
│   ├── CallbackQueryHandler.php
│   ├── ChatMemberHandler.php
│   └── Commands/ (SaldoCommand, SummaryCommand, BudgetCommand, UndoCommand, ReportCommand, ...)
├── Parsers/TransactionParser.php
├── Keyboards/TransactionKeyboard.php
└── Messages/                    # template pesan (Blade text atau class)
```

Handler hanya memanggil Service (`TransactionService`, `ReportService`, `BudgetService`, `ReceiptService`). Lihat [02-architecture.md](02-architecture.md).

## 8. Testing

- Unit test `TransactionParser` dengan tabel kasus (≥ 50 variasi kalimat).
- Feature test webhook memakai fixture JSON update Telegram (`tests/Fixtures/telegram/*.json`) dan `Http::fake()` untuk API Telegram.
- Test idempotensi: update dengan `update_id` sama dikirim 2× → 1 transaksi (BR-J08); callback `confirm` 2× → 1 transaksi (BR-B13).
- Test reply koreksi untuk setiap pola di §3.4 (BR-J10).
