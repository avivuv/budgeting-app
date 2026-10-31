# Budgeting App — Keuangan Keluarga

Aplikasi pencatatan keuangan keluarga multi-tenant. Input transaksi cukup lewat **Telegram** (chat pribadi atau grup keluarga), termasuk **foto struk**; laporan lengkap dan dashboard tersedia di **web** (mobile-friendly / PWA).

## Ringkasan

| Aspek | Keputusan |
|---|---|
| Backend | Laravel 12, PHP 8.4 |
| Frontend | Inertia.js + Vue 3 + Tailwind CSS + shadcn-vue, ApexCharts |
| Database | MySQL 8 |
| Queue / Cache | Redis |
| Arsitektur | Controller → Service → Repository → Model |
| Tenancy | Single database, kolom `family_id` + global scope |
| Telegram | Bot di grup keluarga & chat pribadi (webhook / polling) |
| Struk | AI Vision (Claude API) → JSON terstruktur |
| API | REST `/api/v1` (Sanctum) |
| Testing | Pest (unit, API, integration, consistency) + Vitest |
| UI | Tema putih–biru, mobile-first |
| Environment | Lokal (Laragon) + ngrok; hosting diputuskan nanti |

## Dokumentasi

| # | Dokumen | Isi |
|---|---|---|
| 1 | [Product Requirements](docs/01-product-requirements.md) | Tujuan, persona, fitur, user story |
| 2 | [Arsitektur](docs/02-architecture.md) | Layer, Service-Repository pattern, struktur folder |
| 3 | [Multi-Tenancy](docs/03-multi-tenancy.md) | Keluarga sebagai tenant, scope, role & izin |
| 4 | [Database Schema](docs/04-database-schema.md) | Tabel, kolom, relasi, index |
| 5 | [Telegram Bot](docs/05-telegram-bot.md) | Linking akun/grup, parser, perintah, notifikasi |
| 6 | [Deteksi Struk](docs/06-receipt-scanning.md) | Alur OCR/AI, prompt, kuota, error handling |
| 7 | [Dashboard & Laporan](docs/07-dashboard-reports.md) | Widget, grafik, laporan, export |
| 8 | [Coding Standards](docs/08-coding-standards.md) | Konvensi kode, naming, testing |
| 9 | [Roadmap](docs/09-roadmap.md) | Fase pengerjaan & checklist |
| 10 | [Local Development](docs/10-local-development.md) | Setup Laragon, env, ngrok, perintah harian |
| 11 | [Business Rules](docs/11-business-rules.md) | Aturan perhitungan & validasi (BR-xxx) |
| 12 | [Testing Strategy](docs/12-testing-strategy.md) | Unit, API, integration, consistency test, golden dataset |
| 13 | [REST API](docs/13-rest-api.md) | Endpoint `/api/v1`, auth, format response |
| 14 | [UI Design System](docs/14-ui-design-system.md) | Tema putih–biru, tipografi, komponen |

## Quick Start

Lihat [docs/10-local-development.md](docs/10-local-development.md).
