# 17 — Project Structure

Satu repository berisi dua aplikasi yang hanya terhubung lewat **REST API `/api/v1`**:

- **Backend** — Laravel di root repo: REST API, auth (Fortify headless + Sanctum), queue, scheduler, webhook Telegram. Tidak me-render halaman.
- **Frontend web** — SPA Vue 3 di `frontend/`, di-build ke `public/app/` dan dilayani di domain yang sama.

Aplikasi **Android (Kotlin)** akan menjadi klien ketiga di repo terpisah, memakai API & spec OpenAPI yang sama. Alasan arsitektur: [18-api-first-migration.md](18-api-first-migration.md).

## 1. Root

```
budgeting-app/
├── app/                      # ⚙️ BACKEND — kode aplikasi Laravel
├── bootstrap/                # app.php (middleware, exception rendering JSON, routing)
├── config/                   # konfigurasi + telegram.php, receipt.php, tenancy.php, scramble.php
├── database/                 # migrations, factories, seeders
├── docs/                     # 📚 dokumentasi project + docs/test-cases (skenario per fitur)
├── frontend/                 # 🖥️ FRONTEND — SPA Vue 3 + TypeScript (package.json sendiri)
├── lang/id/                  # teks Bahasa Indonesia (validasi, pesan API, bot)
├── public/                   # index.php + public/app (build SPA, di-ignore git)
├── resources/
│   ├── prompts/              # prompt AI (receipt.md)
│   └── views/                # Blade: template PDF & email saja
├── routes/                   # api, telegram, console (+ spa fallback)
├── storage/                  # log, cache, file struk privat
├── tests/                    # 🧪 Pest (backend)
├── .github/workflows/        # CI backend + frontend
├── CLAUDE.md  README.md
├── composer.json  phpunit.xml  phpstan.neon  pint.json
└── .env.example  .gitignore  .editorconfig
```

## 2. Backend (`app/`)

```
app/
├── Clients/                  # wrapper API eksternal
│   ├── Telegram/
│   └── Ai/
├── Console/Commands/         # telegram:poll, telegram:set-webhook, recurring:run, receipts:expire
├── DTOs/{Domain}/            # *Data (spatie/laravel-data)
├── Enums/
├── Events/  Listeners/
├── Exceptions/{Domain}/      # turunan DomainException
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/{Domain}/  # REST API controllers (satu-satunya pintu HTTP untuk klien)
│   │   └── Telegram/         # WebhookController
│   ├── Middleware/
│   ├── Requests/Api/V1/{Domain}/
│   └── Resources/{Domain}/   # API Resource (bentuk JSON + sumber schema OpenAPI)
├── Jobs/
├── Models/  Models/Concerns/  Models/Scopes/
├── Policies/
├── Providers/                # App, Repository, Event, Fortify
├── Repositories/
│   ├── Contracts/            # *RepositoryInterface
│   ├── Eloquent/             # *Repository
│   └── Cache/                # decorator cache
├── Services/{Domain}/        # logika bisnis
├── Support/{Money,Period,Budget,Receipt,Math}/   # kalkulator murni
└── Telegram/                 # Bot router, Context, Handlers, Parsers, Keyboards, Messages
```

Detail peran tiap folder: [16-backend-architecture.md](16-backend-architecture.md).

**Domain** yang dipakai konsisten di semua folder ber-`{Domain}`: `Auth`, `Family`, `Wallet`, `Category`, `Transaction`, `Budget`, `Saving`, `Recurring`, `Receipt`, `Report`, `Notification`, `Settings`, `Telegram`, `Admin`.

## 3. Frontend (`frontend/`)

```
frontend/
├── index.html  vite.config.ts  tsconfig.json  package.json
├── public/                   # manifest, sw.js, offline.html, ikon
└── src/
    ├── main.ts
    ├── router/
    ├── core/{api,components,layouts,composables,stores,lib}/
    └── modules/
        └── {feature}/        # auth, onboarding, dashboard, transactions, receipts, wallets,
            ├── routes.ts     # categories, budgets, savings, recurring, reports, family,
            ├── api.ts        # notifications, telegram, settings, admin
            ├── pages/
            ├── components/
            ├── composables/
            ├── __tests__/
            └── index.ts
```

