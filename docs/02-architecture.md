# 02 — Arsitektur

> Detail implementasi backend (kontrak, base class, folder per domain, arch test): [16-backend-architecture.md](16-backend-architecture.md). Frontend: [15-frontend-architecture.md](15-frontend-architecture.md).

## 1. Gambaran Umum

```
           ┌──────────────┐        ┌──────────────────┐
 Browser ─▶│ Inertia/Vue  │        │ Telegram (grup & │
           └──────┬───────┘        │  chat pribadi)   │
                  │ HTTP            └────────┬─────────┘
                  ▼                          │ webhook / polling
           ┌──────────────┐        ┌─────────▼─────────┐
           │ Controllers  │        │ Telegram Handlers │
           └──────┬───────┘        └─────────┬─────────┘
                  │  FormRequest / DTO        │  DTO
                  └────────────┬──────────────┘
                               ▼
                       ┌──────────────┐     ┌──────────────┐
                       │   Services   │────▶│ External     │
                       │ (bisnis)     │     │ Telegram API │
                       └──────┬───────┘     │ Claude API   │
                              ▼             └──────────────┘
                       ┌──────────────┐
                       │ Repositories │  (interface + Eloquent)
                       └──────┬───────┘
                              ▼
                       ┌──────────────┐
                       │ Models + DB  │  (global scope family_id)
                       └──────────────┘
```

**Prinsip kunci:** web dan bot Telegram memanggil **Service yang sama**. Logika bisnis (simpan transaksi, cek budget, hitung saldo) tidak boleh ditulis di controller maupun handler bot.

## 2. Layer & Tanggung Jawab

| Layer                | Boleh                                                                                                           | Tidak boleh                             |
| -------------------- | --------------------------------------------------------------------------------------------------------------- | --------------------------------------- |
| **Controller**       | Terima request, panggil FormRequest, panggil Service, return `Inertia::render` / redirect                       | Query Eloquent, logika bisnis           |
| **Telegram Handler** | Parse update Telegram, ubah jadi DTO, panggil Service, kirim balasan                                            | Query Eloquent, logika bisnis           |
| **FormRequest**      | Validasi & otorisasi input web                                                                                  | —                                       |
| **DTO** (`app/DTOs`) | Membawa data antar layer secara type-safe (`spatie/laravel-data`, lihat [16](16-backend-architecture.md#8-dto)) | Logika                                  |
| **Service**          | Logika bisnis, transaksi DB (`DB::transaction`), memanggil beberapa repository, dispatch event/job              | Akses `request()`, return response HTTP |
| **Repository**       | Semua query ke database (CRUD, filter, agregasi laporan)                                                        | Logika bisnis, memanggil service lain   |
| **Model**            | Relasi, casts, scopes, accessor                                                                                 | Logika bisnis berat                     |
| **Job**              | Proses async (scan struk, kirim notifikasi, laporan PDF) — memanggil Service                                    | —                                       |
| **Event/Listener**   | Efek samping (mis. `TransactionCreated` → cek budget)                                                           | —                                       |

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

### 3.3 Controller (tipis)

```php
class TransactionController extends Controller
{
    public function __construct(private TransactionService $service) {}

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $this->service->create(TransactionData::from($request->validated()), $request->user());
        return back()->with('success', 'Transaksi tersimpan');
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
│   ├── Controllers/             # Web (Inertia) controllers
│   │   └── Telegram/WebhookController.php
│   ├── Middleware/              # SetCurrentFamily, EnsureFamilyRole, VerifyTelegramSecret
│   └── Requests/
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

resources/js/
├── core/                        # ui (shadcn-vue), common, charts, form, layouts, composables, lib, types
└── modules/                     # per fitur: pages/, components/, composables/, index.ts
```

Detail frontend: [15-frontend-architecture.md](15-frontend-architecture.md).

## 5. Alur Request

**Web — simpan transaksi**
`POST /transactions` → `SetCurrentFamily` middleware → `StoreTransactionRequest` → `TransactionController@store` → `TransactionService::create` → `TransactionRepository::create` → event `TransactionCreated` → `CheckBudgetListener` (queue) → notifikasi Telegram bila lewat ambang.

**Telegram — teks**
`POST /telegram/webhook` → `VerifyTelegramSecret` → `TelegramBot` resolve `TelegramContext` (set `TenantContext`) → `TextTransactionHandler` → `TransactionParser` → simpan draft (cache 10 menit) → kirim preview + tombol → `CallbackQueryHandler` (`confirm`) → `TransactionService::create`.

**Telegram — foto struk**
→ `PhotoReceiptHandler` → `ReceiptService::store` (status `pending`) → dispatch `ProcessReceiptJob` → `ReceiptParserInterface::parse` → update receipt → kirim preview + tombol.

## 6. Dependency Utama

| Paket                                                                                                                                       | Fungsi                                                                 |
| ------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| Laravel 13 **Vue starter kit** (Inertia 3 + Vue 3 + TS + shadcn-vue + Tailwind 4)                                                           | Auth scaffolding, lalu direstrukturisasi ke `core/` + `modules/`       |
| `spatie/laravel-data` + `spatie/laravel-typescript-transformer`                                                                             | DTO & generate tipe TypeScript                                         |
| `inertiajs/inertia-laravel`, `@inertiajs/vue3`                                                                                              | Jembatan Laravel–Vue                                                   |
| `laravel/wayfinder`                                                                                                                         | Route & controller Laravel sebagai fungsi TypeScript (pengganti Ziggy) |
| `spatie/laravel-permission` _(opsional)_                                                                                                    | Bila role perlu lebih granular; default memakai enum role di pivot     |
| `irazasyed/telegram-bot-sdk`                                                                                                                | Klien Telegram Bot API                                                 |
| `anthropic-ai/sdk` (PHP) atau HTTP client                                                                                                   | Claude API untuk struk                                                 |
| `maatwebsite/excel`                                                                                                                         | Export Excel                                                           |
| `barryvdh/laravel-dompdf`                                                                                                                   | Export PDF                                                             |
| `spatie/laravel-activitylog`                                                                                                                | Riwayat perubahan data (BR-B14)                                        |
| `spatie/laravel-backup`                                                                                                                     | Backup DB + file struk harian                                          |
| `laravel/telescope` (dev) · `laravel/pulse` / Sentry (nanti)                                                                                | Debug & monitoring error, job, query                                   |
| `intervention/image`                                                                                                                        | Kompres/resize foto struk sebelum dikirim ke AI                        |
| Frontend: `shadcn-vue`, `@tanstack/vue-table`, `vue3-apexcharts`, `@vueuse/core`, `lucide-vue-next`, `pinia`, `vue-i18n`, `vite-plugin-pwa` | UI, grafik, util, ikon, state UI, i18n, PWA                            |
| Frontend dev: `vitest`, `@vue/test-utils`, `eslint-plugin-boundaries`, `prettier`                                                           | Test & aturan import                                                   |
| Dev: `pestphp/pest`, `larastan/larastan`, `laravel/pint`, `mockery/mockery`                                                                 | Testing, static analysis, formatting, mocking                          |
