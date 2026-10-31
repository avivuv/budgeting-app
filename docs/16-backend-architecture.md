# 16 — Backend Architecture (Laravel, Service-Repository)

Pelengkap [02-architecture.md](02-architecture.md). Dokumen ini adalah **acuan implementasi**: setiap class backend harus cocok dengan salah satu peran di sini.

## 1. Alur Dependensi

```
 Entry points                     Application                Data
┌──────────────────────┐   DTO   ┌────────────┐  DTO/array ┌──────────────┐   ┌───────┐
│ Web Controller       │────────▶│            │───────────▶│              │──▶│ Model │──▶ MySQL
│ Api/V1 Controller    │         │  Service   │            │  Repository  │   └───────┘
│ Telegram Handler     │         │            │◀───────────│ (interface)  │
│ Job / Command        │◀────────│            │ Model/DTO  └──────────────┘
└──────────────────────┘ Model/  └─────┬──────┘
   ▲   FormRequest       DTO           │ event / job / external client
   │   Resource / Inertia              ▼
   │                         Events → Listeners, Jobs, Clients (Telegram, AI)
```

Aturan arah: **panah hanya ke kanan/bawah.** Repository tidak tahu Service; Service tidak tahu HTTP/Telegram; Model tidak tahu Service.

## 2. Struktur Folder Lengkap

```
app/
├── Clients/                         # wrapper API eksternal (tanpa logika bisnis)
│   ├── Telegram/TelegramClient.php
│   └── Ai/GeminiClient.php, ClaudeClient.php
├── Console/Commands/
├── DTOs/                            # dikelompokkan per domain
│   ├── Transaction/TransactionData.php, TransactionFilterData.php, ParsedTransactionData.php
│   ├── Report/CashflowReportData.php, CategoryReportData.php, SummaryData.php
│   ├── Budget/  Wallet/  Receipt/  Family/  Telegram/
├── Enums/
├── Events/  Listeners/
├── Exceptions/
│   ├── DomainException.php          # base: punya userMessage() Bahasa Indonesia
│   ├── Transaction/  Budget/  Receipt/  Tenancy/
├── Http/
│   ├── Controllers/
│   │   ├── Web/                     # Inertia
│   │   ├── Api/V1/
│   │   └── Telegram/WebhookController.php
│   ├── Middleware/
│   ├── Requests/
│   │   ├── Concerns/TransactionRules.php   # rule validasi dipakai Web & API
│   │   ├── Web/  Api/V1/
│   └── Resources/                   # API Resource
├── Jobs/
├── Models/  Models/Concerns/  Models/Scopes/
├── Policies/
├── Providers/
│   ├── AppServiceProvider.php
│   └── RepositoryServiceProvider.php
├── Repositories/
│   ├── Contracts/
│   │   ├── BaseRepositoryInterface.php
│   │   └── {Model}RepositoryInterface.php
│   ├── Eloquent/
│   │   ├── BaseRepository.php
│   │   └── {Model}Repository.php
│   └── Cache/                       # decorator cache (opsional per repository)
│       └── CachedWalletRepository.php
├── Services/                        # dikelompokkan per domain
│   ├── Transaction/TransactionService.php, TransferService.php, TransactionQueryService.php (baca/list)
│   ├── Wallet/WalletService.php, WalletBalanceService.php
│   ├── Category/CategoryService.php, CategoryGuesser.php
│   ├── Budget/BudgetService.php
│   ├── Report/ReportService.php, DashboardService.php, ExportService.php
│   ├── Receipt/ReceiptService.php, Parsers/{ReceiptParserInterface, GeminiReceiptParser, ClaudeReceiptParser, FakeReceiptParser}.php
│   ├── Recurring/RecurringService.php
│   ├── Family/FamilyService.php, InvitationService.php, MembershipService.php
│   ├── Telegram/TelegramLinkService.php, TelegramNotifier.php
│   └── Tenancy/TenantContext.php
├── Support/                         # kalkulator & util murni (stateless, tanpa DB)
│   ├── Money/AmountParser.php, MoneyFormatter.php
│   ├── Period/PeriodResolver.php, Period.php
│   ├── Budget/BudgetCalculator.php
│   ├── Receipt/ReceiptSplitter.php
│   └── Math/Percentage.php
└── Telegram/                        # entry point bot (setara Controller)
```

**Aturan penempatan:**

- Butuh DB → Repository. Butuh beberapa repository / aturan bisnis → Service. Perhitungan murni tanpa DB → `Support` (mudah di-unit test, dipakai Service).
- Satu Service fokus satu domain; jika > ~300 baris atau > 7 dependency, pecah (mis. `TransferService` dari `TransactionService`).

