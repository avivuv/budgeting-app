# Budgeting App — Keuangan Keluarga

Aplikasi pencatatan keuangan keluarga multi-tenant. Input transaksi cukup lewat **Telegram** (chat pribadi atau grup keluarga), termasuk **foto struk**; laporan lengkap dan dashboard tersedia di **web** (SPA, mobile-friendly / PWA) dan nanti **aplikasi Android** — keduanya memakai REST API yang sama.

## Ringkasan

| Aspek               | Keputusan                                                                             |
| ------------------- | ------------------------------------------------------------------------------------- |
| Backend             | Laravel 13, PHP 8.4 — **REST API only**; Fortify headless (auth, 2FA, passkeys)       |
| Frontend web        | SPA Vue 3 (`frontend/`) + Vue Router + TanStack Query + Tailwind + shadcn-vue         |
| Mobile              | Android native (Kotlin) — direncanakan, memakai API yang sama                         |
| Database            | MySQL 8                                                                               |
| Queue / Cache       | Redis                                                                                 |
| Arsitektur          | API-first: klien → `/api/v1` → Controller → Service → Repository → Model              |
| Tenancy             | Single database, kolom `family_id` + global scope                                     |
| Telegram            | Bot di grup keluarga & chat pribadi (webhook / polling) — dikerjakan di fase terakhir |
| Struk               | AI Vision (rencana: Google Gemini free tier; driver bisa diganti) → JSON terstruktur  |
| Frontend arsitektur | Modular per fitur (`frontend/src/modules/*`)                                          |
| API                 | REST `/api/v1` (Sanctum: cookie untuk SPA, token untuk Android), OpenAPI otomatis     |
| Testing             | Pest (test skenario per fitur lewat API, unit, consistency) + Vitest + Playwright     |
| UI                  | Tema putih–biru, mobile-first                                                         |
| Environment         | Lokal (Laragon) + ngrok; hosting diputuskan nanti                                     |

## Dokumentasi

| #   | Dokumen                                                   | Isi                                                                     |
| --- | --------------------------------------------------------- | ----------------------------------------------------------------------- |
| 1   | [Product Requirements](docs/01-product-requirements.md)   | Tujuan, persona, fitur, user story                                      |
| 2   | [Arsitektur](docs/02-architecture.md)                     | API-first, layer, Service-Repository pattern, struktur folder           |
| 3   | [Multi-Tenancy](docs/03-multi-tenancy.md)                 | Keluarga sebagai tenant, scope, role & izin                             |
| 4   | [Database Schema](docs/04-database-schema.md)             | Tabel, kolom, relasi, index                                             |
| 5   | [Telegram Bot](docs/05-telegram-bot.md)                   | Linking akun/grup, parser, perintah, notifikasi                         |
| 6   | [Deteksi Struk](docs/06-receipt-scanning.md)              | Alur OCR/AI, prompt, kuota, error handling                              |
| 7   | [Dashboard & Laporan](docs/07-dashboard-reports.md)       | Widget, grafik, laporan, export                                         |
| 8   | [Coding Standards](docs/08-coding-standards.md)           | Konvensi kode, naming, testing                                          |
| 9   | [Roadmap](docs/09-roadmap.md)                             | Fase pengerjaan & checklist                                             |
| 10  | [Local Development](docs/10-local-development.md)         | Setup Laragon, env, ngrok, perintah harian                              |
| 11  | [Business Rules](docs/11-business-rules.md)               | Aturan perhitungan & validasi (BR-xxx)                                  |
| 12  | [Testing Strategy](docs/12-testing-strategy.md)           | Test skenario API, unit, consistency test, golden dataset, E2E          |
| 13  | [REST API](docs/13-rest-api.md)                           | Endpoint `/api/v1`, auth, format response                               |
| 14  | [UI Design System](docs/14-ui-design-system.md)           | Tema putih–biru, tipografi, komponen                                    |
| 15  | [Frontend Architecture](docs/15-frontend-architecture.md) | SPA Vue: modul, client API, state, type safety, aturan import           |
| 16  | [Backend Architecture](docs/16-backend-architecture.md)   | Service-Repository detail, folder per domain, DTO, exception, arch test |
| 17  | [Project Structure](docs/17-project-structure.md)         | Struktur folder lengkap backend & frontend, peta file per fitur         |
| 18  | [Migrasi API-first](docs/18-api-first-migration.md)       | Keputusan & tahapan pindah ke backend API + SPA + Android               |
| —   | [Test Cases](docs/test-cases/README.md)                   | Skenario positif & negatif per fitur (sumber test API)                  |

## Quick Start

Lihat [docs/10-local-development.md](docs/10-local-development.md).
