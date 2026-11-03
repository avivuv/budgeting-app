# API Contract — Scan Struk

Skenario test: [test-cases/receipts.md](../test-cases/receipts.md) · Rule: BR-H01…H11, BR-B09, BR-B13, INV-09 · Konvensi umum: [README](README.md) · Alur & AI: [06-receipt-scanning](../06-receipt-scanning.md)

| Method | Path                     | Fungsi                                 | Peran                | Ability | Status |
| ------ | ------------------------ | -------------------------------------- | -------------------- | ------- | ------ |
| GET    | `/receipts`              | 20 struk terakhir + kuota              | semua anggota        | `read`  | ✅ ada |
| POST   | `/receipts`              | unggah file → dibaca di latar belakang | Owner, Admin, Member | `write` | ✅ ada |
| GET    | `/receipts/{id}`         | status & hasil baca (draft)            | semua anggota        | `read`  | ✅ ada |
| GET    | `/receipts/{id}/image`   | file struk (privat, hanya anggota)     | semua anggota        | `read`  | ✅ ada |
| PATCH  | `/receipts/{id}`         | koreksi draft                          | Owner, Admin, Member | `write` | ✅ ada |
| POST   | `/receipts/{id}/confirm` | simpan jadi transaksi                  | Owner, Admin, Member | `write` | ✅ ada |
| DELETE | `/receipts/{id}`         | batalkan draft (tanpa transaksi)       | Owner, Admin, Member | `write` | ✅ ada |

**Alur**: `POST` (202, status `pending`) → klien polling `GET /receipts/{id}` sampai status `parsed` / `failed` → user meninjau & mengoreksi (`PATCH`) → `confirm`. **Tidak ada transaksi sebelum `confirm`** (BR-H08). Draft tak dikonfirmasi 24 jam → `expired` (BR-H10).

## Objek `Receipt`

| Field                                      | Tipe                                                  | Keterangan                                                                                                                                                                  |
| ------------------------------------------ | ----------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `id`                                       | integer                                               |                                                                                                                                                                             |
| `status`, `status_label`                   | enum, string                                          | `pending` Antre · `processing` Sedang dibaca · `parsed` Menunggu konfirmasi · `confirmed` Tersimpan · `failed` Gagal dibaca · `rejected` Dibatalkan · `expired` Kedaluwarsa |
| `document_type`, `document_type_label`     | enum \| null                                          | `receipt` Struk · `transfer_proof` Bukti transfer · `ewallet` E-wallet · `invoice` Invoice                                                                                  |
| `mime_type`, `has_file`, `image_url`       | string, bool, string \| null                          | `image_url` = `/api/v1/receipts/{id}/image` selama file masih ada                                                                                                           |
| `merchant`                                 | string \| null                                        |                                                                                                                                                                             |
| `receipt_date`                             | date \| null                                          | tanggal struk (BR-H06)                                                                                                                                                      |
| `total`, `total_formatted`                 | integer \| null                                       | total dibayar (BR-H01)                                                                                                                                                      |
| `discount`, `tax`                          | integer                                               |                                                                                                                                                                             |
| `payment_method`                           | string \| null                                        |                                                                                                                                                                             |
| `category_id`, `wallet_id`                 | integer \| null                                       | usulan kategori / dompet (bisa dikoreksi)                                                                                                                                   |
| `confidence`                               | number \| null                                        | 0–1                                                                                                                                                                         |
| `flags`                                    | string[]                                              | `total_mismatch` (BR-H03) · `date_replaced` (BR-H06) · `possible_duplicate` (BR-H04)                                                                                        |
| `error`                                    | string \| null                                        | alasan gagal                                                                                                                                                                |
| `items`                                    | `[{ id, name, qty, unit_price, total, category_id }]` | item terbaca; `total` boleh minus (diskon item)                                                                                                                             |
| `items_total`                              | integer                                               | Σ item                                                                                                                                                                      |
| `transactions`                             | Transaction[]                                         | kosong sampai dikonfirmasi                                                                                                                                                  |
| `expires_at`, `confirmed_at`, `created_at` | datetime \| null                                      |                                                                                                                                                                             |

---

### GET `/receipts`

**Response `200`** — `{ "data": Receipt[] (tanpa items/transactions), "meta": { "quota": { "used": 3, "limit": 30, "remaining": 27 }, "max_file_mb": 10 } }`, terbaru dulu.

