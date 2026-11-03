# API Contract — Transaksi

Skenario test: [test-cases/transactions.md](../test-cases/transactions.md) · Rule: BR-A01…A03, BR-B01…B14, BR-C01…C04 · Konvensi umum: [README](README.md)

| Method | Path                         | Fungsi                                        | Peran                                 | Ability | Status |
| ------ | ---------------------------- | --------------------------------------------- | ------------------------------------- | ------- | ------ |
| GET    | `/transactions`              | daftar + filter + total                       | semua anggota                         | `read`  | ✅ ada |
| GET    | `/transactions/{id}`         | detail                                        | semua anggota                         | `read`  | ✅ ada |
| GET    | `/transactions/{id}/history` | riwayat perubahan (activity log)              | semua anggota                         | `read`  | ✅ ada |
| GET    | `/tags`                      | daftar tag keluarga                           | semua anggota                         | `read`  | ✅ ada |
| POST   | `/transactions`              | catat transaksi                               | Owner, Admin, Member                  | `write` | ✅ ada |
| POST   | `/transactions/parse`        | "ketik cepat": teks → pratinjau, tanpa simpan | Owner, Admin, Member                  | `write` | ✅ ada |
| PUT    | `/transactions/{id}`         | ubah                                          | Owner/Admin: semua · Member: miliknya | `write` | ✅ ada |
| DELETE | `/transactions/{id}`         | hapus (soft delete)                           | Owner/Admin: semua · Member: miliknya | `write` | ✅ ada |
| POST   | `/transactions/bulk`         | aksi massal: hapus / ganti kategori           | sama dengan ubah/hapus, per transaksi | `write` | ✅ ada |

## Objek `Transaction`

| Field                      | Tipe                   | Keterangan                                                                       |
| -------------------------- | ---------------------- | -------------------------------------------------------------------------------- |
| `id`                       | integer                |                                                                                  |
| `type`                     | enum                   | `expense` · `income` · `transfer` (BR-B01)                                       |
| `amount`                   | integer                | rupiah, > 0 (BR-A02)                                                             |
| `amount_formatted`         | string                 | `"Rp 12.500"`                                                                    |
| `date`                     | date                   | `YYYY-MM-DD`                                                                     |
| `note`                     | string \| null         | maks 255                                                                         |
| `source`                   | enum                   | `web` (sesi SPA) · `api` (token) · `telegram` · `receipt` · `recurring` (BR-B07) |
| `wallet`                   | `{ id, name }`         | dompet asal                                                                      |
| `to_wallet`                | `{ id, name }` \| null | hanya transfer                                                                   |
| `category`                 | `{ id, name }` \| null | `null` untuk transfer (BR-B03)                                                   |
| `tags`                     | string[]               | huruf kecil, tanpa `#`                                                           |
| `created_by`               | `{ id, name }`         | pencatat                                                                         |
| `created_at`, `updated_at` | datetime               | ISO 8601                                                                         |

---

### GET `/transactions`

**Query** (semua opsional): `from`, `to` (`YYYY-MM-DD`, inklusif — BR-D04) · `type` · `category_id[]` (induk ikut sub-kategori) · `wallet_id[]` (asal atau tujuan) · `user_id[]` · `tag[]` · `source` · `min`, `max` (boleh `"50rb"`) · `q` (cari di catatan) · `sort` (`date`, `-date`, `amount`, `-amount`; default `-date`) · `page` · `per_page` (1–100, default 20).

**Response `200`**

