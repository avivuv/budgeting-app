# 17 — Project Structure

Satu repository (monolith Inertia): **backend Laravel** dan **frontend Vue** hidup di project yang sama, dibangun oleh Vite, dan di-deploy sebagai satu aplikasi. Pemisahan dilakukan di level folder, bukan di level repo.

Alasan: satu bahasa routing & auth (session Laravel), tidak perlu CORS/token untuk web, tipe TypeScript di-generate langsung dari PHP, satu pipeline CI. REST API `/api/v1` tetap tersedia untuk client lain.

## 1. Root

```
budgeting-app/
├── app/                      # ⚙️ BACKEND — kode aplikasi Laravel
├── bootstrap/                # app.php (middleware, exception rendering, routing)
├── config/                   # konfigurasi + telegram.php, receipt.php, tenancy.php
├── database/                 # migrations, factories, seeders
├── docs/                     # 📚 dokumentasi project
├── lang/id/                  # teks Bahasa Indonesia (validasi, pesan, bot)
├── public/                   # entry web + build Vite (public/build, di-ignore git)
├── resources/
│   ├── css/                  # 🎨 Tailwind + token tema
│   ├── js/                   # 🖥️ FRONTEND — Vue 3 + TypeScript
│   ├── prompts/              # prompt AI (receipt.md)
│   └── views/                # Blade: root Inertia, template PDF, email
├── routes/                   # web, api, telegram, console
├── storage/                  # log, cache, file struk privat
├── tests/                    # 🧪 Pest (backend) — Vitest ada di resources/js
├── .github/workflows/        # CI
├── CLAUDE.md  README.md
├── composer.json  package.json
├── vite.config.ts  tsconfig.json  eslint.config.js  .prettierrc
├── phpunit.xml  phpstan.neon  pint.json  vitest.config.ts
└── .env.example  .gitignore  .editorconfig
```

## 2. Backend (`app/`)

```
app/
├── Clients/                  # wrapper API eksternal
│   ├── Telegram/
│   └── Ai/
├── Console/Commands/         # telegram:poll, telegram:set-webhook, recurring:run, receipts:expire
├── DTOs/{Domain}/            # *Data (spatie/laravel-data, #[TypeScript])
├── Enums/
├── Events/  Listeners/
├── Exceptions/{Domain}/      # turunan DomainException
├── Http/
│   ├── Controllers/
│   │   ├── Web/{Domain}/     # Inertia controllers
│   │   ├── Api/V1/           # REST API controllers
│   │   └── Telegram/         # WebhookController
│   ├── Middleware/
│   ├── Requests/{Web,Api/V1,Concerns}/
│   └── Resources/            # API Resource
├── Jobs/
├── Models/  Models/Concerns/  Models/Scopes/
├── Policies/
├── Providers/                # App, Repository, Event
├── Repositories/
│   ├── Contracts/            # *RepositoryInterface
│   ├── Eloquent/             # *Repository
│   └── Cache/                # decorator cache
├── Services/{Domain}/        # logika bisnis
├── Support/{Money,Period,Budget,Receipt,Math}/   # kalkulator murni
└── Telegram/                 # Bot router, Context, Handlers, Parsers, Keyboards, Messages
```
Detail peran tiap folder: [16-backend-architecture.md](16-backend-architecture.md).

**Domain** yang dipakai konsisten di semua folder ber-`{Domain}`: `Family`, `Wallet`, `Category`, `Transaction`, `Budget`, `Saving`, `Recurring`, `Receipt`, `Report`, `Telegram`, `Admin`.

## 3. Frontend (`resources/js/`)

