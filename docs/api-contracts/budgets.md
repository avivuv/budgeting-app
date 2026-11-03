# API Contract — Budget

Skenario test: [test-cases/budgets.md](../test-cases/budgets.md) · Rule: BR-F01…F09, BR-D01, BR-D02 · Konvensi umum: [README](README.md)

| Method | Path            | Fungsi                                     | Peran         | Ability | Status |
| ------ | --------------- | ------------------------------------------ | ------------- | ------- | ------ |
| GET    | `/budgets`      | budget satu periode + realisasi + total    | semua anggota | `read`  | ✅ ada |
| PUT    | `/budgets`      | atur (buat/ubah) beberapa budget sekaligus | Owner, Admin  | `write` | ✅ ada |
| POST   | `/budgets/copy` | salin budget dari periode sebelumnya       | Owner, Admin  | `write` | ✅ ada |
| DELETE | `/budgets/{id}` | hapus satu budget                          | Owner, Admin  | `write` | ✅ ada |

Periode = periode bulanan keluarga (BR-D01), ditulis `YYYY-MM` sesuai bulan label (BR-D02). Default periode berjalan. Boleh periode lalu, berjalan, dan satu periode ke depan (BR-F08).

## Objek `Budget`

| Field                                          | Tipe                                                      | Keterangan                                                   |
| ---------------------------------------------- | --------------------------------------------------------- | ------------------------------------------------------------ |
| `id`                                           | integer                                                   |                                                              |
| `period`                                       | string                                                    | `"2026-10"`                                                  |
| `category`                                     | `{ id, name, icon, color, is_sub_category, is_archived }` | kategori pengeluaran (BR-F01)                                |
| `amount`, `amount_formatted`                   | integer, string                                           | batas budget                                                 |
| `spent`, `spent_formatted`                     | integer, string                                           | realisasi, **termasuk sub-kategori** (BR-F02)                |
| `remaining`, `remaining_formatted`             | integer, string                                           | `amount − spent`, boleh negatif (BR-F03)                     |
| `percentage`                                   | number                                                    | 1 desimal (BR-F03)                                           |
| `status`, `status_label`                       | enum, string                                              | `safe` Aman · `warning` Waspada · `over` Lewat (BR-F04)      |
| `daily_allowance`, `daily_allowance_formatted` | integer, string                                           | jatah per hari sisa periode (BR-F06); `0` bila periode lewat |

```json
{
    "id": 12,
    "period": "2026-10",
    "category": {
        "id": 3,
        "name": "Makanan & Minuman",
        "icon": "utensils",
        "color": "#f97316",
        "is_sub_category": false,
        "is_archived": false
    },
    "amount": 800000,
    "amount_formatted": "Rp 800.000",
    "spent": 650000,
    "spent_formatted": "Rp 650.000",
    "remaining": 150000,
    "remaining_formatted": "Rp 150.000",
    "percentage": 81.3,
    "status": "warning",
    "status_label": "Waspada",
    "daily_allowance": 150000,
    "daily_allowance_formatted": "Rp 150.000"
}
```

## Response daftar (dipakai GET, PUT, POST copy)

```json
{
    "data": [Budget, ...],
    "meta": {
        "period": { "key": "2026-10", "label": "Oktober 2026", "range": "1 Okt – 31 Okt 2026", "start": "2026-10-01", "end": "2026-10-31",
                    "previous_key": "2026-09", "next_key": "2026-11", "is_current": true },
        "totals": { "amount": 900000, "amount_formatted": "Rp 900.000", "spent": 700000, "spent_formatted": "Rp 700.000",
                    "remaining": 200000, "remaining_formatted": "Rp 200.000", "percentage": 77.8,
                    "status": "safe", "status_label": "Aman", "daily_allowance": 200000, "daily_allowance_formatted": "Rp 200.000" },
        "available_categories": [{ "id": 4, "name": "Transportasi", "parent_name": null }],
        "can_manage": true
    }
}
```

- Urut nominal budget terbesar dulu.
- `meta.totals` tidak menghitung dua kali: budget sub-kategori dilewati bila induknya juga punya budget (realisasi induk sudah mencakupnya).
- `meta.period.next_key` = periode berikutnya yang masih boleh dibuka (null bila sudah di batas BR-F08).
- `meta.available_categories` = kategori pengeluaran aktif (dan sub-kategorinya) yang belum punya budget di periode ini — untuk pilihan "tambah budget".
- `meta.can_manage` = user boleh mengatur budget (Owner/Admin).