```json
{
    "data": [Transaction, ...],
    "meta": {
        "current_page": 1, "last_page": 3, "per_page": 20, "total": 47,
        "totals": { "income": 8000000, "expense": 2287500 },
        "daily": { "2026-10-30": { "income": 0, "expense": 12500 } }
    },
    "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

- `meta.totals` = total **seluruh hasil filter** (bukan hanya halaman ini); transfer tidak dihitung (BR-B11, INV-06).
- `meta.daily` = subtotal per tanggal untuk tanggal-tanggal di halaman ini, seluruh transaksi tanggal itu yang cocok filter (untuk tampilan kartu per tanggal di HP).
- Transaksi terhapus tidak pernah muncul (BR-B08). Filter tidak valid → `422`.

---

### POST `/transactions` — catat

| Field          | Tipe               | Wajib                 | Aturan                                                                         | Contoh                          |
| -------------- | ------------------ | --------------------- | ------------------------------------------------------------------------------ | ------------------------------- |
| `type`         | enum               | ya                    |                                                                                | `"expense"`                     |
| `amount`       | integer \| string  | ya                    | > 0, ≤ 999.999.999.999; teks diparse (BR-A03)                                  | `"12.500"`, `"75rb"`, `"1,5jt"` |
| `date`         | date               | ya                    | tidak setelah hari ini, tidak > 5 tahun lalu, zona keluarga (BR-B06)           | `"2026-10-30"`                  |
| `wallet_id`    | integer            | ya                    | dompet aktif keluarga sendiri (BR-B04, BR-B05)                                 |                                 |
| `to_wallet_id` | integer            | transfer              | ≠ `wallet_id`, aktif (BR-B03)                                                  |                                 |
| `category_id`  | integer            | pemasukan/pengeluaran | jenis sama dengan transaksi, aktif (BR-B02); diabaikan untuk transfer (BR-B03) |                                 |
| `note`         | string             | tidak                 | maks 255                                                                       | `"kopi susu"`                   |
| `tags`         | string[] \| string | tidak                 | maks 10, @50; `"#Kantor, mudik"` diterima                                      | `["kantor"]`                    |

Header `Idempotency-Key` (disarankan): request ulang dengan key sama → response pertama, tanpa transaksi baru (BR-B13).

**Response `201`** — `{ "data": Transaction, "meta": { "warnings": ["Saldo GoPay menjadi minus (-Rp 5.000)."] } }` — peringatan saldo minus untuk dompet non-kartu-kredit (BR-C03), transaksi tetap tersimpan.

| Status | Field          | Pesan                                                                                                           | Rule           |
| ------ | -------------- | --------------------------------------------------------------------------------------------------------------- | -------------- |
| 422    | `amount`       | Nominal wajib diisi. / Nominal minimal 1. / Nominal harus berupa bilangan bulat.                                | BR-A02         |
| 422    | `date`         | Tanggal transaksi tidak boleh melewati hari ini. / Tanggal transaksi tidak boleh lebih dari 5 tahun yang lalu.  | BR-B06         |
| 422    | `category_id`  | Kategori wajib diisi. / Jenis kategori tidak sesuai dengan jenis transaksi. / Kategori ini sudah diarsipkan ... | BR-B02, BR-B05 |
| 422    | `to_wallet_id` | Dompet tujuan wajib diisi. / Dompet asal dan tujuan transfer tidak boleh sama.                                  | BR-B03         |
| 422    | `wallet_id`    | Dompet ini sudah diarsipkan ...                                                                                 | BR-B05         |
| 404    | —              | dompet/kategori milik keluarga lain                                                                             | BR-B04, BR-I06 |
| 403    | —              | Viewer / token tanpa `write`                                                                                    | BR-B09         |

---

### PUT `/transactions/{id}` — ubah

Body sama dengan POST. `source`, pencatat, dan struk asal tidak berubah (BR-B07). Dompet/kategori lama yang kini diarsipkan tetap boleh dipertahankan. **Response `200`** — `{ "data": Transaction, "meta": { "warnings": [...] } }`. Saldo dompet lama & baru ikut berubah (BR-C04).

Error = POST, ditambah `403` bila Member mengubah transaksi orang lain, `404` untuk transaksi keluarga lain/terhapus.

---

### DELETE `/transactions/{id}`

**Response `204`** — soft delete; hilang dari daftar, total, saldo (BR-B08); tercatat di activity log.

---

### POST `/transactions/bulk` — aksi massal

| Field         | Tipe      | Wajib                  | Aturan                                         |
| ------------- | --------- | ---------------------- | ---------------------------------------------- |
| `action`      | enum      | ya                     | `delete` · `category`                          |
| `ids`         | integer[] | ya                     | 1–100 id unik                                  |
| `category_id` | integer   | bila `action=category` | kategori aktif, sejenis dengan semua transaksi |

**Response `200`** — `{ "message": "3 transaksi dihapus.", "meta": { "affected": 3 } }`.

- **Semua atau tidak sama sekali**: bila satu transaksi gagal (bukan milik Member, transfer saat ganti kategori, kategori beda jenis), tidak ada yang berubah.
- Setiap transaksi tercatat di activity log (BR-B14).

| Status | Field                            | Pesan                                            | Rule   |
| ------ | -------------------------------- | ------------------------------------------------ | ------ |
| 422    | `ids` / `action` / `category_id` | validasi                                         |        |
| 422    | `category_id`                    | Transfer tidak memakai kategori.                 | BR-B03 |
| 403    | —                                | salah satu transaksi tidak boleh diubah user ini | BR-B09 |
| 404    | —                                | salah satu id milik keluarga lain / tidak ada    | BR-I06 |

---

### POST `/transactions/parse` — ketik cepat

Body `{ "text": "bensin 50rb\nparkir 5rb @gopay kemarin" }` (maks 20 baris). **Response `200`** — satu pratinjau per baris, **tanpa menyimpan**:

```json
{
    "data": [
        {
            "line": "bensin 50rb",
            "type": "expense",
            "type_label": "Pengeluaran",
            "amount": 50000,
            "amount_formatted": "Rp 50.000",
            "date": "2026-10-31",
            "date_formatted": "31 Okt 2026",
            "wallet_id": 1,
            "wallet_name": "Tunai",
            "to_wallet_id": null,
            "to_wallet_name": null,
            "category_id": 4,
            "category_name": "Transportasi",
            "category_source": "keyword",
            "note": "bensin",
            "tags": [],
            "missing": [],
            "complete": true
        }
    ]
}
```

`missing` berisi field yang belum bisa ditebak (mis. `["amount"]`); klien meminta user melengkapinya lalu mengirim `POST /transactions`.

---

### GET `/transactions/{id}/history`

**Response `200`** — terbaru dulu:

```json
{ "data": [
    { "event": "updated", "title": "mengubah transaksi", "who": "Ibu", "at": "2026-10-31T09:12:00+07:00",
      "changes": [{ "field": "Nominal", "old": "Rp 12.500", "new": "Rp 15.000" }] },
    { "event": "created", "title": "mencatat transaksi", "who": "Ayah", "at": "2026-10-30T08:00:00+07:00", "changes": [] }
] },
    { "event": "updated", "title": "mengubah transaksi", "who": "Ibu", "at": "2026-10-31T09:12:00+07:00",
      "changes": [{ "field": "Nominal", "old": "Rp 12.500", "new": "Rp 15.000" }] }
] }
```

Riwayat disimpan 30 hari (activity log, BR-B14).

---

### GET `/tags`

**Response `200`** — `{ "data": ["kantor", "mudik"] }` — tag yang pernah dipakai keluarga, urut abjad (untuk saran saat mengetik).

## Penyesuaian dari implementasi lama (M2 — Transaksi, 3 Nov 2026)

- ✅ `POST /transactions/parse` memakai **snake_case** seperti endpoint lain (sebelumnya camelCase: `walletId` → `wallet_id`).
- ✅ `source` = `web` untuk sesi SPA, `api` untuk token (sebelumnya selalu `api`).
- ✅ `PUT` ikut mengembalikan `meta.warnings`.
- ✅ `GET /transactions` menambah `meta.daily`.
- ✅ Endpoint baru: `bulk`, `history`, `tags`.
