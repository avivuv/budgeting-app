# CLAUDE.md

Instruksi wajib untuk AI assistant (Claude Code) di repo ini. Dibaca otomatis setiap sesi.

**Arsitektur API-first:** backend Laravel hanya REST API `/api/v1`; web = SPA Vue di `frontend/`; Android (Kotlin) nanti memakai API yang sama. Saat ini sedang **migrasi dari Inertia** ([docs/18-api-first-migration.md](docs/18-api-first-migration.md)) — kode Inertia lama hanya dipertahankan sampai penggantinya siap, **jangan menambah fitur/halaman Inertia baru**.

**Urutan pengerjaan:** Fase M (migrasi API-first) → Fase 5 → bot Telegram di fase terakhir. Ikuti [docs/09-roadmap.md](docs/09-roadmap.md).

## Project

Aplikasi keuangan keluarga **multi-tenant**: input via **Telegram** (grup & chat pribadi, teks + foto struk), dashboard & laporan via **web SPA** dan nanti **Android** — semua lewat **REST API**. Detail lengkap di [README.md](README.md) dan [docs/](docs/).

## Komunikasi

- Bahasa Indonesia ke user; kode, nama variabel, commit message dalam English.
- Teks yang tampil ke user aplikasi (UI, pesan bot, validasi) dalam Bahasa Indonesia via `lang/id`.

## Stack

Backend: Laravel 13 (PHP 8.4) REST API · Fortify headless (auth, 2FA, passkeys) · Sanctum (cookie SPA + token Android) · Scramble (OpenAPI) · MySQL · Redis (queue/cache) · Pest 5. Frontend `frontend/`: Vue 3 (TS, `<script setup>`) · Vue Router · Pinia · TanStack Query · tipe dari OpenAPI · Tailwind 4 + shadcn-vue (reka-ui) · ApexCharts · Vitest + Playwright · pnpm + Vite+. Activity log di DB terpisah `budgeting_app_log` (retensi 30 hari). Upload via disk `config('filesystems.uploads')` (local → MinIO). Lokal di Laragon (`http://budgeting-app.test`); hosting belum ditentukan.

## Aturan Wajib

1. **Service-Repository pattern** — `Api/V1 Controller | Telegram Handler | Job | Command → Service → Repository (interface di Contracts + Eloquent) → Model`. Tidak ada query Eloquent/`DB` di luar repository. Logika bisnis hanya di Service, dipakai bersama oleh API dan bot. Controller hanya mengembalikan JSON (API Resource) / file — tidak me-render halaman. Perhitungan murni di `app/Support`. Service & DTO dikelompokkan per domain. Dijaga oleh Pest arch test. → [docs/02-architecture.md](docs/02-architecture.md), [docs/16-backend-architecture.md](docs/16-backend-architecture.md)
2. **Multi-tenant** — model data tenant memakai trait `BelongsToFamily` (global scope `family_id`, fail closed). `TenantContext` di-set di middleware/bot/job. `withoutGlobalScope` hanya boleh di repository Super Admin dan method tenant resolver `resolve*` (BR-I07). Data keluarga lain → 404. → [docs/03-multi-tenancy.md](docs/03-multi-tenancy.md)
3. **Business rules** — semua perhitungan & validasi mengikuti [docs/11-business-rules.md](docs/11-business-rules.md) (ID `BR-xxx`). Rule baru/berubah → update dokumen dulu.
4. **Idempoten & teraudit** — setiap konfirmasi/POST pembuat data tidak boleh menghasilkan data ganda (BR-B13, BR-J08); perubahan data penting tercatat di activity log (BR-B14).
5. **Uang = integer rupiah** (`BIGINT`), tidak pernah float. Parsing lewat `AmountParser`, tampilan lewat `MoneyFormatter`/`useMoney`.
6. **Testing wajib** — setiap fitur: tulis dulu skenario positif & negatif di `docs/test-cases/<fitur>.md` (dari form + logika fitur), lalu satu test API per skenario dengan format Given–When–Then (seperti orang memakai aplikasi: input → simpan → cek response, DB, baca ulang; negatif → DB tidak berubah). Ditambah unit test untuk perhitungan dan invariant `INV-xx` bila menyentuh angka. Nama test (English) menyebut ID skenario & rule, mis. `[WAL-01] ... [BR-C01]`. → [docs/12-testing-strategy.md](docs/12-testing-strategy.md), [docs/test-cases/](docs/test-cases/README.md)
7. **UI** — tema putih–biru, pakai token warna (tidak hardcode hex), mobile-first. → [docs/14-ui-design-system.md](docs/14-ui-design-system.md)
8. **Frontend SPA modular** — `frontend/src/core` (api client, ui, layouts, composables, stores) + `frontend/src/modules/<fitur>/` (routes.ts, api.ts, pages, components, `index.ts`). Data hanya lewat REST API (TanStack Query). Antar modul hanya via `index.ts`. Tidak ada perhitungan bisnis di Vue; tipe dari `core/api/schema.d.ts` (OpenAPI). Pilihan dari master data selalu memakai `SearchableSelect` (bisa dicari); `NativeSelect` hanya untuk enum kecil. → [docs/15-frontend-architecture.md](docs/15-frontend-architecture.md)
9. **API-first** — setiap aksi yang ada di web wajib punya endpoint `/api/v1` (agar Android tidak tertinggal); format response/error, `X-Family-Id`, `Idempotency-Key` sesuai [docs/13-rest-api.md](docs/13-rest-api.md).
10. **Coding standards** — `strict_types`, DTO `spatie/laravel-data`, Service tidak `final`, Enum, FormRequest, API Resource, naming sesuai [docs/08-coding-standards.md](docs/08-coding-standards.md).

