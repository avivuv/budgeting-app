# API Contract — Onboarding (Buat Keluarga)

Skenario test: [test-cases/onboarding.md](../test-cases/onboarding.md) · Rule: BR-I01, BR-D01, BR-B13 · Konvensi umum: [README](README.md)

| Method | Path        | Fungsi                                                  | Peran               | Ability | Status |
| ------ | ----------- | ------------------------------------------------------- | ------------------- | ------- | ------ |
| POST   | `/families` | buat keluarga baru; pembuat jadi Owner & keluarga aktif | user login mana pun | `write` | ✅ ada |

Dipakai setelah daftar (user belum punya keluarga → semua endpoint keluarga menjawab `409` `code: no_family`), dan untuk membuat keluarga tambahan. Tidak butuh keluarga aktif.

### POST `/families`

| Field              | Tipe    | Wajib | Aturan                                                  | Contoh            |
| ------------------ | ------- | ----- | ------------------------------------------------------- | ----------------- |
| `name`             | string  | ya    | maks 100                                                | `"Keluarga Budi"` |
| `period_start_day` | integer | tidak | 1–28, default 1 — tanggal awal periode bulanan (BR-D01) | `25`              |

Zona waktu default `Asia/Jakarta`. Keluarga baru langsung berisi dompet **Tunai** dan kategori bawaan (`config/budgeting.php`). Header `Idempotency-Key` disarankan: kiriman ulang tidak membuat keluarga kedua (BR-B13).

**Response `201`** — `{ "message": "Keluarga Budi siap digunakan.", "data": Me }` (`GET /me`, dengan `current_family_id` = keluarga baru dan peran `owner`).

| Status | Field              | Pesan                                               |
| ------ | ------------------ | --------------------------------------------------- |
| 422    | `name`             | Nama keluarga wajib diisi. / maksimal 100 karakter. |
| 422    | `period_start_day` | Tanggal awal periode harus antara 1 dan 28.         |
| 403    | —                  | token tanpa `write`                                 |
| 401    | —                  | belum login                                         |

## Penyesuaian dari implementasi lama (M2 — Onboarding, 4 Nov 2026)

- Endpoint baru (sebelumnya hanya di web `POST /onboarding`).
