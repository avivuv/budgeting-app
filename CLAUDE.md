# CLAUDE.md

Instruksi wajib untuk AI assistant (Claude Code) di repo ini. Dibaca otomatis setiap sesi.

## Project
Aplikasi keuangan keluarga **multi-tenant**: input via **Telegram** (grup & chat pribadi, teks + foto struk), dashboard & laporan via **web**, plus **REST API**. Detail lengkap di [README.md](README.md) dan [docs/](docs/).

## Komunikasi
- Bahasa Indonesia ke user; kode, nama variabel, commit message dalam English.
- Teks yang tampil ke user aplikasi (UI, pesan bot, validasi) dalam Bahasa Indonesia via `lang/id`.

## Stack
Laravel 12 (PHP 8.4) · Inertia v2 + Vue 3 (TS, `<script setup>`) · Tailwind + shadcn-vue · ApexCharts · MySQL · Redis (queue/cache) · Sanctum (API) · Pest + Vitest. Lokal di Laragon (`http://budgeting-app.test`); hosting belum ditentukan.

## Aturan Wajib

1. **Service-Repository pattern** — `Controller (Web/Api) | Telegram Handler | Job | Command → Service → Repository (interface di Contracts + Eloquent) → Model`. Tidak ada query Eloquent/`DB` di luar repository. Logika bisnis hanya di Service, dipakai bersama oleh web, API, dan bot. Perhitungan murni di `app/Support`. Service & DTO dikelompokkan per domain. Dijaga oleh Pest arch test. → [docs/02-architecture.md](docs/02-architecture.md), [docs/16-backend-architecture.md](docs/16-backend-architecture.md)
2. **Multi-tenant** — model data tenant memakai trait `BelongsToFamily` (global scope `family_id`, fail closed). `TenantContext` di-set di middleware/bot/job. Jangan `withoutGlobalScope` kecuali repository Super Admin. Data keluarga lain → 404. → [docs/03-multi-tenancy.md](docs/03-multi-tenancy.md)
3. **Business rules** — semua perhitungan & validasi mengikuti [docs/11-business-rules.md](docs/11-business-rules.md) (ID `BR-xxx`). Rule baru/berubah → update dokumen dulu.
4. **Uang = integer rupiah** (`BIGINT`), tidak pernah float. Parsing lewat `AmountParser`, tampilan lewat `MoneyFormatter`/`useMoney`.
5. **Testing wajib** — setiap fitur: unit test (logika/perhitungan), API/feature test (response, validasi, otorisasi, tenant), dan consistency invariant `INV-xx` bila menyentuh angka. Nama test menyebut ID rule, mis. `[BR-B08]`. → [docs/12-testing-strategy.md](docs/12-testing-strategy.md)
6. **UI** — tema putih–biru, pakai token warna (tidak hardcode hex), mobile-first. → [docs/14-ui-design-system.md](docs/14-ui-design-system.md)
7. **Frontend modular** — `resources/js/core/` (shared) + `resources/js/modules/<fitur>/` (pages, components, composables, `index.ts`). Antar modul hanya via `index.ts`. Tidak ada perhitungan bisnis di Vue; tipe dari `generated.d.ts`. → [docs/15-frontend-architecture.md](docs/15-frontend-architecture.md)
8. **Coding standards** — `strict_types`, DTO readonly, Enum, FormRequest, API Resource, naming sesuai [docs/08-coding-standards.md](docs/08-coding-standards.md).

## Sebelum Selesai / Commit
```bash
php artisan test --parallel
./vendor/bin/pint
./vendor/bin/phpstan
npm run test && npm run type-check
```
Semua harus hijau. Laporkan hasil apa adanya — jika ada test gagal, sampaikan.

## Dokumen
| Dokumen | Kapan dibaca |
|---|---|
| [01-product-requirements](docs/01-product-requirements.md) | memahami fitur & scope |
| [02-architecture](docs/02-architecture.md) | membuat class/folder baru |
| [03-multi-tenancy](docs/03-multi-tenancy.md) | model, query, role |
| [04-database-schema](docs/04-database-schema.md) | migration, model |
| [05-telegram-bot](docs/05-telegram-bot.md) | fitur bot |
| [06-receipt-scanning](docs/06-receipt-scanning.md) | fitur struk |
| [07-dashboard-reports](docs/07-dashboard-reports.md) | dashboard, laporan, export |
| [08-coding-standards](docs/08-coding-standards.md) | setiap menulis kode |
| [09-roadmap](docs/09-roadmap.md) | memilih pekerjaan berikutnya — **update checklist setelah selesai** |
| [10-local-development](docs/10-local-development.md) | setup & menjalankan |
| [11-business-rules](docs/11-business-rules.md) | setiap logika/perhitungan |
| [12-testing-strategy](docs/12-testing-strategy.md) | setiap menulis test |
| [13-rest-api](docs/13-rest-api.md) | endpoint API |
| [14-ui-design-system](docs/14-ui-design-system.md) | setiap membuat UI |
| [15-frontend-architecture](docs/15-frontend-architecture.md) | setiap membuat halaman/komponen Vue |
| [16-backend-architecture](docs/16-backend-architecture.md) | setiap membuat class backend |
| [17-project-structure](docs/17-project-structure.md) | menentukan lokasi file baru |

## Git
- Remote: `https://github.com/avivuv/budgeting-app.git`, branch utama `main`.
- Branch fitur `feature/...`, Conventional Commits. Commit/push hanya bila diminta user.
- Jangan commit `.env`, `vendor/`, `node_modules/`, file struk di `storage/`.
