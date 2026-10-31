# 05 — Telegram Bot

## 1. Mode Operasi

| Mode | Kapan | Perintah |
|---|---|---|
| **Polling** | Development cepat tanpa ngrok | `php artisan telegram:poll` |
| **Webhook** | Development dengan ngrok / production | `php artisan telegram:set-webhook {url?}` |

Webhook: `POST /telegram/webhook`, diverifikasi header `X-Telegram-Bot-Api-Secret-Token` = `TELEGRAM_WEBHOOK_SECRET` (middleware `VerifyTelegramSecret`). Controller langsung membalas `200` dan memproses update; pekerjaan berat (struk, PDF) di-dispatch ke queue.

Setup bot di @BotFather:
- `/newbot` → simpan token ke `TELEGRAM_BOT_TOKEN`.
- `/setprivacy` → **Enable** (bot di grup hanya menerima perintah `/...`, mention, dan reply ke bot). Input transaksi di grup memakai `/c` atau mention/reply — lihat §4.
- `/setcommands` → daftar perintah di §5.
- `/setjoingroups` → Enable.

## 2. Menghubungkan Akun & Grup

### 2.1 Chat pribadi
1. Web: *Pengaturan → Telegram → Hubungkan* → `TelegramLinkService::createCode(user, 'private')`.
2. Tampilkan tombol deep link `https://t.me/<bot>?start=<code>` + QR.
3. Bot menerima `/start <code>` → validasi → simpan `users.telegram_user_id` + `telegram_chats` (type `private`).
4. Bot membalas sambutan + contoh input.

Jika user `/start` tanpa kode / belum terhubung → bot membalas link login web.

### 2.2 Grup keluarga
1. Owner/Admin menambahkan bot ke grup.
2. Web: *Pengaturan → Telegram → Hubungkan Grup* → kode `purpose = group` (terikat ke `family_id`).
3. Di grup: `/hubungkan <code>` → bot cek pengirim adalah user terhubung dengan role ≥ admin → simpan `telegram_chats` (type `group`).
4. Anggota grup yang belum menghubungkan akun pribadinya: saat mencoba input, bot membalas *"Hubungkan akunmu dulu: [link]"* (pesan dikirim di grup, mention user).

Event `my_chat_member` (bot dikeluarkan) → `telegram_chats.is_active = false`.

### 2.3 Resolusi konteks (`TelegramContext`)
```
update.from.id  → users.telegram_user_id → $user          (wajib)
update.chat.id  → telegram_chats.chat_id → $family        (grup)
                  atau $user->currentFamily               (pribadi)
cek: user anggota family, family tidak suspended, role ≠ viewer untuk input
→ TenantContext::set($family)
```

## 3. Parser Input Teks

`App\Telegram\Parsers\TransactionParser` → menghasilkan `ParsedTransactionData` (bisa sebagian kosong).

### 3.1 Aturan
| Elemen | Contoh | Hasil |
|---|---|---|
| Nominal | `50000`, `50.000`, `50rb`, `50k`, `1,5jt`, `1.5 juta`, `2jt300` | integer rupiah |
| Pemasukan | diawali `+` atau keyword `gaji, terima, dapat, masuk` | `income` |
| Pengeluaran | default | `expense` |
| Transfer | `transfer 500rb bca ke gopay` / `tf 500rb bca>gopay` | `transfer` |
| Dompet | `@bca`, `pakai gopay`, nama dompet disebut | match nama wallet (fuzzy) |
| Tanggal | `kemarin`, `2 hari lalu`, `tgl 25`, `25/10` | default hari ini (zona waktu keluarga) |
| Tag | `#liburan` | tag |
| Kategori | `!transportasi` eksplisit, atau dari keywords | match kategori |
| Catatan | sisa teks setelah token dibuang | `note` |

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

## 4. Perilaku di Grup vs Pribadi

| | Pribadi | Grup |
|---|---|---|
| Input teks | Semua pesan yang mengandung nominal | `/c bensin 50rb`, mention `@bot bensin 50rb`, atau reply ke pesan bot |
| Foto struk | Kirim foto langsung | Foto dengan caption `/struk` atau mention bot |
| Pencatat | user pengirim | user pengirim |
| `/saldo` | tampil lengkap | sesuai `families.settings.group_show_balance` |
| Tombol konfirmasi | — | hanya bisa ditekan oleh pengirim asli (cek `from.id`) |

## 5. Daftar Perintah

| Perintah | Fungsi |
|---|---|
| `/start [kode]` | Sambutan / hubungkan akun |
| `/hubungkan <kode>` | Hubungkan grup ke keluarga |
| `/c <teks>` | Catat transaksi (wajib di grup) |
| `/struk` | Petunjuk/scan struk |
| `/saldo` | Saldo per dompet & total |
| `/hariini` `/minggu` `/bulan` | Ringkasan periode |
| `/budget` | Sisa budget per kategori (progress bar teks) |
| `/terakhir` | 5 transaksi terakhir + tombol hapus |
| `/undo` | Hapus transaksi terakhir milik pengirim (≤ 24 jam) |
| `/laporan [bulan]` | Kirim PDF laporan bulanan |
| `/keluarga` | Ganti keluarga aktif (pribadi, jika anggota >1 keluarga) |
| `/bantuan` | Contoh format input |

## 6. Notifikasi Keluar

| Notifikasi | Pemicu | Tujuan |
|---|---|---|
| Budget 80% / 100% | `TransactionCreated` → `CheckBudgetListener` | grup (jika ada) + owner/admin |
| Transaksi baru oleh anggota | opsional (setting) | grup |
| Pengingat tagihan | scheduler harian 07:00 | grup + pembuat rule |
| Ringkasan harian | 21:00 (setting) | grup |
| Ringkasan mingguan | Minggu 20:00 | grup |
| Laporan bulanan | tanggal awal periode 08:00 | grup + owner (PDF) |

Semua dikirim via `SendTelegramMessageJob` (queue `telegram`) dengan rate limit Telegram (≈30 pesan/detik global, 20/menit per grup) memakai `RateLimiter`.

## 7. Struktur Kode

```
app/Telegram/
├── TelegramBot.php              # dispatch update → handler berdasarkan tipe
├── TelegramContext.php
├── Handlers/
│   ├── StartHandler.php
│   ├── LinkGroupHandler.php
│   ├── TextTransactionHandler.php
│   ├── PhotoReceiptHandler.php
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