```
resources/js/
├── app.ts                    # bootstrap Inertia + page resolver
├── core/
│   ├── components/{ui,common,charts,form,data-table}/
│   ├── layouts/  layouts/partials/
│   ├── composables/
│   ├── lib/
│   ├── plugins/
│   ├── i18n/
│   └── types/                # generated.d.ts (auto), inertia.d.ts, global.d.ts
└── modules/
    └── {feature}/            # auth, onboarding, dashboard, transactions, receipts, wallets,
        ├── pages/            # categories, budgets, savings, recurring, reports, family,
        ├── components/       # telegram, settings, admin
        ├── composables/
        ├── constants.ts
        ├── types.ts
        ├── __tests__/
        └── index.ts
```
Detail: [15-frontend-architecture.md](15-frontend-architecture.md).

## 4. Pendukung

```
config/
├── telegram.php              # token, username, secret, mode
├── receipt.php               # driver, model, rate, quota default
└── tenancy.php               # default kategori/dompet, batas

database/
├── migrations/               # urutan sesuai 04-database-schema.md
├── factories/                # semua model, dengan state (expense(), income(), transfer())
└── seeders/
    ├── DatabaseSeeder.php
    ├── DemoSeeder.php
    └── data/default_categories.php

resources/views/
├── app.blade.php             # root Inertia
├── pdf/reports/              # template laporan PDF
└── emails/                   # undangan, dll

routes/
├── web.php                   # memuat routes/web/*.php
├── web/                      # dipecah per domain: transactions.php, budgets.php, reports.php, ...
├── api.php                   # /api/v1 → routes/api/v1/*.php
├── api/v1/
├── telegram.php              # POST /telegram/webhook
└── console.php               # jadwal scheduler

storage/app/private/receipts/{family_id}/{yyyy}/{mm}/

tests/
├── Architecture/
├── Unit/{Support,Services,Telegram}/
├── Feature/{Api/V1,Web,Telegram,Integration,Tenancy,Consistency}/
├── Fixtures/{telegram,receipts}/  GoldenFamilySeeder.php  golden_expected.php
└── Concerns/                 # WithFamily, ActsAsTelegram

.github/workflows/
└── tests.yml                 # PHP + Node, MySQL + Redis service, semua quality gate
```

## 5. Satu Fitur = File di Mana Saja?

Contoh fitur **Budget**:

| Lapisan | File |
|---|---|
| Rule | `docs/11-business-rules.md` (BR-F**) |
| Migration / Model / Factory | `database/migrations/*_create_budgets_table.php`, `app/Models/Budget.php`, `database/factories/BudgetFactory.php` |
| Repository | `app/Repositories/Contracts/BudgetRepositoryInterface.php`, `app/Repositories/Eloquent/BudgetRepository.php` |
| Kalkulator murni | `app/Support/Budget/BudgetCalculator.php` |
| Service | `app/Services/Budget/BudgetService.php` |
| DTO | `app/DTOs/Budget/BudgetData.php`, `BudgetProgressData.php` |
| Event/Listener | `app/Listeners/CheckBudgetThresholds.php` |
| Request / Policy | `app/Http/Requests/Web/Budget/*`, `app/Http/Requests/Api/V1/*`, `app/Policies/BudgetPolicy.php` |
| Controller | `app/Http/Controllers/Web/Budget/BudgetController.php`, `app/Http/Controllers/Api/V1/BudgetController.php` |
| Bot | `app/Telegram/Handlers/Commands/BudgetCommand.php` |
| Route | `routes/web/budgets.php`, `routes/api/v1/budgets.php` |
| Frontend | `resources/js/modules/budgets/{pages,components,composables,index.ts}` |
| Test | `tests/Unit/Support/BudgetCalculatorTest.php`, `tests/Unit/Services/BudgetServiceTest.php`, `tests/Feature/Api/V1/BudgetTest.php`, `tests/Feature/Web/BudgetTest.php`, `tests/Feature/Consistency/*`, `resources/js/modules/budgets/__tests__/*` |

## 6. Alias & Namespace

| Konteks | Alias |
|---|---|
| PHP | PSR-4 `App\` → `app/`, `Database\` → `database/`, `Tests\` → `tests/` |
| TS / Vite | `@/core` → `resources/js/core`, `@/modules` → `resources/js/modules`, `@/types` → `resources/js/core/types` |
