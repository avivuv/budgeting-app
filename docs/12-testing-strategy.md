# 12 — Testing Strategy

Tujuan: memastikan **data yang diinput, logika, response, dan hasil perhitungan selalu sinkron** — di semua channel (web, REST API, Telegram, struk) dan semua tampilan (dashboard, laporan, export, bot).

## 1. Tools

| Kebutuhan                    | Tool                                                                                                        |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Test runner                  | **Pest 5** (+ `pestphp/pest-plugin-laravel`)                                                                |
| Database test                | MySQL `budgeting_app_test` (sama dengan production, agar fungsi SQL & agregasi identik) + `RefreshDatabase` |
| HTTP eksternal               | `Http::fake()` (Telegram API, Claude API)                                                                   |
| Queue / Event / Notification | `Queue::fake()`, `Event::fake()`, atau dijalankan sync untuk integration                                    |
| Waktu                        | `$this->travelTo()` / `Carbon::setTestNow()` — semua test perhitungan periode memakai tanggal tetap         |
| Frontend                     | **Vitest** + `@vue/test-utils` untuk composable & komponen (format uang, filter)                            |
| Coverage                     | `pest --coverage` (Xdebug/PCOV)                                                                             |
| Static analysis              | Larastan, `vue-tsc`                                                                                         |

## 2. Lapisan Test

```
              ┌───────────────────────┐
              │  Consistency / E2E     │  sedikit — skenario utuh lintas channel
              ├───────────────────────┤
              │  API & Integration     │  endpoint web, REST API, webhook Telegram, job
              ├───────────────────────┤
              │  Unit                  │  banyak — parser, kalkulator, service (repo di-mock)
              └───────────────────────┘
```

### 2.1 Unit Test (`tests/Unit`)

Tanpa database. Cepat, banyak kasus (pakai `dataset()` Pest).

| Target                                    | Yang diuji                                                                             |
| ----------------------------------------- | -------------------------------------------------------------------------------------- |
| `AmountParser`                            | BR-A03: semua variasi `rb/k/jt/juta`, titik/koma, input invalid                        |
| `TransactionParser`                       | tipe, nominal, dompet, tanggal relatif, tag, kategori, multi-baris (≥ 50 kasus)        |
| `MoneyFormatter` / `PercentageCalculator` | BR-A04, BR-A05, BR-A06                                                                 |
| `PeriodResolver`                          | BR-D01–D03 (start_day 1/25/28, akhir tahun, kabisat)                                   |
| `BudgetCalculator`                        | BR-F02–F06                                                                             |
| `ReceiptSplitter`                         | BR-H02: alokasi diskon/pajak, Σ hasil = total persis                                   |
| `RecurringScheduleCalculator`             | BR-G01 (31 → Feb), mingguan, tahunan                                                   |
| Service                                   | logika & cabang error dengan repository di-mock (Mockery)                              |
| DTO                                       | `XData::from(...)` dari request, model, dan hasil parser Telegram → nilai & tipe benar |

### 2.2 Integration Test (`tests/Feature/Integration`)

Dengan database nyata; menguji **Service + Repository + Model** bersama.

- Repository: query & agregasi menghasilkan angka benar dari data yang di-seed.
- Tenancy: data keluarga A tidak pernah muncul di context keluarga B (setiap repository).
- Event → Listener: transaksi baru → cek budget → `SendTelegramMessageJob` ter-dispatch tepat sekali (BR-F05).
- Job struk dengan `FakeReceiptParser` dan dengan `Http::fake()` respons Claude.
- Scheduler recurring idempoten (BR-G02).

### 2.3 API Test (`tests/Feature/Api`, `tests/Feature/Web`, `tests/Feature/Telegram`)

Menguji **request → response** lengkap.

| Kelompok             | Yang diassert                                                                                                               |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| REST API `/api/v1/*` | status code, struktur JSON (`assertJsonStructure`), nilai (`assertJsonPath`), validasi 422 + pesan, 401/403/404, pagination |
| Web (Inertia)        | `assertInertia(fn ($page) => $page->component('Dashboard')->where('summary.expense', 1250000))`                             |
| Webhook Telegram     | fixture JSON update → record di DB + isi pesan balasan (`Http::assertSent`)                                                 |
| Export               | Excel (`Excel::fake()` + assert isi), PDF ter-generate                                                                      |
| Otorisasi            | matrix role (BR-B09, BR-I03) memakai `dataset()` role × aksi                                                                |