Detail: [15-frontend-architecture.md](15-frontend-architecture.md).

## 4. Pendukung

```
config/
├── telegram.php              # token, username, secret, mode
├── receipt.php               # driver, model, rate, quota default
├── tenancy.php               # default kategori/dompet, batas
└── scramble.php              # dokumentasi OpenAPI /docs/api

database/
├── migrations/               # urutan sesuai 04-database-schema.md
├── factories/                # semua model, dengan state (expense(), income(), transfer())
└── seeders/  (DatabaseSeeder, DemoSeeder, data/default_categories.php)

resources/views/
├── pdf/reports/              # template laporan PDF
└── emails/                   # undangan, dll

routes/
├── api.php                   # /api/v1 (auth, me, families, wallets, ... per domain)
├── telegram.php              # POST /telegram/webhook
├── spa.php                   # fallback: semua path non-API → public/app/index.html
└── console.php               # jadwal scheduler

storage/app/private/receipts/{family_id}/{yyyy}/{mm}/

docs/test-cases/              # skenario positif & negatif per fitur (sumber test API)

tests/
├── Architecture/
├── Unit/{Support,Services,Telegram}/
├── Feature/{Api/V1,Telegram,Integration,Tenancy,Consistency}/
├── Fixtures/{telegram,receipts}/  GoldenFamily.php
└── Concerns/                 # WithFamily, ActsAsTelegram

frontend/tests/e2e/           # Playwright (SPA → API → DB)

.github/workflows/
└── tests.yml                 # PHP + Node, MySQL + Redis service, semua quality gate
```

## 5. Satu Fitur = File di Mana Saja?

Contoh fitur **Budget**:

| Lapisan                     | File                                                                                                                                                                                                            |
| --------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Rule                        | `docs/11-business-rules.md` (BR-F**)                                                                                                                                                                            |
| Skenario test               | `docs/test-cases/budgets.md`                                                                                                                                                                                    |
| Migration / Model / Factory | `database/migrations/*_create_budgets_table.php`, `app/Models/Budget.php`, `database/factories/BudgetFactory.php`                                                                                               |
| Repository                  | `app/Repositories/Contracts/BudgetRepositoryInterface.php`, `app/Repositories/Eloquent/BudgetRepository.php`                                                                                                    |
| Kalkulator murni            | `app/Support/Budget/BudgetCalculator.php`                                                                                                                                                                       |
| Service                     | `app/Services/Budget/BudgetService.php`                                                                                                                                                                         |
| DTO                         | `app/DTOs/Budget/BudgetData.php`, `BudgetProgressData.php`                                                                                                                                                      |
| Event/Listener              | `app/Listeners/CheckBudgetThresholds.php`                                                                                                                                                                       |
| Request / Policy            | `app/Http/Requests/Api/V1/Budget/*`, `app/Policies/BudgetPolicy.php`                                                                                                                                            |
| Controller / Resource       | `app/Http/Controllers/Api/V1/Budget/BudgetController.php`, `app/Http/Resources/Budget/BudgetResource.php`                                                                                                       |
| Bot                         | `app/Telegram/Handlers/Commands/BudgetCommand.php`                                                                                                                                                              |
| Route                       | `routes/api.php` (grup `budgets`)                                                                                                                                                                               |
| Frontend                    | `frontend/src/modules/budgets/{routes.ts,api.ts,pages,components,index.ts}`                                                                                                                                     |
| Test                        | `tests/Unit/Support/BudgetCalculatorTest.php`, `tests/Feature/Api/V1/BudgetTest.php` (skenario), `tests/Feature/Consistency/*`, `frontend/src/modules/budgets/__tests__/*`, `frontend/tests/e2e/budgets.e2e.ts` |

## 6. Alias & Namespace

| Konteks   | Alias                                                                 |
| --------- | --------------------------------------------------------------------- |
| PHP       | PSR-4 `App\` → `app/`, `Database\` → `database/`, `Tests\` → `tests/` |
| TS / Vite | `@/core` → `frontend/src/core`, `@/modules` → `frontend/src/modules`  |
