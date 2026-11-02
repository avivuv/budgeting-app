# 02 — Arsitektur

> Detail implementasi backend (kontrak, base class, folder per domain, arch test): [16-backend-architecture.md](16-backend-architecture.md). Frontend: [15-frontend-architecture.md](15-frontend-architecture.md).

## 1. Gambaran Umum

Arsitektur **API-first**: backend Laravel hanya menyediakan **REST API `/api/v1`** (+ webhook Telegram); setiap tampilan adalah klien terpisah. Alasan & tahapan migrasi: [18-api-first-migration.md](18-api-first-migration.md).

```
 ┌─ Web SPA (frontend/) ─┐   ┌─ Android (Kotlin) ─┐        ┌──────────────────┐
 │ Vue 3 + Vue Router    │   │ nanti              │        │ Telegram (grup & │
 └──────────┬────────────┘   └─────────┬──────────┘        │  chat pribadi)   │
   cookie Sanctum + CSRF     Bearer token                  └────────┬─────────┘
            └──────────────┬───────────┘                            │ webhook / polling
                           ▼ HTTPS /api/v1                          ▼
                 ┌──────────────────────┐                 ┌───────────────────┐
                 │ Api/V1 Controllers   │                 │ Telegram Handlers │
                 │ (+ Fortify JSON)     │                 └─────────┬─────────┘
                 └──────────┬───────────┘                           │  DTO
                            │  FormRequest / DTO                    │
                            └──────────────────┬────────────────────┘
                                               ▼
                                       ┌──────────────┐     ┌──────────────┐
                                       │   Services   │────▶│ External     │
                                       │ (bisnis)     │     │ Telegram API │
                                       └──────┬───────┘     │ Gemini API   │
                                              ▼             └──────────────┘
                                       ┌──────────────┐
                                       │ Repositories │  (interface + Eloquent)
                                       └──────┬───────┘
                                              ▼
                                       ┌──────────────┐
                                       │ Models + DB  │  (global scope family_id)
                                       └──────────────┘
```

**Prinsip kunci:**

- Web SPA, Android, dan bot Telegram memanggil **Service yang sama**. Logika bisnis (simpan transaksi, cek budget, hitung saldo) tidak boleh ditulis di controller, handler bot, maupun di klien.
- **Tidak ada halaman yang di-render server.** Backend hanya mengembalikan JSON (API Resource), file (export, gambar struk), dan email/PDF.
- Semua yang bisa dilakukan di web harus bisa dilakukan lewat API (dan sebaliknya), sehingga Android tidak tertinggal fitur.

## 2. Layer & Tanggung Jawab