### 2.4 Consistency Test (`tests/Feature/Consistency`)

Test kunci untuk menjamin **semua angka sinkron**.

| ID     | Invariant                                                                                                                                                                            |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| INV-01 | Input identik via **web**, **REST API**, dan **Telegram** menghasilkan baris `transactions` identik (kecuali `source`, id, timestamp).                                               |
| INV-02 | `Σ saldo wallet` (halaman dompet) = `total_saldo` (dashboard) = `/saldo` (bot) = API `/summary`.                                                                                     |
| INV-03 | `pemasukan`, `pengeluaran` di dashboard = laporan arus kas = export Excel = `/bulan` bot, untuk periode yang sama.                                                                   |
| INV-04 | `Σ per kategori` (laporan kategori) = total pengeluaran periode.                                                                                                                     |
| INV-05 | `Σ per anggota` = total pengeluaran periode.                                                                                                                                         |
| INV-06 | `Σ subtotal harian` di daftar transaksi = total periode dengan filter yang sama.                                                                                                     |
| INV-07 | Saldo akhir periode N = saldo awal periode N+1.                                                                                                                                      |
| INV-08 | Setelah edit/hapus transaksi, semua angka di INV-02–INV-07 langsung ter-update (cache invalidation, BR-C04).                                                                         |
| INV-09 | Struk "pecah per item": Σ transaksi yang dibuat = `receipt.total`.                                                                                                                   |
| INV-10 | Transfer tidak mengubah total pemasukan/pengeluaran, dan tidak mengubah total saldo keluarga.                                                                                        |
| INV-11 | Konfirmasi yang sama dikirim berulang (klik ganda, retry API dengan `Idempotency-Key` sama, update Telegram dikirim ulang) → jumlah transaksi tetap bertambah satu (BR-B13, BR-J08). |
| INV-12 | Pembayaran tagihan kartu kredit (transfer) tidak menambah pengeluaran; belanja kartu kredit terhitung satu kali (BR-C06).                                                            |
| INV-13 | Setiap perubahan transaksi menghasilkan tepat satu entri activity log dengan nilai lama & baru yang benar (BR-B14).                                                                  |

## 3. Golden Dataset

`tests/Fixtures/GoldenFamilySeeder.php` — data tetap dengan **hasil yang sudah dihitung manual** di `tests/Fixtures/golden_expected.php`. Semua test perhitungan & consistency memakai dataset ini.

Contoh (periode Okt 2026, `period_start_day = 1`, tanggal test = 31 Okt 2026):

| Wallet | Saldo awal |
| ------ | ---------- |
| Tunai  | 500.000    |
| BCA    | 10.000.000 |
| GoPay  | 200.000    |

| Tgl | Tipe     | Nominal   | Wallet      | Kategori           | Oleh          |
| --- | -------- | --------- | ----------- | ------------------ | ------------- |
| 01  | income   | 8.000.000 | BCA         | Gaji               | Ayah          |
| 02  | transfer | 1.000.000 | BCA → Tunai | —                  | Ayah          |
| 03  | expense  | 250.000   | Tunai       | Makanan            | Ibu           |
| 05  | expense  | 1.500.000 | BCA         | Tagihan            | Ayah          |
| 10  | expense  | 87.500    | GoPay       | Belanja Harian     | Ibu (struk)   |
| 15  | expense  | 50.000    | Tunai       | Transportasi       | Ayah          |
| 20  | expense  | 400.000   | BCA         | Makanan > Restoran | Ibu           |
| 25  | expense  | 99.000    | Tunai       | Makanan            | Ibu (dihapus) |

Hasil yang diharapkan:

| Metrik                   | Nilai                                                                     |
| ------------------------ | ------------------------------------------------------------------------- |
| Pemasukan                | 8.000.000                                                                 |
| Pengeluaran              | 2.287.500 (transaksi tgl 25 dihapus → tidak dihitung)                     |
| Selisih                  | 5.712.500                                                                 |
| Saldo Tunai              | 500.000 + 1.000.000 − 250.000 − 50.000 = **1.200.000**                    |
| Saldo BCA                | 10.000.000 + 8.000.000 − 1.000.000 − 1.500.000 − 400.000 = **15.100.000** |
| Saldo GoPay              | 200.000 − 87.500 = **112.500**                                            |
| Total saldo              | **16.412.500** (= saldo awal 10.700.000 + selisih 5.712.500 ✓)            |
| Makanan (incl. Restoran) | 650.000 (28,4%)                                                           |
| Ibu / Ayah               | 737.500 / 1.550.000                                                       |
| Budget Makanan 800.000   | realisasi 650.000 → 81,3% → `waspada`, alert 80% terkirim 1×              |

## 4. Konvensi Penulisan Test

- Nama test menyebut rule: `it('excludes soft-deleted transactions from totals [BR-B08]')`.
- Satu rule boleh dipakai di banyak test; **setiap BR wajib minimal 1 test** — dicek oleh `tests/Architecture/BusinessRuleCoverageTest.php` (memindai `docs/11-business-rules.md` dan mencari ID di folder `tests/`).
- Arrange memakai factory + state (`Transaction::factory()->expense(50_000)->for($wallet)`), bukan insert manual.
- Nominal di test ditulis dengan underscore: `1_500_000`.
- Waktu selalu dibekukan pada test yang melibatkan tanggal.
- Trait helper: `WithFamily` (buat keluarga + set `TenantContext`), `ActsAsTelegram` (kirim fixture update).

## 5. Architecture Test (Pest `arch()`)

```php
Daftar lengkap arch test ada di [16-backend-architecture.md §9](16-backend-architecture.md#9-penjaga-arsitektur-pest-arch-tests). Ringkasnya:
- Controller, Telegram handler, Job, Command tidak memakai Repository / `DB`.
- Service bergantung pada **interface** repository, bukan implementasi Eloquent.
- Repository Eloquent extends `BaseRepository`, kontrak berupa interface.
- `App\Support` murni (tanpa Model/Repository/Facade).
- DTO extends `Spatie\LaravelData\Data`.
- `withoutGlobalScope(FamilyScope)` hanya di method `resolve*` & namespace Admin (BR-I07).
- `strict_types` di semua file, preset Laravel.

Larangan float untuk uang (BR-A01) dijaga lewat Larastan (properti/kolom nominal bertipe `int`) dan test DTO, bukan arch test.

## 6. Struktur Folder

```

tests/
├── Architecture/ # arch() + BusinessRuleCoverageTest
├── Unit/
│ ├── Support/ # AmountParser, MoneyFormatter, PeriodResolver
│ ├── Services/
│ └── Telegram/Parsers/
├── Feature/
│ ├── Api/V1/ # REST API
│ ├── Web/ # Inertia endpoints
│ ├── Telegram/ # webhook, commands, callback
│ ├── Integration/ # repository, listener, job, scheduler
│ ├── Tenancy/ # isolasi
│ └── Consistency/ # INV-01 … INV-13
├── Fixtures/
│ ├── telegram/_.json
│ ├── receipts/_.jpg + *.json (respons AI)
│ ├── GoldenFamilySeeder.php
│ └── golden_expected.php
└── Concerns/ # WithFamily, ActsAsTelegram
resources/js/**/**tests**/ # Vitest

```

## 7. Target & Gate

| Gate | Syarat |
|---|---|
| Coverage | ≥ 90% `app/Services`, `app/Support`, `app/Telegram/Parsers`; ≥ 80% total |
| Business rules | 100% BR punya test |
| Invariants | INV-01 … INV-13 lulus |
| Sebelum commit | `php artisan test --parallel`, Pint, Larastan, `pnpm run check`, `pnpm run types:check` |
| CI | GitHub Actions menjalankan semua gate di atas pada setiap push & PR (`.github/workflows/tests.yml`, service MySQL + Redis) |

## 8. Definition of Done per Fitur
1. Business rule baru/ubah dicatat di [11-business-rules.md](11-business-rules.md).
2. Unit test untuk logika/perhitungan.
3. API/feature test untuk setiap endpoint/command baru (happy path, validasi, otorisasi, tenant).
4. Invariant consistency yang relevan ditambah/diperbarui.
5. Semua gate hijau.
```
