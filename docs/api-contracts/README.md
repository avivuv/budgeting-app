# API Contract per Fitur

Kontrak = **janji bentuk request & response** setiap endpoint, ditulis per fitur sebelum/bersamaan dengan implementasi. Dipakai oleh:

- **Frontend SPA & Android** — tahu persis apa yang dikirim dan diterima tanpa membaca kode backend.
- **Test API** — setiap skenario di [test-cases](../test-cases/README.md) mengecek response sesuai kontrak ini.
- **OpenAPI** (Scramble, `/docs/api`) — di-generate dari kode; CI memastikan hasilnya cocok dengan kontrak. Bila berbeda, yang benar adalah kontrak (atau kontrak di-update dulu).

Satu fitur = satu file `docs/api-contracts/<fitur>.md`, berpasangan dengan `docs/test-cases/<fitur>.md`.

## 1. Konvensi Umum (berlaku di semua fitur)

### Base URL & header

| Header                       | Wajib                                | Isi                                                                                           |
| ---------------------------- | ------------------------------------ | --------------------------------------------------------------------------------------------- |
| `Accept`                     | ya                                   | `application/json`                                                                            |
| `Content-Type`               | untuk body                           | `application/json` (upload: `multipart/form-data`)                                            |
| `Authorization`              | Android                              | `Bearer <token>`                                                                              |
| Cookie sesi + `X-XSRF-TOKEN` | SPA                                  | dari `GET /sanctum/csrf-cookie` lalu login                                                    |
| `X-Family-Id`                | tidak                                | keluarga aktif; default `current_family_id` user                                              |
| `Idempotency-Key`            | disarankan untuk `POST` pembuat data | UUID; request ulang dengan key sama dalam 24 jam → response pertama, tanpa data baru (BR-B13) |

Base URL: `/api/v1`.

### Tipe data

| Jenis         | Format                                                                                              | Contoh                                 |
| ------------- | --------------------------------------------------------------------------------------------------- | -------------------------------------- |
| Uang          | integer rupiah (input boleh teks seperti diketik user: `"1.500.000"`, `"75rb"`, `"1,5jt"` — BR-A03) | `1500000`                              |
| Uang tampilan | string, selalu disertakan di samping angka                                                          | `"Rp 1.500.000"`                       |
| Tanggal       | `YYYY-MM-DD`                                                                                        | `"2026-10-31"`                         |
| Waktu         | ISO 8601 dengan offset zona keluarga                                                                | `"2026-10-31T19:20:00+07:00"`          |
| Enum          | string `value` + `*_label` Bahasa Indonesia                                                         | `"type": "bank", "type_label": "Bank"` |
| Kosong        | `null` (bukan string kosong)                                                                        | `"color": null`                        |

### Envelope response

```json
// satu data
{ "data": { ... } }

// daftar
{ "data": [ ... ], "meta": { ... } }

// daftar dengan pagination
{ "data": [ ... ], "meta": { "current_page": 1, "last_page": 7, "per_page": 20, "total": 134 }, "links": { "first": "...", "last": "...", "prev": null, "next": "..." } }
```

### Error

```json
{
    "message": "Nama wajib diisi.",
    "errors": { "name": ["Nama wajib diisi."] },
    "rule": "BR-C05"
}
```

- `errors` selalu ada untuk 422 (key = nama field; error yang tidak terkait field memakai key `general`).
- `rule` hanya ada bila penolakan berasal dari business rule ([11-business-rules](../11-business-rules.md)).
- Semua pesan dalam **Bahasa Indonesia**.

| Status | Kapan                                           | Body                                                    |
| ------ | ----------------------------------------------- | ------------------------------------------------------- |
| `200`  | berhasil (baca / ubah)                          | `data`                                                  |
| `201`  | data baru dibuat                                | `data`                                                  |
| `202`  | diterima, diproses di latar belakang (struk)    | `data`                                                  |
| `204`  | dihapus                                         | kosong                                                  |
| `401`  | belum login / token tidak valid / kedaluwarsa   | `{"message": "Silakan login terlebih dahulu."}`         |
| `403`  | peran atau ability token tidak cukup            | `{"message": "Kamu tidak punya akses untuk aksi ini."}` |
| `404`  | tidak ada **atau milik keluarga lain** (BR-I06) | `{"message": "Data tidak ditemukan."}`                  |
| `409`  | user belum punya keluarga                       | `{"message": "...", "code": "no_family"}`               |
| `422`  | validasi / business rule                        | `message` + `errors` (+ `rule`)                         |
| `429`  | rate limit / kuota habis                        | `message`                                               |

### Hak akses

Setiap endpoint menuliskan: **peran** yang boleh (Owner, Admin, Member, Viewer — BR-B09) dan **ability token** (`read` untuk GET, `write` untuk lainnya). Sesi SPA dianggap punya semua ability.

## 2. Format satu endpoint

```
### <METHOD> <path> — <fungsi>

Akses: peran · ability
Request: tabel field (nama, tipe, wajib, aturan, contoh)
Response: status + contoh JSON
Error: tabel status/field/pesan/rule
```

## 3. Daftar kontrak

| Fitur                                  | Kontrak                              | Skenario test                                      | Status          |
| -------------------------------------- | ------------------------------------ | -------------------------------------------------- | --------------- |
| Auth                                   | [auth.md](auth.md)                   | [auth.md](../test-cases/auth.md)                   | ✅ selesai (M1) |
| Dompet                                 | [wallets.md](wallets.md)             | [wallets.md](../test-cases/wallets.md)             | ✅ selesai (M2) |
| Kategori                               | [categories.md](categories.md)       | [categories.md](../test-cases/categories.md)       | ✅ selesai (M2) |
| Transaksi                              | [transactions.md](transactions.md)   | [transactions.md](../test-cases/transactions.md)   | ✅ selesai (M2) |
| Budget                                 | [budgets.md](budgets.md)             | [budgets.md](../test-cases/budgets.md)             | ✅ selesai (M2) |
| Ringkasan, Dashboard, Laporan & Export | [reports.md](reports.md)             | [reports.md](../test-cases/reports.md)             | ✅ selesai (M2) |
| Tabungan                               | [savings.md](savings.md)             | [savings.md](../test-cases/savings.md)             | ✅ selesai (M2) |
| Scan Struk                             | [receipts.md](receipts.md)           | [receipts.md](../test-cases/receipts.md)           | ✅ selesai (M2) |
| Keluarga, Anggota & Undangan           | [family.md](family.md)               | [family.md](../test-cases/family.md)               | ✅ selesai (M2) |
| Onboarding                             | [onboarding.md](onboarding.md)       | [onboarding.md](../test-cases/onboarding.md)       | ✅ selesai (M2) |
| Notifikasi                             | [notifications.md](notifications.md) | [notifications.md](../test-cases/notifications.md) | ✅ selesai (M2) |
| Profil, Keamanan & Token               | settings.md                          | settings.md                                        | belum           |
