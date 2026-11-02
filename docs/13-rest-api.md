# 13 — REST API

**REST API `/api/v1` adalah satu-satunya pintu HTTP ke backend.** Semua klien memakainya: **web SPA** (`frontend/`), **aplikasi Android** (Kotlin, nanti), dan integrasi lain. Controller API memanggil **Service yang sama** dengan bot Telegram (lihat [02-architecture.md](02-architecture.md)). Semua fitur diuji lewat API dengan test skenario ([test-cases/](test-cases/README.md)).

Dokumentasi OpenAPI di-generate otomatis (Scramble) di `/docs/api` — dipakai untuk tipe TypeScript SPA dan client Kotlin.

## 1. Autentikasi & Tenant

Satu guard **`auth:sanctum`**, dua cara login:

| Klien                     | Cara                                                  | Alur                                                                                                                       |
| ------------------------- | ----------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| **Web SPA** (domain sama) | Sesi cookie Sanctum + CSRF                            | `GET /sanctum/csrf-cookie` → `POST /api/v1/auth/login` → cookie sesi; `POST /api/v1/auth/logout`                           |
| **Android / integrasi**   | Personal access token `Authorization: Bearer <token>` | `POST /api/v1/auth/token` (`email`, `password`, `device_name`, `code` 2FA bila aktif) → token; `DELETE /api/v1/auth/token` |

- Auth memakai **Fortify headless** (JSON): register, login, 2FA (challenge, aktifkan, QR, recovery code), passkey (WebAuthn options → verify), lupa/reset password, verifikasi email, konfirmasi password.
- Token punya **abilities** `read` (semua GET) dan `write` (membuat/mengubah/menghapus) serta **masa berlaku** (default 90 hari, `SANCTUM_EXPIRATION`); bisa dicabut dari pengaturan. Sesi SPA dianggap punya semua ability. **Peran tetap dicek Policy** (BR-B09) untuk semua klien.
- `source` transaksi (BR-B07): sesi SPA → `web`, token → `api`.
- Keluarga aktif: header `X-Family-Id` (opsional, default `current_family_id`); divalidasi keanggotaan → `TenantContext`. Belum punya keluarga → `409`.
- Rate limit: 60 request/menit per user/token; login 5/menit per email+IP.

### Kenapa Sanctum, bukan JWT

|                                           | Sanctum (dipakai)                                         | JWT                                               |
| ----------------------------------------- | --------------------------------------------------------- | ------------------------------------------------- |
| Web SPA                                   | Cookie httpOnly + CSRF, token tidak pernah ada di browser | Token di browser → rawan XSS, perlu refresh token |
| Android                                   | Personal access token, disimpan terenkripsi di perangkat  | Access + refresh token, rotasi                    |
| Cabut akses (logout, anggota dikeluarkan) | Instan (hapus sesi/token di DB)                           | Sulit sebelum token kedaluwarsa (perlu blacklist) |
| Kompleksitas                              | Bawaan Laravel                                            | Paket tambahan + rotasi refresh token             |

JWT dipertimbangkan hanya jika nanti backend dipecah menjadi beberapa service yang memverifikasi token tanpa DB.

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

Error validasi / business rule (pelanggaran business rule menyertakan `rule` agar klien bisa bereaksi tanpa membaca teks):

```json
{
    "message": "Tanggal transaksi tidak boleh lebih dari hari ini.",
    "errors": {
        "date": ["Tanggal transaksi tidak boleh lebih dari hari ini."]
    },
    "rule": "BR-B06"
}
```

| Status          | Arti                                            |
| --------------- | ----------------------------------------------- |
| 200 / 201 / 204 | OK / dibuat / dihapus                           |
| 401             | belum login / token tidak valid                 |
| 403             | role tidak berhak                               |
| 404             | tidak ada **atau milik keluarga lain** (BR-I06) |
| 409             | belum punya keluarga (arahkan ke onboarding)    |
| 422             | validasi / business rule                        |
| 429             | rate limit / kuota struk habis                  |

**Idempotensi (BR-B13):** semua `POST` yang membuat data (transaksi, struk, konfirmasi struk, setoran tabungan, …) menerima header `Idempotency-Key: <uuid>`. Request ulang dengan key sama dalam 24 jam mengembalikan response pertama tanpa membuat data baru (middleware `EnsureIdempotency`, disimpan di Redis per `family + user + key`).

