# 08 — Coding Standards

## 1. Umum

- PHP 8.4, `declare(strict_types=1);` di semua file PHP aplikasi.
- Format: **Laravel Pint** (preset `laravel`). Static analysis: **Larastan** level 6+.
- Kode, nama class/variabel, commit message: **English**. Teks yang tampil ke user (UI, pesan bot, validasi): **Bahasa Indonesia** via `lang/id/*.php`.
- Tidak ada query Eloquent di Controller, Telegram Handler, Job, atau Vue — selalu lewat Service → Repository.

## 2. Aturan Layer (ringkas)

| Dari \ Ke                            |              Service              | Repository |                Model/Eloquent                |
| ------------------------------------ | :-------------------------------: | :--------: | :------------------------------------------: |
| Controller / Handler / Job / Command |                ✅                 |     ❌     | ❌ (kecuali route model binding & type-hint) |
| Service                              | ✅ (service lain, hindari siklus) |     ✅     |              ❌ query langsung               |
| Repository                           |                ❌                 |     ❌     |                      ✅                      |

## 3. Naming

| Jenis                | Pola                            | Contoh                                     |
| -------------------- | ------------------------------- | ------------------------------------------ |
| Repository interface | `{Model}RepositoryInterface`    | `TransactionRepositoryInterface`           |
| Repository impl      | `{Model}Repository`             | `TransactionRepository`                    |
| Service              | `{Domain}Service`               | `TransactionService`, `ReportService`      |
| DTO                  | `{Thing}Data`                   | `TransactionData`, `TransactionFilterData` |
| Enum                 | singular, PascalCase            | `TransactionType::Expense`                 |
| FormRequest          | `{Verb}{Model}Request`          | `StoreTransactionRequest`                  |
| Job                  | `{Verb}{Thing}Job`              | `ProcessReceiptJob`                        |
| Event                | past tense                      | `TransactionCreated`                       |
| Vue page             | `Pages/{Resource}/{Action}.vue` | `Pages/Transactions/Index.vue`             |
| Vue component        | PascalCase, multi-word          | `CategoryDonut.vue`                        |
| Route name           | `resource.action`               | `transactions.index`                       |
| Tabel                | snake_case plural               | `recurring_rules`                          |

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

- Class **tidak** `final` (agar bisa di-mock di test controller/handler); dependency ditandai `private readonly`.
- Constructor injection interface repository.
- Operasi yang menulis > 1 tabel wajib `DB::transaction()`.
- Terima DTO / primitive, bukan `Request`.
- Lempar exception domain (`BudgetNotFoundException`, `QuotaExceededException`) — ditangani di controller/handler menjadi pesan user.

## 6. DTO

- Semua DTO memakai **`spatie/laravel-data`** (extends `Data`), suffix `Data`, diberi `#[TypeScript]` bila dipakai frontend. Contoh lengkap: [16-backend-architecture.md §8](16-backend-architecture.md#8-dto).
- Dibuat dengan `XData::from($request->validated())`, `XData::from($model)`, atau `XData::collect($paginator)`.
- Konversi ke kolom tabel lewat method `toModelArray()` (snake_case, tanpa field turunan).
- DTO tidak berisi logika bisnis; perhitungan ada di Service/`Support`.

## 7. Uang & Tanggal

- Uang selalu `int` rupiah. Dilarang `float` untuk nominal.
- Parsing input (`50rb`, `1,5jt`) hanya lewat `App\Support\AmountParser`; format tampilan lewat `MoneyFormatter` (PHP) dan `useMoney()` (Vue).
- Tanggal transaksi = tanggal lokal zona waktu keluarga; gunakan `CarbonImmutable`.

## 8. Frontend (Vue)

Detail di [15-frontend-architecture.md](15-frontend-architecture.md). Ringkas:

- `<script setup lang="ts">`, Composition API.
- Struktur modular `core/` + `modules/<fitur>/`; antar modul hanya lewat `index.ts`.
- SPA di `frontend/`; data hanya lewat REST API (`core/api/client.ts` + `api.ts` per modul).
- Tipe dari `core/api/schema.d.ts` (hasil generate OpenAPI), jangan tulis ulang manual.
- Tidak ada perhitungan bisnis di frontend — hanya format & tampilan.
- Komponen UI dasar dari shadcn-vue (`core/components/ui`), jangan duplikasi.
- Tailwind + token warna; hindari CSS custom kecuali perlu.

## 9. Testing (Pest)

Detail lengkap di [12-testing-strategy.md](12-testing-strategy.md). Ringkas:

- **Unit**: parser, kalkulator, service dengan repository di-mock.
- **API/Feature**: test skenario per fitur lewat REST API ([test-cases](test-cases/README.md)), webhook Telegram (fixture JSON + `Http::fake`), job struk (`FakeReceiptParser`).
- **Integration & Consistency**: repository + agregasi, invariant `INV-xx` lintas channel.
- **Tenancy**: setiap repository punya test isolasi.
- Nama test menyebut ID rule dari [11-business-rules.md](11-business-rules.md).
- Target: test lulus + Pint + Larastan bersih sebelum commit.

## 10. Git

- Branch: `feature/...`, `fix/...`, `chore/...`.
- Commit: Conventional Commits (`feat(telegram): parse multi-line input`).
