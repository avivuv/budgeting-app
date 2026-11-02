# 13 — REST API

Selain web (Inertia) dan Telegram, aplikasi menyediakan **REST API `/api/v1`** untuk integrasi (aplikasi mobile/shortcut di masa depan) dan sebagai target **API test**. Controller API memanggil **Service yang sama** dengan web dan bot (lihat [02-architecture.md](02-architecture.md)).

## 1. Autentikasi & Tenant

- **Laravel Sanctum** personal access token: `Authorization: Bearer <token>`.
- Token dibuat di _Pengaturan → API Token_.
- Keluarga aktif: header `X-Family-Id` (opsional, default `current_family_id`); divalidasi keanggotaan → `TenantContext`.
- Rate limit: 60 request/menit per token.
- Token punya **abilities** `read` (semua GET) dan `write` (membuat/mengubah/menghapus), dipilih saat membuat token, serta **masa berlaku** (default 90 hari, `SANCTUM_EXPIRATION`); bisa dicabut kapan saja dari halaman pengaturan.

### Kenapa Sanctum, bukan JWT

|                                           | Sanctum (dipakai)                             | JWT                                                       |
| ----------------------------------------- | --------------------------------------------- | --------------------------------------------------------- |
| Web (Inertia)                             | Session cookie + CSRF, tanpa token di browser | Token di browser → rawan XSS, perlu refresh token         |
| Cabut akses (logout, anggota dikeluarkan) | Instan (hapus token di DB)                    | Sulit sebelum token kedaluwarsa (perlu blacklist)         |
| Kompleksitas                              | Bawaan Laravel                                | Paket tambahan + rotasi refresh token                     |
| Cocok untuk                               | Monolith + API untuk app sendiri              | Banyak service terpisah yang memverifikasi token tanpa DB |

Ringkas: **Web = session**, **REST API = Sanctum token**, **Telegram = identitas `telegram_user_id` + webhook secret** (tanpa token). JWT dipertimbangkan hanya jika nanti arsitektur dipecah menjadi beberapa service.

## 2. Format Response

Sukses (single):

```json
{
    "data": {
        "id": 12,
        "type": "expense",
        "amount": 50000,
        "amount_formatted": "Rp 50.000",
        "date": "2026-10-31",
        "...": "..."
    }
}
```

Sukses (list):

```json
{ "data": [ ... ], "meta": { "current_page": 1, "per_page": 20, "total": 134, "totals": { "income": 8000000, "expense": 2287500 } }, "links": { "next": "..." } }
```

Error:

```json
{
    "message": "Data tidak valid",
    "errors": { "amount": ["Nominal harus lebih dari 0"] }
}
```

| Status          | Arti                                            |
| --------------- | ----------------------------------------------- |
| 200 / 201 / 204 | OK / dibuat / dihapus                           |
| 401             | token tidak valid                               |
| 403             | role tidak berhak                               |
| 404             | tidak ada **atau milik keluarga lain** (BR-I06) |
| 422             | validasi                                        |
| 429             | rate limit / kuota struk habis                  |

**Idempotensi (BR-B13):** semua `POST` yang membuat data (`/transactions`, `/receipts`, `/receipts/{id}/confirm`) menerima header `Idempotency-Key: <uuid>`. Request ulang dengan key sama dalam 24 jam mengembalikan response pertama tanpa membuat data baru (middleware `EnsureIdempotency`, disimpan di Redis per `family + user + key`).

Nominal selalu integer rupiah; tanggal `YYYY-MM-DD`; datetime ISO 8601 dengan offset zona keluarga. Response memakai **API Resource** (`app/Http/Resources`).

## 3. Endpoint

| Method          | Path                                      | Fungsi                                                                                                                   |
| --------------- | ----------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| GET             | `/api/v1/me`                              | profil + daftar keluarga & role                                                                                          |
| GET             | `/api/v1/wallets`                         | dompet + saldo                                                                                                           |
| POST/PUT/DELETE | `/api/v1/wallets/{id}`                    | kelola dompet (admin)                                                                                                    |
| GET             | `/api/v1/categories?type=expense`         | kategori (tree)                                                                                                          |
| GET             | `/api/v1/transactions`                    | list + filter (`from,to,type,category_id[],wallet_id[],user_id[],tag[],q,min,max,source`)                                |
| POST            | `/api/v1/transactions`                    | buat transaksi                                                                                                           |
| POST            | `/api/v1/transactions/parse`              | parse teks bebas (`{"text":"bensin 50rb"}`, multi-baris) → preview per baris + `missing`, tanpa simpan (ability `write`) |
| GET/PUT/DELETE  | `/api/v1/transactions/{id}`               | detail / ubah / hapus                                                                                                    |
| GET             | `/api/v1/budgets?period=2026-10`          | budget + realisasi + status                                                                                              |
| PUT             | `/api/v1/budgets`                         | set budget (bulk)                                                                                                        |
| POST            | `/api/v1/receipts`                        | upload struk (multipart) → `202` + id                                                                                    |
| GET             | `/api/v1/receipts/{id}`                   | status & hasil parse (draft, belum jadi transaksi)                                                                       |
| PATCH           | `/api/v1/receipts/{id}`                   | koreksi draft (total, tanggal, merchant, kategori, wallet, item)                                                         |
| POST            | `/api/v1/receipts/{id}/confirm`           | `{"mode":"total"                                                                                                         | "split"}` → transaksi |
| GET             | `/api/v1/summary?period=2026-10`          | ringkasan dashboard (saldo, pemasukan, pengeluaran, selisih, Δ%)                                                         |
| GET             | `/api/v1/reports/cashflow?from&to`        | arus kas per bulan                                                                                                       |
| GET             | `/api/v1/reports/categories?from&to&type` | per kategori                                                                                                             |
| GET             | `/api/v1/reports/members?from&to`         | per anggota                                                                                                              |
| GET             | `/api/v1/reports/wallets?from&to`         | mutasi per dompet                                                                                                        |
| GET             | `/api/v1/reports/budget?period`           | budget vs realisasi                                                                                                      |

## 4. Contoh

```http
POST /api/v1/transactions
Authorization: Bearer 1|abc...
Content-Type: application/json

{ "type": "expense", "amount": 50000, "date": "2026-10-31", "wallet_id": 1, "category_id": 4, "note": "bensin", "tags": ["mudik"] }
```

```json
201
{ "data": { "id": 345, "type": "expense", "amount": 50000, "amount_formatted": "Rp 50.000",
  "date": "2026-10-31", "wallet": { "id": 1, "name": "Tunai" },
  "category": { "id": 4, "name": "Transportasi" }, "note": "bensin", "tags": ["mudik"],
  "source": "api", "created_by": { "id": 2, "name": "Ayah" }, "budget_status": { "percent": 45.0, "status": "aman" } } }
```

## 5. Struktur Kode

```
app/Http/Controllers/Api/V1/   # TransactionController, WalletController, ReportController, ...
app/Http/Requests/Api/V1/
app/Http/Resources/            # TransactionResource, WalletResource, SummaryResource, ...
routes/api.php                 # prefix v1, middleware auth:sanctum, set.family, throttle:api
```

FormRequest web dan API memakai rule validasi yang sama (trait `TransactionRules`) agar validasi konsisten.