Nominal selalu integer rupiah; tanggal `YYYY-MM-DD`; datetime ISO 8601 dengan offset zona keluarga. Response memakai **API Resource** (`app/Http/Resources`).

## 3. Endpoint

Semua path di bawah `/api/v1`. ✅ = sudah ada · ⬜ = ditambah saat migrasi ([18](18-api-first-migration.md), tahap M1–M2).

### Auth & akun

|     | Method            | Path                                                                          | Fungsi                                           |
| --- | ----------------- | ----------------------------------------------------------------------------- | ------------------------------------------------ |
| ⬜  | POST              | `/auth/register` · `/auth/login` · `/auth/logout`                             | daftar, login & logout SPA (sesi)                |
| ⬜  | POST/DELETE       | `/auth/token`                                                                 | buat / cabut token Android                       |
| ⬜  | POST              | `/auth/two-factor-challenge`                                                  | kode 2FA / recovery code saat login              |
| ⬜  | POST              | `/auth/passkeys/options` · `/auth/passkeys/login`                             | login passkey (WebAuthn)                         |
| ⬜  | POST              | `/auth/forgot-password` · `/auth/reset-password`                              | lupa & reset password                            |
| ⬜  | POST · GET        | `/auth/email/verification-notification` · `/auth/email/verify/{id}/{hash}`    | verifikasi email                                 |
| ✅  | GET               | `/me`                                                                         | profil, daftar keluarga & role, izin (`can`)     |
| ⬜  | PATCH/DELETE      | `/me`                                                                         | ubah profil (nama, email, tampilan) / hapus akun |
| ⬜  | PUT               | `/me/password`                                                                | ganti password                                   |
| ⬜  | POST/DELETE · GET | `/me/two-factor` · `/me/two-factor/qr-code` · `/me/two-factor/recovery-codes` | kelola 2FA                                       |
| ⬜  | GET/POST/DELETE   | `/me/passkeys`                                                                | kelola passkey                                   |
| ⬜  | GET/POST/DELETE   | `/me/tokens`                                                                  | kelola token API                                 |

### Keluarga & anggota

|     | Method           | Path                                                               | Fungsi                                                |
| --- | ---------------- | ------------------------------------------------------------------ | ----------------------------------------------------- |
| ⬜  | POST             | `/families`                                                        | buat keluarga (onboarding: dompet & kategori default) |
| ⬜  | PATCH            | `/families/{id}`                                                   | pengaturan keluarga (nama, zona waktu, awal periode)  |
| ⬜  | PUT              | `/families/{id}/switch`                                            | jadikan keluarga aktif                                |
| ⬜  | POST             | `/families/{id}/leave`                                             | keluar dari keluarga                                  |
| ⬜  | GET/PATCH/DELETE | `/members` · `/members/{id}`                                       | anggota, ubah peran, keluarkan (BR-I01..I04)          |
| ⬜  | GET/POST/DELETE  | `/invitations` · `/invitations/{id}`                               | undangan keluarga                                     |
| ⬜  | GET · POST       | `/invitations/token/{token}` · `/invitations/token/{token}/accept` | lihat & terima undangan                               |

### Master data

|     | Method          | Path                                                  | Fungsi                       |
| --- | --------------- | ----------------------------------------------------- | ---------------------------- |
| ✅  | GET             | `/wallets`                                            | dompet + saldo + total       |
| ✅  | POST/PUT/DELETE | `/wallets` · `/wallets/{id}`                          | kelola dompet (owner/admin)  |
| ⬜  | PATCH           | `/wallets/{id}/archive`                               | arsip / aktifkan             |
| ✅  | GET             | `/categories?type=expense`                            | kategori (tree)              |
| ⬜  | POST/PUT/DELETE | `/categories` · `/categories/{id}`                    | kelola kategori (+ keywords) |
| ⬜  | PATCH · POST    | `/categories/{id}/archive` · `/categories/{id}/merge` | arsip, gabung (BR-E03)       |
| ⬜  | GET             | `/tags`                                               | daftar tag                   |

### Transaksi

