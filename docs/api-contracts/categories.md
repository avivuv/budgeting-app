# API Contract — Kategori

Skenario test: [test-cases/categories.md](../test-cases/categories.md) · Rule: BR-E01…E05 · Konvensi umum: [README](README.md)

| Method | Path                       | Fungsi                            | Peran         | Ability | Status  |
| ------ | -------------------------- | --------------------------------- | ------------- | ------- | ------- |
| GET    | `/categories`              | pohon kategori                    | semua anggota | `read`  | ✅ ada  |
| POST   | `/categories`              | buat kategori / sub-kategori      | Owner, Admin  | `write` | ⬜ baru |
| PUT    | `/categories/{id}`         | ubah kategori                     | Owner, Admin  | `write` | ⬜ baru |
| PATCH  | `/categories/{id}/archive` | arsipkan / aktifkan               | Owner, Admin  | `write` | ⬜ baru |
| POST   | `/categories/{id}/merge`   | gabungkan ke kategori lain        | Owner, Admin  | `write` | ⬜ baru |
| DELETE | `/categories/{id}`         | hapus kategori yang belum dipakai | Owner, Admin  | `write` | ⬜ baru |

## Objek `Category`

| Field         | Tipe            | Keterangan                                                        |
| ------------- | --------------- | ----------------------------------------------------------------- |
| `id`          | integer         |                                                                   |
| `type`        | enum            | `expense` · `income`                                              |
| `name`        | string          | maks 50; unik per (jenis, induk) — BR-E02                         |
| `parent_id`   | integer \| null | `null` = kategori induk; maks 2 level — BR-E01                    |
| `icon`        | string \| null  |                                                                   |
| `color`       | string \| null  |                                                                   |
| `keywords`    | string[]        | kata kunci tebakan kategori (BR-E05), mis. `["bensin", "parkir"]` |
| `is_archived` | boolean         |                                                                   |
| `children`    | Category[]      | hanya di `GET /categories` (sub-kategori)                         |

```json
{
    "id": 4,
    "type": "expense",
    "name": "Transportasi",
    "parent_id": null,
    "icon": "car",
    "color": "#2563eb",
    "keywords": ["bensin", "parkir", "tol"],
    "is_archived": false,
    "children": [
        {
            "id": 31,
            "type": "expense",
            "name": "Ojek Online",
            "parent_id": 4,
            "...": "..."
        }
    ]
}
```

---

### GET `/categories`

| Param              | Tipe    | Keterangan                           |
| ------------------ | ------- | ------------------------------------ |
| `type`             | enum    | `expense` / `income`; kosong = semua |
| `include_archived` | boolean | default `false`                      |

**Response `200`** — `{ "data": Category[] }` berupa pohon: induk dengan `children`. Urut `sort_order`, lalu nama. `type` tidak dikenal → `422`.

---

### POST `/categories` — buat

| Field       | Tipe               | Wajib | Aturan                                                                        | Contoh              |
| ----------- | ------------------ | ----- | ----------------------------------------------------------------------------- | ------------------- |
| `type`      | enum               | ya    | `expense` / `income`                                                          | `"expense"`         |
| `name`      | string             | ya    | maks 50                                                                       | `"Ojek Online"`     |
| `parent_id` | integer            | tidak | kategori induk keluarga sendiri, jenis sama, bukan sub-kategori               | `4`                 |
| `icon`      | string             | tidak | maks 50                                                                       |                     |
| `color`     | string             | tidak | maks 20                                                                       |                     |
| `keywords`  | string[] \| string | tidak | maks 50 kata, @50 karakter; teks dipisah koma juga diterima (`"gojek, grab"`) | `["gojek", "grab"]` |

**Response `201`** — `{ "data": Category }`.

| Status | Field       | Pesan                                                        | Rule   |
| ------ | ----------- | ------------------------------------------------------------ | ------ |
| 422    | `name`      | Nama wajib diisi. / Nama maksimal 50 karakter.               |        |
| 422    | `type`      | Jenis wajib diisi. / Jenis yang dipilih tidak valid.         |        |
| 422    | `name`      | Nama kategori sudah dipakai.                                 | BR-E02 |
| 422    | `parent_id` | Sub-kategori tidak bisa memiliki sub-kategori lagi.          | BR-E01 |
| 422    | `parent_id` | Sub-kategori harus memiliki jenis yang sama dengan induknya. | BR-E01 |
| 404    | —           | Data tidak ditemukan. (induk milik keluarga lain)            | BR-I06 |
| 403    | —           | Member / Viewer / token tanpa `write`                        | BR-B09 |

