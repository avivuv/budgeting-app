# 09 — Roadmap

Status: ⬜ belum · 🟨 berjalan · ✅ selesai

Setiap fase dianggap selesai hanya jika memenuhi _Definition of Done_ di [12-testing-strategy.md](12-testing-strategy.md#8-definition-of-done-per-fitur) (unit + API/integration test, BR tercakup, invariant lulus).

**Strategi:**

- **API-first.** Backend hanya REST API; web SPA dan Android (nanti) adalah klien. Fase 1–4 dibangun dengan Inertia dan dipindahkan di **Fase M** sebelum fitur baru ([18-api-first-migration.md](18-api-first-migration.md)).
- **Bot Telegram terakhir.** Semua logika ada di Service, jadi saat bot dibuat, ia tinggal memanggil Service yang sudah teruji.
- **MVP = Fase 1 + 2.** Setelah MVP selesai, aplikasi dipakai keluarga sendiri selama 1–2 minggu. Masukan dari pemakaian itu dipakai untuk menyesuaikan fase berikutnya sebelum lanjut.

## Fase 0 — Dokumentasi & Repo

- ✅ Dokumen product, arsitektur, tenancy, schema, bot, struk, laporan, standar, business rules, testing, API, UI, struktur project
- ✅ CLAUDE.md
- ✅ Push ke GitHub
- ✅ Review desain & perbaikan (tenant resolver, idempotensi, kartu kredit, activity log, backup, export data)

## Fase 1 — Fondasi & Multi-Tenant `MVP` ✅ selesai 31 Okt 2026

- ✅ Laravel 13 + Vue starter kit (Inertia 3 + TS + shadcn-vue + Fortify: 2FA, passkeys); fitur di `modules/<fitur>/`, folder shared starter kit = core
- ✅ Tooling: Pest 5, Larastan, Pint, Vite+ (format + lint), Vitest (`vp test`), Telescope (dev), `typescript-transformer`, Wayfinder, Sanctum, Mailpit (lokal)
- ✅ Base repository, `RepositoryServiceProvider`, `DomainException`/`BusinessRuleException` + rendering Web/API
- ✅ `TenantContext`, `BelongsToFamily`, `FamilyScope` (fail closed), `SetCurrentFamily` (+ `X-Family-Id`), tenant resolver undangan (BR-I07)
- ✅ Migration: families, family_user, invitations, wallets, categories, tags, transactions, users.appearance, activity_log (DB `budgeting_app_log`, retensi 30 hari)
- ✅ Onboarding: buat keluarga + dompet & kategori default
- ✅ Role & Policy (BR-B09), switch keluarga, undangan via email, kelola peran & anggota (BR-I01..I04)
- ✅ CRUD dompet (termasuk kartu kredit, BR-C06; peringatan saldo minus BR-C03), kategori (+ keywords, arsip, gabung), transaksi (+ transfer, tag)
- ✅ Activity log (BR-B14) + riwayat perubahan di halaman edit transaksi
- ✅ `AmountParser`, `MoneyFormatter`, `Percentage`, `PeriodResolver`
- ✅ Tema putih–biru + toggle terang/gelap/sistem tersimpan di akun; pilihan master data bisa dicari (`SearchableSelect`)
- ✅ REST API v1 (Sanctum, abilities `read`/`write`) wallets, categories, transactions + `Idempotency-Key` (BR-B13), halaman Token API
- ✅ Testing: golden dataset, isolasi tenant, matriks role, API, arch test, `BusinessRuleCoverageTest` (BR fase berikutnya tercatat sebagai _pending_), Vitest batas modul — 284 test Pest + 6 Vitest
- ✅ GitHub Actions CI (PHP 8.4, MySQL, pnpm, semua gate)
- ✅ Backup harian (`spatie/laravel-backup`) kedua database + file upload, terjadwal 02:00

## Fase 2 — Dashboard & Daftar Transaksi `MVP` 🟨 fitur selesai 2 Nov 2026, menunggu uji pakai

- ✅ Layout: sidebar (desktop), bottom nav + FAB "+" (HP), tombol "+" melayang (desktop); tema putih–biru & dark mode dari Fase 1
- ✅ `DashboardService` + cache per widget (5 menit, invalidasi lewat event transaksi/dompet/kategori — BR-C04); ringkasan + Δ% vs periode lalu, arus kas 6 periode, donut kategori (sub-kategori digabung ke induk, BR-E04), tren harian kumulatif, per anggota, saldo dompet, transaksi terbaru, insight; filter periode/dompet/anggota; deferred props
- ✅ Daftar transaksi: filter lengkap di query string (web & API sama), total & subtotal harian dari server (INV-06), tabel + urut + aksi massal (desktop), kartu per tanggal + infinite scroll (HP)
- ✅ Input cepat: "Ketik cepat" di form → `POST /transactions/parse` (+ `/api/v1/transactions/parse`); `TransactionTextParser` + `TransactionParser` siap dipakai bot
- ✅ PWA: manifest, ikon, service worker (aset saja), halaman offline
- ✅ `DemoSeeder` untuk data contoh lokal
- ⬜ 🎯 **Uji pakai oleh keluarga sendiri 1–2 minggu → catat masukan**

## Fase 3 — Budget & Laporan ✅ selesai 2 Nov 2026

- ✅ Budget per kategori (periode = bulan label) + salin periode lalu (BR-F01…F07); halaman Budget, kartu & insight di dashboard
- ✅ `CheckBudgetThresholds` + notifikasi 80%/100% sekali per periode, reset bila turun (channel database + email); lonceng & halaman notifikasi
- ✅ `ReportService` + halaman laporan: arus kas (saldo awal–akhir), kategori (+ sub, vs periode lalu), anggota, dompet (mutasi), budget, tahunan (heatmap), merchant, tag
- ✅ Export Excel (Ringkasan, laporan, Transaksi, Kategori, Budget) & PDF dari tabel yang sama — langsung diunduh (export latar belakang/`GenerateReportJob` belum perlu untuk ukuran data keluarga)
- ✅ API `/summary`, `/reports/{laporan}`, `GET/PUT /budgets`
- ✅ Tujuan tabungan (BR-K01…K04): dari saldo dompet atau setoran manual, setoran per bulan sampai tenggat
- ✅ Consistency test INV-01 … INV-13 lulus (INV-09 struk menyusul di Fase 4), dijaga `InvariantCoverageTest`
- ✅ E2E Playwright (`pnpm run test:e2e`) + API round-trip test

## Fase 4 — Deteksi Struk (Web & API) ✅ selesai 3 Nov 2026

- ✅ Migration receipts, receipt_items, usage_logs
- ✅ `ReceiptParserInterface`, `FakeReceiptParser` (default), `GeminiReceiptParser` (+ fallback model), test kontrak parser dengan `Http::fake()` — tinggal isi `GEMINI_API_KEY` & `RECEIPT_PARSER=gemini`
- ✅ `ReceiptService` (validasi tipe/ukuran/halaman PDF, kuota bulanan, resize ke JPEG 1600px, sha256, peringatan duplikat, konfirmasi idempoten dengan lock), `ProcessReceiptJob` (retry 3×, rate limit, kuota dikembalikan bila gagal sistem)
- ✅ Dukungan struk, bukti transfer, screenshot e-wallet, invoice PDF (BR-H11); HEIC disimpan apa adanya (tanpa resize)
- ✅ Halaman Scan struk (kamera HP / pilih file / drag & drop) → form review dengan polling → Simpan atau "Pecah per item" (BR-H08/H09, BR-H02), route gambar terproteksi
- ✅ API `POST/GET/PATCH/DELETE /receipts`, `POST /receipts/{id}/confirm`
- ✅ `receipts:expire` tiap jam: draft kedaluwarsa 24 jam, file dihapus setelah 7 hari (BR-H10)
- ✅ INV-09 & BR-H01…H11 dites (unit, feature, API round-trip, E2E)
- ⬜ 🎯 Coba dengan struk asli setelah API key Gemini tersedia → catat akurasi

## Fase M — Migrasi API-first 🟨 mulai 3 Nov 2026

Detail & alasan: [18-api-first-migration.md](18-api-first-migration.md). Web Inertia lama tetap jalan sampai pengganti SPA-nya siap.

- ✅ **M0 Dokumen** — keputusan arsitektur, semua dokumen diperbarui ke API-first, format test case per fitur ([test-cases](test-cases/README.md)), `composer test:api` (trace input/output)
- ⬜ **M1 Fondasi API** — Fortify headless + Sanctum stateful, `/api/v1/auth/*` (SPA & token Android), error JSON + `rule`, OpenAPI (Scramble), test skenario auth
- ⬜ **M2 API lengkap + test skenario per fitur** (dokumen skenario direview dulu, test API lama dirombak)
    - ⬜ Dompet ([skenario](test-cases/wallets.md) draft)
    - ⬜ Kategori
    - ⬜ Transaksi
    - ⬜ Budget
    - ⬜ Ringkasan, Dashboard, Laporan & Export
    - ⬜ Tabungan
    - ⬜ Scan Struk
    - ⬜ Keluarga, Anggota & Undangan
    - ⬜ Onboarding
    - ⬜ Notifikasi
    - ⬜ Profil, Keamanan & Token
- ⬜ **M3 Kerangka SPA** — `frontend/`, router, auth pages, layout, switch keluarga, tema, PWA
- ⬜ **M4 Pindah halaman per fitur** — halaman SPA + E2E → hapus halaman, controller, route & test Inertia-nya
- ⬜ **M5 Bersih-bersih** — hapus Inertia, Wayfinder, `resources/js`; update CI & dokumen
- 🎯 Setelah M2: API lengkap → aplikasi Android bisa mulai dikerjakan

## Fase 5 — Otomasi, Data & Super Admin

- ⬜ Transaksi berulang + pengingat tagihan
- ⬜ Ringkasan mingguan/bulanan terjadwal (email/web)
- ⬜ Export seluruh data keluarga & hapus keluarga/akun (BR-I08)
- ⬜ Panel Super Admin (keluarga, kuota, suspend)
- ⬜ Cleanup terjadwal (struk ditolak/kedaluwarsa, keluarga dihapus)

## Fase 6 — Telegram Bot

- ⬜ Buat 2 bot (dev & production), konfigurasi `@BotFather`
- ⬜ Webhook + polling, `VerifyTelegramSecret`, dedupe `update_id` → `ProcessTelegramUpdateJob` (BR-J08)
- ⬜ Linking akun pribadi (deep link + QR) & grup (`/hubungkan`), `telegram_default_family_id` (BR-J09)
- ⬜ `TelegramContext` resolution
- ⬜ Input teks (pakai `TransactionParser` dari Fase 2) + preview + inline keyboard + konfirmasi idempoten
- ⬜ Koreksi lewat reply (BR-J10)
- ⬜ Struk/bukti transaksi via foto & dokumen (pakai `ReceiptService` dari Fase 4)
- ⬜ Perintah: `/c`, `/saldo`, `/hariini`, `/minggu`, `/bulan`, `/budget`, `/terakhir`, `/undo`, `/laporan`, `/keluarga`, `/bantuan`
- ⬜ Perilaku grup (mention/reply/`/c`, tombol hanya untuk pengirim)
- ⬜ `TelegramChannel` untuk notifikasi yang sudah ada (budget, pengingat, ringkasan)

## Nanti

- Aplikasi Android native (Kotlin) memakai REST API + client dari OpenAPI; push notification (FCM)
- Deploy (hosting diputuskan kemudian), Pulse/Sentry di production
- Input suara (voice note → teks)
- Paket berlangganan berbasis kuota
- `ClaudeReceiptParser` bila Gemini kurang akurat
