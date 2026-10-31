# 09 — Roadmap

Status: ⬜ belum · 🟨 berjalan · ✅ selesai

Setiap fase dianggap selesai hanya jika memenuhi _Definition of Done_ di [12-testing-strategy.md](12-testing-strategy.md#8-definition-of-done-per-fitur) (unit + API/integration test, BR tercakup, invariant lulus).

**Strategi:**

- **Web & REST API dulu, bot Telegram terakhir.** Semua logika ada di Service, jadi saat bot dibuat, ia tinggal memanggil Service yang sudah teruji.
- **MVP = Fase 1 + 2.** Setelah MVP selesai, aplikasi dipakai keluarga sendiri selama 1–2 minggu. Masukan dari pemakaian itu dipakai untuk menyesuaikan fase berikutnya sebelum lanjut.

## Fase 0 — Dokumentasi & Repo

- ✅ Dokumen product, arsitektur, tenancy, schema, bot, struk, laporan, standar, business rules, testing, API, UI, struktur project
- ✅ CLAUDE.md
- ✅ Push ke GitHub
- ✅ Review desain & perbaikan (tenant resolver, idempotensi, kartu kredit, activity log, backup, export data)

## Fase 1 — Fondasi & Multi-Tenant `MVP`

- 🟨 ✅ Install Laravel 13 + Vue starter kit (Inertia 3 + TS + shadcn-vue + Fortify) · ⬜ restrukturisasi ke `core/` + `modules/`
- 🟨 Tooling: ✅ Pest 5, Larastan, Pint, Vite+ (format+lint), Telescope (dev), `typescript-transformer`, Sanctum · ⬜ ESLint boundaries, Vitest
- ⬜ Base repository, `RepositoryServiceProvider`, `DomainException` + rendering Web/API
- ⬜ `TenantContext`, `BelongsToFamily`, `FamilyScope`, `SetCurrentFamily`, tenant resolver (BR-I07)
- 🟨 Migration ✅ activity_log (DB `budgeting_app_log`, retensi 30 hari) · ⬜ families, family_user, invitations, wallets, categories, tags, transactions
- ⬜ Onboarding: buat keluarga + seeder dompet & kategori default
- ⬜ Undangan anggota & role (Policy, `EnsureFamilyRole`), switch keluarga
- ⬜ CRUD dompet (termasuk kartu kredit, BR-C06), kategori (+ keywords), transaksi (+ transfer, tag)
- ⬜ Activity log transaksi/wallet/kategori + riwayat di detail transaksi (BR-B14)
- ⬜ `AmountParser`, `MoneyFormatter`, `PeriodResolver`, `useMoney`
- ⬜ REST API v1 (Sanctum) wallets, categories, transactions + `Idempotency-Key` (BR-B13)
- ⬜ Testing: arch test, `WithFamily`, golden dataset, `BusinessRuleCoverageTest`, test isolasi tenant & resolver
- ⬜ GitHub Actions CI (MySQL + Redis)
- ⬜ Backup harian (`spatie/laravel-backup`) DB + file

## Fase 2 — Dashboard & Daftar Transaksi `MVP`

- ⬜ Layout (Sidebar, BottomNav, FAB), tema putih–biru, dark mode
- ⬜ `DashboardService` + cache; stat cards, arus kas, donut kategori, tren harian, per anggota, saldo dompet, transaksi terbaru
- ⬜ DataTable server-side (desktop) + list kartu infinite scroll (mobile), filter lengkap
- ⬜ Input cepat di web: field teks bebas `bensin 50rb` → `/transactions/parse` (parser yang sama nanti dipakai bot)
- ⬜ PWA
- ⬜ 🎯 **Uji pakai oleh keluarga sendiri 1–2 minggu → catat masukan**

## Fase 3 — Budget & Laporan

- ⬜ Budget per kategori + salin bulan lalu
- ⬜ `CheckBudgetThresholds` + notifikasi 80%/100% (channel database + email)
- ⬜ `ReportService` + halaman laporan (arus kas, kategori, anggota, dompet, budget, tahunan, merchant, tag)
- ⬜ Export Excel & PDF
- ⬜ API `/summary` & `/reports/*`
- ⬜ Tujuan tabungan
- ⬜ Consistency test INV-01 … INV-13 lulus

## Fase 4 — Deteksi Struk (Web & API)

- ⬜ Migration receipts, receipt_items, usage_logs
- ⬜ `ReceiptParserInterface`, `FakeReceiptParser`, `GeminiReceiptParser` (saat API key tersedia), test kontrak parser
- ⬜ `ReceiptService` (validasi tipe, kuota, resize, hash, duplikat, idempotensi konfirmasi), `ProcessReceiptJob`
- ⬜ Dukungan struk, bukti transfer, screenshot e-wallet, invoice PDF (BR-H11)
- ⬜ Halaman scan → form review → Simpan (BR-H08/H09), "pecah per item", route gambar terproteksi
- ⬜ Draft kedaluwarsa & cleanup (BR-H10)

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

- Deploy (hosting diputuskan kemudian), Pulse/Sentry di production
- Input suara (voice note → teks)
- Paket berlangganan berbasis kuota
- E2E test Playwright