### POST `/receipts` — unggah

`multipart/form-data` field `file`: JPG/PNG/WEBP/HEIC/PDF (maks 3 halaman), maks 10 MB (BR-H11).

**Response `202`** — `{ "data": Receipt, "meta": { "quota": {...} } }`. Kuota langsung terpakai (BR-H05).

| Status | Field  | Pesan                                                                                                       | Rule   |
| ------ | ------ | ----------------------------------------------------------------------------------------------------------- | ------ |
| 422    | `file` | File wajib diisi. / Format file tidak didukung. ... / Ukuran file maksimal 10 MB. / PDF maksimal 3 halaman. | BR-H11 |
| 422    | `file` | File struk yang sama sudah pernah diunggah.                                                                 | BR-H04 |
| 429    | `file` | Kuota scan struk bulan ini (30) sudah habis. Catat transaksi secara manual.                                 | BR-H05 |
| 403    | —      | Viewer / token tanpa `write`                                                                                | BR-B09 |

### GET `/receipts/{id}/image`

**Response `200`** — isi file dengan `Content-Type` aslinya. File sudah dihapus (BR-H10) → `404`.

### PATCH `/receipts/{id}` — koreksi (BR-H09)

| Field                 | Tipe              | Wajib | Aturan                                   |
| --------------------- | ----------------- | ----- | ---------------------------------------- |
| `receipt_date`        | date              | ya    | `YYYY-MM-DD`                             |
| `total`               | integer \| string | ya    | > 0; teks diparse (`"99rb"`)             |
| `merchant`            | string            | tidak | maks 120                                 |
| `category_id`         | integer           | tidak | kategori pengeluaran keluarga sendiri    |
| `wallet_id`           | integer           | tidak | dompet keluarga sendiri                  |
| `items`               | array             | tidak | maks 100; dikirim = mengganti semua item |
| `items.*.name`        | string            | ya    | maks 120                                 |
| `items.*.qty`         | number            | tidak | default 1                                |
| `items.*.unit_price`  | integer           | tidak |                                          |
| `items.*.total`       | integer \| string | ya    | boleh minus                              |
| `items.*.category_id` | integer           | tidak |                                          |

**Response `200`** — `{ "data": Receipt }`. Hanya status `parsed` yang bisa dikoreksi → selain itu `422 status` "Hanya hasil baca yang menunggu konfirmasi yang bisa diubah." (BR-H09). Kategori/dompet keluarga lain → `404`.

### POST `/receipts/{id}/confirm` — simpan

Body `{ "mode": "total" | "split" }`. `total` = satu transaksi sebesar `total` (BR-H01); `split` = satu transaksi per kategori item, diskon/pajak dibagi proporsional, Σ = total persis (BR-H02, INV-09). Transaksi ber-`source` `receipt`, catatan = merchant.

**Response `201`** — `{ "message": "Struk tersimpan sebagai 2 transaksi.", "data": Receipt }` (dengan `transactions`). Konfirmasi ulang → response yang sama, tidak ada transaksi baru (BR-H07, BR-B13).

| Status | Field         | Pesan                                       | Rule   |
| ------ | ------------- | ------------------------------------------- | ------ |
| 422    | `mode`        | Cara simpan wajib diisi. / tidak valid      |        |
| 422    | `wallet_id`   | Pilih dompet sebelum menyimpan.             | BR-H08 |
| 422    | `category_id` | Pilih kategori sebelum menyimpan.           | BR-H08 |
| 422    | `status`      | Struk ini tidak sedang menunggu konfirmasi. | BR-H07 |

### DELETE `/receipts/{id}` — batalkan

**Response `200`** — `{ "message": "Struk dibatalkan, tidak ada transaksi yang dibuat.", "data": Receipt }` (status `rejected`; data tetap ada untuk riwayat, file dihapus 7 hari kemudian — BR-H10). Hanya status `parsed`/`failed`; selain itu `422 status`.

## Penyesuaian dari implementasi lama (M2 — Struk, 4 Nov 2026)

- Endpoint baru: `GET /receipts`, `GET /receipts/{id}/image`.
- Response: `date` → `receipt_date` (sama dengan field PATCH); tambah `status_label`, `document_type_label`, `has_file`, `image_url`, `items_total`.
- `confirm` dan `DELETE` menyertakan `message`.