| Layer                     | Boleh                                                                                                           | Tidak boleh                                   |
| ------------------------- | --------------------------------------------------------------------------------------------------------------- | --------------------------------------------- |
| **Controller** (`Api/V1`) | Terima request, panggil FormRequest + Policy, panggil Service, return API Resource / JSON / file                | Query Eloquent, logika bisnis, render halaman |
| **Telegram Handler**      | Parse update Telegram, ubah jadi DTO, panggil Service, kirim balasan                                            | Query Eloquent, logika bisnis                 |
| **FormRequest**           | Validasi & otorisasi input API                                                                                  | —                                             |
| **DTO** (`app/DTOs`)      | Membawa data antar layer secara type-safe (`spatie/laravel-data`, lihat [16](16-backend-architecture.md#8-dto)) | Logika                                        |
| **Service**               | Logika bisnis, transaksi DB (`DB::transaction`), memanggil beberapa repository, dispatch event/job              | Akses `request()`, return response HTTP       |
| **Repository**            | Semua query ke database (CRUD, filter, agregasi laporan)                                                        | Logika bisnis, memanggil service lain         |
| **API Resource**          | Bentuk JSON response (+ sumber schema OpenAPI)                                                                  | Query, logika bisnis                          |
| **Model**                 | Relasi, casts, scopes, accessor                                                                                 | Logika bisnis berat                           |
| **Job**                   | Proses async (scan struk, kirim notifikasi, laporan PDF) — memanggil Service                                    | —                                             |
| **Event/Listener**        | Efek samping (mis. `TransactionCreated` → cek budget)                                                           | —                                             |

## 3. Service-Repository Pattern

### 3.1 Repository

Setiap repository punya **interface** di `Contracts` dan implementasi Eloquent. Binding didaftarkan di `RepositoryServiceProvider`.

```php
// app/Repositories/Contracts/TransactionRepositoryInterface.php
interface TransactionRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateFiltered(TransactionFilterData $filter, int $perPage = 20): LengthAwarePaginator;
    public function sumByCategory(CarbonInterface $from, CarbonInterface $to, TransactionType $type): Collection;
    public function latestByUser(int $userId): ?Transaction;
}

// app/Repositories/Eloquent/TransactionRepository.php
class TransactionRepository extends BaseRepository implements TransactionRepositoryInterface
{
    public function __construct(Transaction $model)
    {
        parent::__construct($model);
    }

    public function sumByCategory(CarbonInterface $from, CarbonInterface $to, TransactionType $type): Collection
    {
        return $this->model->newQuery()
            ->where('type', $type)
            ->whereBetween('date', [$from, $to])
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->with('category:id,name,color,icon')
            ->get();
    }
    // ...
}
```

`BaseRepositoryInterface` menyediakan operasi umum: `find`, `findOrFail`, `create`, `update`, `delete`, `paginate`.

### 3.2 Service

```php
// app/Services/TransactionService.php
class TransactionService
{
    public function __construct(
        private TransactionRepositoryInterface $transactions,
        private WalletRepositoryInterface $wallets,
    ) {}

    public function create(TransactionData $data, User $actor): Transaction
    {
        return DB::transaction(function () use ($data, $actor) {
            $transaction = $this->transactions->create([...$data->toModelArray(), 'created_by' => $actor->id]);
            event(new TransactionCreated($transaction));   // → BudgetAlertListener
            return $transaction;
        });
    }
}
```

### 3.3 Controller API (tipis)

```php
final class TransactionController extends Controller
{
    public function __construct(private TransactionService $service) {}

    public function store(TransactionRequest $request): JsonResponse
    {
        Gate::authorize('create', Transaction::class);
        $transaction = $this->service->create($request->toData($request->source()), $request->user());

        return TransactionResource::make($transaction)->response()->setStatusCode(201);
    }
}
```

### 3.4 Handler Telegram (memakai service yang sama)

```php
class TextTransactionHandler
{
    public function __construct(
        private TransactionParser $parser,
        private TransactionService $service,
    ) {}

    public function handle(TelegramContext $ctx, string $text): void
    {
        $data = $this->parser->parse($text, $ctx->user, $ctx->family);
        // simpan draft + kirim preview dengan tombol konfirmasi
    }
}
```

### 3.5 Kapan tidak perlu repository?

Model tanpa query khusus (mis. `Invitation`) tetap dibuatkan repository agar konsisten — service **tidak pernah** memanggil Eloquent langsung.

## 4. Struktur Folder

```
app/
├── Console/Commands/            # telegram:set-webhook, telegram:poll, recurring:run
├── DTOs/                        # TransactionData, TransactionFilterData, ReceiptData, ...
├── Enums/                       # TransactionType, FamilyRole, WalletType, ReceiptStatus, TransactionSource
├── Events/  Listeners/
├── Exceptions/                  # QuotaExceededException, TenantNotResolvedException
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/{Domain}/     # REST API (satu-satunya pintu HTTP)
│   │   └── Telegram/WebhookController.php
│   ├── Middleware/              # SetCurrentFamily, EnsureFamilyRole, VerifyTelegramSecret
│   ├── Requests/Api/V1/{Domain}/
│   └── Resources/{Domain}/      # API Resource
├── Jobs/                        # ProcessReceiptJob, SendTelegramMessageJob, GenerateReportPdfJob
├── Models/
│   ├── Concerns/BelongsToFamily.php
│   └── Scopes/FamilyScope.php
├── Policies/
├── Providers/
│   └── RepositoryServiceProvider.php
├── Repositories/
│   ├── Contracts/               # *RepositoryInterface.php, BaseRepositoryInterface.php
│   └── Eloquent/                # *Repository.php, BaseRepository.php
├── Services/
│   ├── FamilyService.php  InvitationService.php  WalletService.php
│   ├── CategoryService.php  TransactionService.php  BudgetService.php
│   ├── ReportService.php  DashboardService.php  RecurringService.php
│   ├── Receipt/ReceiptService.php  Receipt/ReceiptParserInterface.php  Receipt/ClaudeReceiptParser.php
│   └── Tenancy/TenantContext.php
├── Support/                     # MoneyFormatter, AmountParser (50rb → 50000)
└── Telegram/
    ├── TelegramBot.php          # router update → handler
    ├── TelegramContext.php      # user, family, chat yang ter-resolve
    ├── Handlers/                # StartHandler, LinkGroupHandler, TextTransactionHandler,
    │                            # PhotoReceiptHandler, CallbackQueryHandler, Commands/*
    ├── Parsers/TransactionParser.php
    └── Keyboards/               # builder inline keyboard

frontend/src/                    # SPA Vue 3 (klien web)
├── core/                        # api client, ui (shadcn-vue), layouts, composables, stores, lib
└── modules/                     # per fitur: routes.ts, api.ts, pages/, components/, index.ts
```

Detail frontend: [15-frontend-architecture.md](15-frontend-architecture.md).

## 5. Alur Request

**Web SPA / Android — simpan transaksi**
`POST /api/v1/transactions` (cookie sesi atau Bearer token, `X-Family-Id`, `Idempotency-Key`) → `auth:sanctum` → `SetCurrentFamily` → `EnsureIdempotency` → `TransactionRequest` → `TransactionController@store` → `TransactionService::create` → `TransactionRepository::create` → event `TransactionCreated` → `CheckBudgetThresholds` (queue) → notifikasi → `201` + `TransactionResource`. SPA lalu meng-invalidasi query dompet, dashboard, dan budget.

**Telegram — teks**
`POST /telegram/webhook` → `VerifyTelegramSecret` → `TelegramBot` resolve `TelegramContext` (set `TenantContext`) → `TextTransactionHandler` → `TransactionParser` → simpan draft (cache 10 menit) → kirim preview + tombol → `CallbackQueryHandler` (`confirm`) → `TransactionService::create`.

**Telegram — foto struk**
→ `PhotoReceiptHandler` → `ReceiptService::store` (status `pending`) → dispatch `ProcessReceiptJob` → `ReceiptParserInterface::parse` → update receipt → kirim preview + tombol.

## 6. Dependency Utama

| Paket                                                                                                                                                                    | Fungsi                                                             |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------ |
| `laravel/fortify` (headless) + `laravel/sanctum`                                                                                                                         | Auth JSON (login, 2FA, passkey, reset) + sesi SPA & token Android  |
| `dedoc/scramble`                                                                                                                                                         | Dokumentasi OpenAPI otomatis (`/docs/api`)                         |
| `spatie/laravel-data`                                                                                                                                                    | DTO antar layer                                                    |
| `spatie/laravel-permission` _(opsional)_                                                                                                                                 | Bila role perlu lebih granular; default memakai enum role di pivot |
| `irazasyed/telegram-bot-sdk`                                                                                                                                             | Klien Telegram Bot API                                             |
| HTTP client Laravel                                                                                                                                                      | Gemini API untuk struk (Claude sebagai cadangan)                   |
| `maatwebsite/excel`                                                                                                                                                      | Export Excel                                                       |
| `barryvdh/laravel-dompdf`                                                                                                                                                | Export PDF                                                         |
| `spatie/laravel-activitylog`                                                                                                                                             | Riwayat perubahan data (BR-B14)                                    |
| `spatie/laravel-backup`                                                                                                                                                  | Backup DB + file struk harian                                      |
| `laravel/telescope` (dev) · `laravel/pulse` / Sentry (nanti)                                                                                                             | Debug & monitoring error, job, query                               |
| `intervention/image`                                                                                                                                                     | Kompres/resize foto struk sebelum dikirim ke AI                    |
| Frontend (`frontend/`): Vue 3, `vue-router`, `pinia`, `@tanstack/vue-query`, `openapi-typescript`, `shadcn-vue`, `apexcharts`, `@vueuse/core`, `@lucide/vue`, PWA manual | Routing, sesi, data API, tipe dari OpenAPI, UI, grafik, ikon, PWA  |
| Frontend dev: Vite+, `vitest`, `@vue/test-utils`, Playwright                                                                                                             | Lint/format, test, E2E                                             |
| Dev: `pestphp/pest`, `larastan/larastan`, `laravel/pint`, `mockery/mockery`                                                                                              | Testing, static analysis, formatting, mocking                      |
