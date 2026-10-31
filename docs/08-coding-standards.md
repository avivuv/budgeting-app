# 08 — Coding Standards

## 1. Umum
- PHP 8.4, `declare(strict_types=1);` di semua file PHP aplikasi.
- Format: **Laravel Pint** (preset `laravel`). Static analysis: **Larastan** level 6+.
- Kode, nama class/variabel, commit message: **English**. Teks yang tampil ke user (UI, pesan bot, validasi): **Bahasa Indonesia** via `lang/id/*.php`.
- Tidak ada query Eloquent di Controller, Telegram Handler, Job, atau Vue — selalu lewat Service → Repository.

## 2. Aturan Layer (ringkas)

| Dari \ Ke | Service | Repository | Model/Eloquent |
|---|:-:|:-:|:-:|
| Controller / Handler / Job / Command | ✅ | ❌ | ❌ (kecuali route model binding & type-hint) |
| Service | ✅ (service lain, hindari siklus) | ✅ | ❌ query langsung |
| Repository | ❌ | ❌ | ✅ |

## 3. Naming

| Jenis | Pola | Contoh |
|---|---|---|
| Repository interface | `{Model}RepositoryInterface` | `TransactionRepositoryInterface` |
| Repository impl | `{Model}Repository` | `TransactionRepository` |
| Service | `{Domain}Service` | `TransactionService`, `ReportService` |
| DTO | `{Thing}Data` | `TransactionData`, `TransactionFilterData` |
| Enum | singular, PascalCase | `TransactionType::Expense` |
| FormRequest | `{Verb}{Model}Request` | `StoreTransactionRequest` |
| Job | `{Verb}{Thing}Job` | `ProcessReceiptJob` |
| Event | past tense | `TransactionCreated` |
| Vue page | `Pages/{Resource}/{Action}.vue` | `Pages/Transactions/Index.vue` |
| Vue component | PascalCase, multi-word | `CategoryDonut.vue` |
| Route name | `resource.action` | `transactions.index` |
| Tabel | snake_case plural | `recurring_rules` |

## 4. Repository
- Method mendeskripsikan kebutuhan, bukan query: `sumByCategory()`, bukan `getWhereTypeGroupBy()`.
- Return `Model`, `Collection`, `LengthAwarePaginator`, atau DTO — tidak return `Builder`.
- Eager load relasi yang dibutuhkan untuk menghindari N+1 (aktifkan `Model::preventLazyLoading()` di non-production).
- Didaftarkan di `RepositoryServiceProvider::$bindings`:
```php
public array $bindings = [
    TransactionRepositoryInterface::class => TransactionRepository::class,
    // ...
];
```

## 5. Service
- Constructor injection interface repository.
- Operasi yang menulis > 1 tabel wajib `DB::transaction()`.
- Terima DTO / primitive, bukan `Request`.
- Lempar exception domain (`BudgetNotFoundException`, `QuotaExceededException`) — ditangani di controller/handler menjadi pesan user.

## 6. DTO
```php
final readonly class TransactionData
{
    public function __construct(
        public TransactionType $type,
        public int $amount,
        public CarbonImmutable $date,
        public int $walletId,
        public ?int $categoryId = null,
        public ?int $toWalletId = null,
        public ?string $note = null,
        public TransactionSource $source = TransactionSource::Web,
        public array $tagIds = [],
    ) {}

    public static function fromRequest(StoreTransactionRequest $request): self { /* ... */ }
    public function toArray(): array { /* ... */ }
}
```

## 7. Uang & Tanggal
- Uang selalu `int` rupiah. Dilarang `float` untuk nominal.
- Parsing input (`50rb`, `1,5jt`) hanya lewat `App\Support\AmountParser`; format tampilan lewat `MoneyFormatter` (PHP) dan `useMoney()` (Vue).
- Tanggal transaksi = tanggal lokal zona waktu keluarga; gunakan `CarbonImmutable`.

## 8. Frontend (Vue)
- `<script setup lang="ts">` + TypeScript.
- Composition API, composables di `resources/js/Composables`.
- Props dari Inertia diberi type di `resources/js/types`.
- Komponen UI dasar dari shadcn-vue (`Components/ui`), jangan duplikasi.
- Tailwind; hindari CSS custom kecuali perlu.

## 9. Testing (Pest)
Detail lengkap di [12-testing-strategy.md](12-testing-strategy.md). Ringkas:
- **Unit**: parser, kalkulator, service dengan repository di-mock.
- **API/Feature**: REST API, endpoint web (Inertia assertions), webhook Telegram (fixture JSON + `Http::fake`), job struk (`FakeReceiptParser`).
- **Integration & Consistency**: repository + agregasi, invariant `INV-xx` lintas channel.
- **Tenancy**: setiap repository punya test isolasi.
- Nama test menyebut ID rule dari [11-business-rules.md](11-business-rules.md).
- Target: test lulus + Pint + Larastan bersih sebelum commit.

## 10. Git
- Branch: `feature/...`, `fix/...`, `chore/...`.
- Commit: Conventional Commits (`feat(telegram): parse multi-line input`).