---

### GET `/budgets`

| Param    | Tipe   | Keterangan                           |
| -------- | ------ | ------------------------------------ |
| `period` | string | `YYYY-MM`; kosong = periode berjalan |

**Response `200`** — response daftar.

| Status | Field    | Pesan                                                  | Rule   |
| ------ | -------- | ------------------------------------------------------ | ------ |
| 422    | `period` | Format periode tidak valid.                            |        |
| 422    | `period` | Budget hanya bisa diatur sampai satu periode ke depan. | BR-F08 |

---

### PUT `/budgets` — atur beberapa budget

| Field                   | Tipe              | Wajib | Aturan                                                       | Contoh      |
| ----------------------- | ----------------- | ----- | ------------------------------------------------------------ | ----------- |
| `period`                | string            | tidak | `YYYY-MM`, BR-F08; default periode berjalan                  | `"2026-10"` |
| `budgets`               | array             | ya    | 1–100 baris                                                  |             |
| `budgets.*.category_id` | integer           | ya    | kategori **pengeluaran** keluarga sendiri, tidak boleh dobel | `3`         |
| `budgets.*.amount`      | integer \| string | ya    | > 0, ≤ 999.999.999.999; teks diparse (BR-A03)                | `"800rb"`   |

Kategori yang sudah punya budget di periode itu → nominalnya **diubah** (satu budget per kategori per periode, BR-F01); belum → dibuat. **Semua atau tidak sama sekali.** Ambang notifikasi 80%/100% dicek ulang (BR-F05). Request ulang identik tidak membuat data ganda (PUT bersifat idempoten).

**Response `200`** — response daftar periode tersebut.

| Status | Field                   | Pesan                                                                   | Rule   |
| ------ | ----------------------- | ----------------------------------------------------------------------- | ------ |
| 422    | `budgets`               | Budget wajib diisi. / maksimal 100                                      |        |
| 422    | `budgets.N.amount`      | Nominal budget wajib diisi. / minimal 1. / harus berupa bilangan bulat. | BR-A02 |
| 422    | `budgets.N.category_id` | Kategori memiliki nilai duplikat.                                       |        |
| 422    | `budgets.N.category_id` | Budget hanya bisa dibuat untuk kategori pengeluaran.                    | BR-F01 |
| 422    | `budgets.N.category_id` | Kategori ini sudah diarsipkan dan tidak bisa diberi budget baru.        | BR-F09 |
| 422    | `period`                | Budget hanya bisa diatur sampai satu periode ke depan.                  | BR-F08 |
| 404    | —                       | kategori milik keluarga lain                                            | BR-I06 |
| 403    | —                       | Member / Viewer / token tanpa `write`                                   | BR-B09 |

---

### POST `/budgets/copy` — salin dari periode lalu

Body `{ "period": "2026-11" }` (opsional, default periode berjalan). Menyalin budget periode **sebelumnya** hanya untuk kategori yang belum punya budget di periode tujuan dan belum diarsipkan (BR-F07, BR-F09). Aman diulang: panggilan kedua menyalin 0.

**Response `200`** — response daftar periode tujuan, ditambah `"message": "2 budget disalin dari periode lalu."` (atau `"Tidak ada budget baru untuk disalin."`) dan `meta.copied` (jumlah yang dibuat).

Error: `422 period` (format / BR-F08), `403` seperti PUT.

---

### DELETE `/budgets/{id}`

**Response `204`**. Tercatat di activity log. Budget keluarga lain / tidak ada → `404`; Member/Viewer → `403`.

## Penyesuaian dari implementasi lama (M2 — Budget, 4 Nov 2026)

- Response `GET`/`PUT` berubah ke bentuk daftar standar: `data` = Budget[], periode & total pindah ke `meta`; semua key **snake_case** (sebelumnya `data.budgets[].categoryName`).
- Endpoint baru: `POST /budgets/copy`, `DELETE /budgets/{id}`.
- Periode lebih dari satu ke depan sekarang `422` (sebelumnya diam-diam diganti periode berikutnya).
- Error per baris di PUT memakai key `budgets.N.category_id` (sebelumnya `category_id` tanpa tahu baris mana).
- Budget baru di kategori yang diarsipkan ditolak (BR-F09).
- `meta.available_categories` dan `meta.can_manage` ditambahkan agar SPA/Android tidak perlu request lain.