|     | Method         | Path                         | Fungsi                                                                                                                           |
| --- | -------------- | ---------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| ✅  | GET            | `/transactions`              | list + filter (`from,to,type,category_id[],wallet_id[],user_id[],tag[],q,min,max,source,sort`) + `meta.totals` & subtotal harian |
| ✅  | POST           | `/transactions`              | buat transaksi                                                                                                                   |
| ✅  | POST           | `/transactions/parse`        | parse teks bebas → preview per baris + `missing`, tanpa simpan                                                                   |
| ✅  | GET/PUT/DELETE | `/transactions/{id}`         | detail / ubah / hapus                                                                                                            |
| ⬜  | POST           | `/transactions/bulk`         | aksi massal: hapus, ganti kategori                                                                                               |
| ⬜  | GET            | `/transactions/{id}/history` | riwayat perubahan (activity log)                                                                                                 |

### Budget, tabungan, laporan

|     | Method              | Path                                            | Fungsi                                                             |
| --- | ------------------- | ----------------------------------------------- | ------------------------------------------------------------------ |
| ✅  | GET                 | `/budgets?period=2026-10`                       | budget + realisasi + status                                        |
| ✅  | PUT                 | `/budgets`                                      | set budget (bulk)                                                  |
| ⬜  | POST                | `/budgets/copy`                                 | salin dari periode lalu                                            |
| ⬜  | DELETE              | `/budgets/{id}`                                 | hapus budget                                                       |
| ⬜  | GET/POST/PUT/DELETE | `/savings` · `/savings/{id}`                    | tujuan tabungan (BR-K01..K04)                                      |
| ⬜  | POST                | `/savings/{id}/contributions`                   | setor ke tabungan                                                  |
| ✅  | GET                 | `/summary?period=2026-10`                       | ringkasan (saldo, pemasukan, pengeluaran, selisih, Δ%)             |
| ⬜  | GET                 | `/dashboard/{widget}`                           | widget dashboard (arus kas, kategori, tren, anggota, insight, …)   |
| ✅  | GET                 | `/reports/{laporan}?from&to`                    | laporan per jenis (arus kas, kategori, anggota, dompet, budget, …) |
| ⬜  | GET                 | `/reports/{laporan}/export?format=xlsx` / `pdf` | unduh export dengan filter yang sama                               |

### Struk & notifikasi

|     | Method | Path                     | Fungsi                                                                                            |
| --- | ------ | ------------------------ | ------------------------------------------------------------------------------------------------- |
| ⬜  | GET    | `/receipts`              | struk terakhir + `meta.quota`                                                                     |
| ✅  | POST   | `/receipts`              | upload (multipart `file`) → `202` + draft + `meta.quota`; kuota habis → `429`                     |
| ✅  | GET    | `/receipts/{id}`         | status & hasil parse (draft)                                                                      |
| ⬜  | GET    | `/receipts/{id}/image`   | file struk (ter-auth)                                                                             |
| ✅  | PATCH  | `/receipts/{id}`         | koreksi draft (`total`, `receipt_date`, `merchant`, `category_id`, `wallet_id`, `items[]`)        |
| ✅  | DELETE | `/receipts/{id}`         | batalkan draft (`rejected`)                                                                       |
| ✅  | POST   | `/receipts/{id}/confirm` | `{"mode":"total"}` / `{"mode":"split"}` → `201` + transaksi; konfirmasi ulang tidak menambah data |
| ⬜  | GET    | `/notifications`         | notifikasi (budget, pengingat, …)                                                                 |
| ⬜  | POST   | `/notifications/read`    | tandai dibaca (semua / per id)                                                                    |

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
app/Http/Controllers/Api/V1/{Domain}/   # TransactionController, WalletController, AuthController, ...
app/Http/Requests/Api/V1/{Domain}/
app/Http/Resources/{Domain}/            # TransactionResource, WalletResource, ...
routes/api.php                          # prefix v1; auth:sanctum, set.family, throttle:api
```

- Rule validasi bersama ada di `app/Http/Requests/Concerns` (mis. `TransactionRules`), dipakai juga oleh bot.
- Perubahan breaking → `/api/v2`; perubahan non-breaking (field baru) boleh di v1.