## Sebelum Selesai / Commit

```bash
composer test        # pint --test + phpstan + pest
./vendor/bin/pint
./vendor/bin/phpstan
pnpm run check && pnpm run types:check && pnpm run test   # selama migrasi: root (Inertia lama) dan frontend/ (SPA)
```

Semua harus hijau. Laporkan hasil apa adanya — jika ada test gagal, sampaikan.

## Dokumen

| Dokumen                                                      | Kapan dibaca                                                        |
| ------------------------------------------------------------ | ------------------------------------------------------------------- |
| [01-product-requirements](docs/01-product-requirements.md)   | memahami fitur & scope                                              |
| [02-architecture](docs/02-architecture.md)                   | membuat class/folder baru                                           |
| [03-multi-tenancy](docs/03-multi-tenancy.md)                 | model, query, role                                                  |
| [04-database-schema](docs/04-database-schema.md)             | migration, model                                                    |
| [05-telegram-bot](docs/05-telegram-bot.md)                   | fitur bot                                                           |
| [06-receipt-scanning](docs/06-receipt-scanning.md)           | fitur struk                                                         |
| [07-dashboard-reports](docs/07-dashboard-reports.md)         | dashboard, laporan, export                                          |
| [08-coding-standards](docs/08-coding-standards.md)           | setiap menulis kode                                                 |
| [09-roadmap](docs/09-roadmap.md)                             | memilih pekerjaan berikutnya — **update checklist setelah selesai** |
| [10-local-development](docs/10-local-development.md)         | setup & menjalankan                                                 |
| [11-business-rules](docs/11-business-rules.md)               | setiap logika/perhitungan                                           |
| [12-testing-strategy](docs/12-testing-strategy.md)           | setiap menulis test                                                 |
| [13-rest-api](docs/13-rest-api.md)                           | endpoint API                                                        |
| [14-ui-design-system](docs/14-ui-design-system.md)           | setiap membuat UI                                                   |
| [15-frontend-architecture](docs/15-frontend-architecture.md) | setiap membuat halaman/komponen Vue                                 |
| [16-backend-architecture](docs/16-backend-architecture.md)   | setiap membuat class backend                                        |
| [17-project-structure](docs/17-project-structure.md)         | menentukan lokasi file baru                                         |
| [18-api-first-migration](docs/18-api-first-migration.md)     | selama migrasi Inertia → API + SPA                                  |
| [test-cases/](docs/test-cases/README.md)                     | sebelum menulis test sebuah fitur                                   |

## Git

- Remote: `git@github-avivuv:avivuv/budgeting-app.git` (SSH alias akun avivuv), branch utama `main`.
- Branch fitur `feature/...`, Conventional Commits. Commit lokal boleh per langkah; **push ke GitHub hanya setelah satu fase roadmap selesai** dan semua gate hijau.
- Jangan commit `.env`, `vendor/`, `node_modules/`, file struk di `storage/`.
