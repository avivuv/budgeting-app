# 09 — Roadmap

Status: ⬜ belum · 🟨 berjalan · ✅ selesai

Setiap fase dianggap selesai hanya jika memenuhi *Definition of Done* di [12-testing-strategy.md](12-testing-strategy.md#8-definition-of-done-per-fitur) (unit + API/integration test, BR tercakup, invariant lulus).

## Fase 0 — Dokumentasi & Repo
- ✅ Dokumen product, arsitektur, tenancy, schema, bot, struk, laporan, standar, business rules, testing, API, UI
- ✅ CLAUDE.md
- ✅ Push ke GitHub

## Fase 1 — Fondasi & Multi-Tenant
- ⬜ Install Laravel 12 + Breeze (Vue + Inertia + TypeScript), Pest, Pint, Larastan
- ⬜ Setup shadcn-vue, Tailwind, layout (Sidebar, BottomNav, FAB), dark mode
- ⬜ Base repository (`BaseRepositoryInterface`, `BaseRepository`), `RepositoryServiceProvider`
- ⬜ `TenantContext`, `BelongsToFamily`, `FamilyScope`, middleware `SetCurrentFamily`
- ⬜ Migration families, family_user, invitations, wallets, categories, tags, transactions
- ⬜ Onboarding: buat keluarga + seeder dompet & kategori default
- ⬜ Undangan anggota & role (Policy, `EnsureFamilyRole`)
- ⬜ Switch keluarga
- ⬜ CRUD dompet, kategori (+ keywords), transaksi (+ transfer, tag)
- ⬜ `AmountParser`, `MoneyFormatter`, `useMoney`
- ⬜ REST API v1 (Sanctum) untuk wallets, categories, transactions + API Resource
- ⬜ Setup testing: Pest, arch test, `WithFamily`, golden dataset, `BusinessRuleCoverageTest`
- ⬜ Unit & API test BR-A, BR-B, BR-C, BR-E, BR-I; test isolasi tenant
- ⬜ GitHub Actions CI (MySQL + Redis)

## Fase 2 — Telegram Bot
- ⬜ Konfigurasi bot, `telegram:set-webhook`, `telegram:poll`, `VerifyTelegramSecret`
- ⬜ Linking akun pribadi (deep link + QR) & grup (`/hubungkan`)
- ⬜ `TelegramContext` resolution
- ⬜ `TransactionParser` (nominal, tipe, dompet, tanggal, tag, kategori, multi-baris) + unit test
- ⬜ Preview + inline keyboard + callback (simpan, ganti kategori/dompet/tanggal, batal)
- ⬜ Perintah: `/c`, `/saldo`, `/hariini`, `/minggu`, `/bulan`, `/terakhir`, `/undo`, `/keluarga`, `/bantuan`
- ⬜ Perilaku grup (mention/reply/`/c`, tombol hanya untuk pengirim)

## Fase 3 — Deteksi Struk
- ⬜ Migration receipts, receipt_items, usage_logs
- ⬜ `ReceiptParserInterface`, `FakeReceiptParser`, `ClaudeReceiptParser`
- ⬜ `ReceiptService` (kuota, resize, hash, duplikat), `ProcessReceiptJob`
- ⬜ Handler foto Telegram + preview + "pecah per item"
- ⬜ Halaman scan struk di web + route gambar terproteksi

## Fase 4 — Dashboard
- ⬜ `DashboardService` + cache
- ⬜ Stat cards, arus kas, donut kategori, tren harian, per anggota, saldo dompet, transaksi terbaru, insight
- ⬜ Daftar transaksi dengan filter lengkap
- ⬜ PWA

## Fase 5 — Laporan & Budget
- ⬜ Budget per kategori + salin bulan lalu
- ⬜ `CheckBudgetListener` + notifikasi 80%/100% ke Telegram
- ⬜ `ReportService` + halaman laporan (arus kas, kategori, anggota, dompet, budget, tahunan, merchant, tag)
- ⬜ Export Excel & PDF, `/laporan` di bot
- ⬜ API `/summary` & `/reports/*`
- ⬜ Consistency test INV-01 … INV-10 lulus
- ⬜ Tujuan tabungan

## Fase 6 — Otomasi & Super Admin
- ⬜ Transaksi berulang + pengingat tagihan
- ⬜ Ringkasan harian/mingguan/bulanan terjadwal
- ⬜ Panel Super Admin (keluarga, kuota, suspend)
- ⬜ Cleanup terjadwal (struk ditolak, keluarga dihapus)

## Nanti
- Deploy (hosting diputuskan kemudian), backup otomatis
- Input suara (voice note → teks)
- Paket berlangganan berbasis kuota