---

### PUT `/categories/{id}` — ubah

Body sama dengan POST. **Response `200`** — `{ "data": Category }`.

Tambahan error:

| Status | Field       | Pesan                                                                                                         | Rule             |
| ------ | ----------- | ------------------------------------------------------------------------------------------------------------- | ---------------- |
| 422    | `type`      | Jenis kategori tidak bisa diubah karena sudah dipakai transaksi atau punya sub-kategori.                      | BR-E06 ⚠️ usulan |
| 422    | `parent_id` | Sub-kategori tidak bisa memiliki sub-kategori lagi. (kategori yang punya sub-kategori dijadikan sub-kategori) | BR-E01           |

---

### PATCH `/categories/{id}/archive`

Body `{ "archived": true | false }` → **`200`** `{ "data": Category }`. Kategori arsip tidak muncul di pilihan untuk transaksi baru dan ditolak saat dipakai (`422` di `category_id`); transaksi lama tetap dihitung di laporan.

---

### POST `/categories/{id}/merge` — gabungkan

Semua transaksi kategori `{id}` dipindah ke `target_id`, lalu kategori `{id}` dihapus (BR-E03).

| Field       | Tipe    | Wajib | Aturan                                              |
| ----------- | ------- | ----- | --------------------------------------------------- |
| `target_id` | integer | ya    | kategori keluarga sendiri, jenis sama, bukan `{id}` |

**Response `200`** — `{ "data": Category (tujuan), "meta": { "moved_transactions": 12 } }`.

| Status | Field       | Pesan                                                       | Rule   |
| ------ | ----------- | ----------------------------------------------------------- | ------ |
| 422    | `target_id` | Pilih kategori tujuan yang berbeda.                         | BR-E03 |
| 422    | `target_id` | Kategori hanya bisa digabung dengan kategori berjenis sama. | BR-E03 |
| 422    | `target_id` | Kategori tujuan sedang diarsipkan. ⚠️ usulan                | BR-E03 |
| 422    | `general`   | Hapus atau pindahkan sub-kategorinya terlebih dahulu.       | BR-E01 |
| 404    | —           | target milik keluarga lain                                  | BR-I06 |

---

### DELETE `/categories/{id}`

**Response `204`**.

| Status | Field     | Pesan                                                                       | Rule   |
| ------ | --------- | --------------------------------------------------------------------------- | ------ |
| 422    | `general` | Kategori sudah dipakai transaksi. Arsipkan atau gabungkan ke kategori lain. | BR-E03 |
| 422    | `general` | Hapus atau pindahkan sub-kategorinya terlebih dahulu.                       | BR-E01 |

## ⚠️ Perlu keputusan sebelum implementasi

Temuan saat memeriksa kode yang ada:

1. **Ubah jenis kategori yang sudah dipakai** — sekarang ditolak dengan pesan yang salah ("Sub-kategori harus memiliki jenis yang sama dengan induknya."). Usulan: pesan sendiri + rule baru **BR-E06** di [11-business-rules](../11-business-rules.md).
2. **Gabung kategori tidak tercatat di activity log** — transaksi dipindah sekaligus lewat satu query, sehingga BR-B14 ("setiap perubahan transaksi tercatat") terlewati. Usulan: setiap transaksi yang dipindah tercatat (kategori lama → baru).
3. **Budget kategori yang digabung ikut terhapus diam-diam** (cascade). Usulan: budget dipindah ke kategori tujuan; bila tujuan sudah punya budget di periode yang sama, nominalnya **dijumlahkan**.
4. **Hapus kategori yang belum dipakai tapi punya budget** — budget ikut terhapus. Usulan: boleh, budget ikut terhapus (kategorinya memang tidak terpakai).
5. **Gabung ke kategori yang diarsipkan** — sekarang diizinkan. Usulan: ditolak.