## 3. Kontrak Dasar

### 3.1 BaseRepositoryInterface

```php
interface BaseRepositoryInterface
{
    public function find(int $id): ?Model;
    public function findOrFail(int $id): Model;
    public function all(array $columns = ['*']): Collection;
    public function paginate(int $perPage = 20): LengthAwarePaginator;
    public function create(array $attributes): Model;
    public function update(Model $model, array $attributes): Model;
    public function delete(Model $model): bool;
}
```

### 3.2 BaseRepository

```php
abstract class BaseRepository implements BaseRepositoryInterface
{
    public function __construct(protected Model $model) {}

    protected function query(): Builder
    {
        return $this->model->newQuery();   // global scope FamilyScope ikut aktif
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();
        return $model->refresh();
    }
    // ...
}
```

### 3.3 Binding

```php
class RepositoryServiceProvider extends ServiceProvider
{
    public array $bindings = [
        FamilyRepositoryInterface::class      => FamilyRepository::class,
        TransactionRepositoryInterface::class => TransactionRepository::class,
        CategoryRepositoryInterface::class    => CategoryRepository::class,
        BudgetRepositoryInterface::class      => BudgetRepository::class,
        // ...
    ];

    public function register(): void
    {
        // decorator cache
        $this->app->bind(WalletRepositoryInterface::class, fn ($app) =>
            new CachedWalletRepository($app->make(WalletRepository::class), $app->make('cache.store')));
    }
}
```

### 3.4 Filter terstruktur

Filter kompleks tidak ditulis sebagai parameter panjang, tetapi DTO + method privat di repository:

```php
public function paginateFiltered(TransactionFilterData $f, int $perPage = 20): LengthAwarePaginator
{
    return $this->query()
        ->with(['wallet:id,name', 'category:id,name,icon,color', 'creator:id,name', 'tags:id,name'])
        ->when($f->from, fn ($q) => $q->whereDate('date', '>=', $f->from))
        ->when($f->to, fn ($q) => $q->whereDate('date', '<=', $f->to))
        ->when($f->type, fn ($q) => $q->where('type', $f->type))
        ->when($f->categoryIds, fn ($q) => $q->whereIn('category_id', $f->categoryIds))
        ->when($f->search, fn ($q) => $q->where(fn ($q) => $q
            ->where('note', 'like', "%{$f->search}%")->orWhere('merchant', 'like', "%{$f->search}%")))
        ->orderByDesc('date')->orderByDesc('id')
        ->paginate($perPage)->withQueryString();
}
```

## 4. Service

```php
class TransactionService
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
        private readonly WalletRepositoryInterface $wallets,
        private readonly CategoryRepositoryInterface $categories,
        private readonly TenantContext $tenant,
    ) {}

    public function create(TransactionData $data, User $actor): Transaction
    {
        $this->assertWritable($data);                          // BR-B02..B06

        return DB::transaction(function () use ($data, $actor) {
            $transaction = $this->transactions->create([
                ...$data->toModelArray(),
                'created_by' => $actor->id,
            ]);
            $this->transactions->syncTags($transaction, $data->tagIds);

            TransactionCreated::dispatch($transaction);          // → invalidate cache, cek budget
            return $transaction;
        });
    }

    private function assertWritable(TransactionData $data): void
    {
        $wallet = $this->wallets->findOrFail($data->walletId);  // 404 bila beda keluarga (BR-B04)
        if ($wallet->is_archived) {
            throw new ArchivedWalletException();                 // BR-B05
        }
        // ...
    }
}
```

Konvensi method Service: kata kerja bisnis (`create`, `transfer`, `undoLast`, `confirmReceipt`, `copyFromPreviousPeriod`), bukan nama query.

## 5. Transaksi DB, Event, Cache

| Topik          | Aturan                                                                                                                                                                         |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| DB transaction | Dibuka di Service saja. Repository tidak membuka transaksi.                                                                                                                    |
| Event          | Di-dispatch dari Service. Listener yang memanggil API eksternal → `ShouldQueue` + `afterCommit`.                                                                               |
| Cache          | Hanya di decorator `Repositories/Cache/*` atau `DashboardService`; key selalu diawali `family:{id}:`. Invalidasi lewat listener `TransactionCreated/Updated/Deleted` (BR-C04). |
| Job            | Simpan `familyId` + id primitif (bukan model besar); set `TenantContext` di awal `handle()`; panggil Service.                                                                  |

## 6. Exception & Response

- Semua exception bisnis turunan `App\Exceptions\DomainException` dengan `userMessage()` dan `status()` (default 422).
- `bootstrap/app.php` → `withExceptions()` merender:
    - **Web**: redirect back + flash error.
    - **API**: `{"message": userMessage}` dengan status yang sesuai.
    - **Telegram**: handler menangkap dan membalas `userMessage` ke chat.
- `ModelNotFoundException` dari tenant scope → 404 (BR-I06).

## 7. Controller (contoh lengkap)

```php
// Web
final class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $service, private readonly TransactionQueryService $query) {}

    public function index(TransactionIndexRequest $request): Response
    {
        $filter = TransactionFilterData::from($request->validated());
        return Inertia::render('transactions/Index', [
            'transactions' => TransactionListItemData::collect($this->query->paginate($filter)),
            'filters'      => $filter,
            'options'      => Inertia::defer(fn () => $this->query->filterOptions()),
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $this->service->create(TransactionData::from($request->validated()), $request->user());
        return back()->with('success', __('transactions.saved'));
    }
}

// API — memanggil service yang sama
final class TransactionController extends Controller
{
    public function store(StoreTransactionRequest $request, TransactionService $service): TransactionResource
    {
        $trx = $service->create(TransactionData::from([...$request->validated(), 'source' => 'api']), $request->user());
        return TransactionResource::make($trx->load('wallet', 'category', 'creator', 'tags'));
    }
}
```

Read-heavy (list, laporan) boleh memakai _query service_ terpisah (`TransactionQueryService`, `ReportService`) agar service tulis tetap ramping (pemisahan baca/tulis ringan ala CQRS).

## 8. DTO

Memakai `spatie/laravel-data` agar satu class melayani: validasi input → DTO → output Inertia → tipe TypeScript.

```php
#[TypeScript]
final class TransactionData extends Data
{
    public function __construct(
        public TransactionType $type,
        public int $amount,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        public CarbonImmutable $date,
        public int $walletId,
        public ?int $categoryId,
        public ?int $toWalletId,
        public ?string $note,
        public TransactionSource $source = TransactionSource::Web,
        /** @var int[] */ public array $tagIds = [],
    ) {}
}
```

Pemetaan `snake_case` ↔ `camelCase` diatur global (`MapName(SnakeCaseMapper::class)`).

## 9. Penjaga Arsitektur (Pest arch tests)

```php
arch('controllers & handlers only talk to services')
    ->expect(['App\Http\Controllers', 'App\Telegram', 'App\Jobs', 'App\Console'])
    ->not->toUse(['App\Repositories', 'Illuminate\Support\Facades\DB']);

arch('services depend on repository contracts, not implementations')
    ->expect('App\Services')
    ->not->toUse('App\Repositories\Eloquent');
// DB::transaction() boleh di Service; query builder mentah (DB::table/select) dicegah
// lewat Larastan custom rule + code review, karena arch() tidak bisa membedakan method facade.

arch('repositories are the only eloquent query layer')
    ->expect('App\Repositories\Eloquent')
    ->toExtend('App\Repositories\Eloquent\BaseRepository')
    ->toHaveSuffix('Repository');

arch('repository contracts are interfaces')
    ->expect('App\Repositories\Contracts')->toBeInterfaces()->toHaveSuffix('RepositoryInterface');

arch('support is pure')
    ->expect('App\Support')->not->toUse(['App\Models', 'App\Repositories', 'Illuminate\Support\Facades']);

arch('dtos are data objects')
    ->expect('App\DTOs')->toExtend('Spatie\LaravelData\Data')->toHaveSuffix('Data');
arch()->expect('App')->toUseStrictTypes();
arch()->preset()->laravel();
```

Penjagaan scope tenant (BR-I07): `tests/Architecture/TenantScopeBypassTest.php` memindai `app/` dan memastikan `withoutGlobalScope(FamilyScope` hanya muncul di dalam method bernama `resolve*` atau di namespace `App\Repositories\Eloquent\Admin`.

Service sengaja **tidak** `final` agar bisa di-mock (Mockery) di test controller & handler.

## 10. Checklist Fitur Baru (Backend)

1. Rule bisnis dicatat di [11-business-rules.md](11-business-rules.md).
2. Migration + Model (+ `BelongsToFamily`) + Factory.
3. `XRepositoryInterface` + `XRepository` + binding.
4. DTO input/output (`#[TypeScript]`).
5. `XService` (logika & rule) + exception domain.
6. FormRequest (Web & API, rule bersama) + Policy.
7. Controller Web / API / Handler Telegram yang tipis.
8. Test: unit (Support/Service), API/feature, tenancy, consistency bila menyentuh angka.
9. `typescript:transform`, Pint, Larastan, arch test hijau.
