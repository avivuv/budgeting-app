# API Contract — Tabungan (Tujuan Menabung)

Skenario test: [test-cases/savings.md](../test-cases/savings.md) · Rule: BR-K01…K04, BR-A02/A03, BR-B09, BR-B13 · Konvensi umum: [README](README.md)

| Method | Path                          | Fungsi                        | Peran                | Ability | Status |
| ------ | ----------------------------- | ----------------------------- | -------------------- | ------- | ------ |
| GET    | `/savings`                    | daftar tujuan + progres       | semua anggota        | `read`  | ✅ ada |
| POST   | `/savings`                    | buat tujuan                   | Owner, Admin         | `write` | ✅ ada |
| PUT    | `/savings/{id}`               | ubah tujuan                   | Owner, Admin         | `write` | ✅ ada |
| DELETE | `/savings/{id}`               | hapus tujuan                  | Owner, Admin         | `write` | ✅ ada |
| POST   | `/savings/{id}/contributions` | setor / ambil tabungan manual | Owner, Admin, Member | `write` | ✅ ada |

## Objek `SavingGoal`

| Field                                        | Tipe                    | Keterangan                                                                         |
| -------------------------------------------- | ----------------------- | ---------------------------------------------------------------------------------- |
| `id`, `name`, `icon`, `color`                |                         | nama maks 80                                                                       |
| `target_amount`, `target_formatted`          | integer, string         | > 0 (BR-K01)                                                                       |
| `progress`, `progress_formatted`             | integer, string         | saldo dompet terkait hari ini, atau total setoran manual (BR-K01)                  |
| `remaining`, `remaining_formatted`           | integer, string         | `max(target − progres, 0)` (BR-K03)                                                |
| `percentage`                                 | number                  | 1 desimal, boleh > 100                                                             |
| `achieved`                                   | boolean                 | progres ≥ target (BR-K03)                                                          |
| `wallet_id`, `wallet_name`                   | integer \| null, string | dompet terkait; `null` = tabungan manual                                           |
| `deadline`, `deadline_formatted`             | date \| null, string    | `"2027-06-30"`, `"30 Jun 2027"`                                                    |
| `monthly_needed`, `monthly_needed_formatted` | integer \| null         | setoran per bulan agar tercapai (BR-K04); `null` tanpa deadline / lewat / tercapai |

---

### GET `/savings`

**Response `200`** — `{ "data": SavingGoal[], "meta": { "can_manage": true, "can_contribute": true } }`. Urut sesuai pembuatan.

---

### POST `/savings` — buat · PUT `/savings/{id}` — ubah

| Field           | Tipe              | Wajib | Aturan                                              | Contoh           |
| --------------- | ----------------- | ----- | --------------------------------------------------- | ---------------- |
| `name`          | string            | ya    | maks 80                                             | `"Dana Darurat"` |
| `target_amount` | integer \| string | ya    | > 0, ≤ 999.999.999.999; teks diparse (BR-A03)       | `"30jt"`         |
| `wallet_id`     | integer           | tidak | dompet keluarga sendiri; progres mengikuti saldonya |                  |
| `deadline`      | date              | tidak | `YYYY-MM-DD`                                        | `"2027-06-30"`   |
| `icon`, `color` | string            | tidak | maks 50 / 20                                        |                  |

**Response** `201` (buat) / `200` (ubah) — `{ "data": SavingGoal }`. Saat tersimpan, status tercapai diperbarui (BR-K03).

| Status | Field           | Pesan                                                                  | Rule   |
| ------ | --------------- | ---------------------------------------------------------------------- | ------ |
| 422    | `name`          | Nama wajib diisi. / maksimal 80 karakter.                              |        |
| 422    | `target_amount` | Target wajib diisi. / Target minimal 1. / harus berupa bilangan bulat. | BR-K01 |
| 422    | `deadline`      | format tanggal tidak valid                                             |        |
| 404    | —               | dompet / tujuan milik keluarga lain                                    | BR-I06 |
| 403    | —               | Member, Viewer, token tanpa `write`                                    | BR-B09 |

---

### DELETE `/savings/{id}`

**Response `204`**. Transaksi dan dompet tidak tersentuh. Tercatat di activity log.

---

### POST `/savings/{id}/contributions` — setor / ambil

| Field       | Tipe              | Wajib | Aturan                                 |
| ----------- | ----------------- | ----- | -------------------------------------- |
| `direction` | enum              | ya    | `deposit` (setor) · `withdraw` (ambil) |
| `amount`    | integer \| string | ya    | > 0; teks diparse (`"500rb"`)          |

Header `Idempotency-Key` disarankan: kiriman ulang dengan key sama tidak menyetor dua kali (BR-B13).

**Response `200`** — `{ "message": "Tabungan ditambahkan.", "data": SavingGoal }` (atau `"Tabungan diambil."`).

| Status | Field       | Pesan                                                                                               | Rule   |
| ------ | ----------- | --------------------------------------------------------------------------------------------------- | ------ |
| 422    | `amount`    | Jumlah yang diambil melebihi tabungan yang terkumpul.                                               | BR-K02 |
| 422    | `amount`    | Tujuan ini mengikuti saldo dompet, jadi tidak bisa diisi manual. Catat transfer ke dompet tersebut. | BR-K02 |
| 422    | `direction` | Jenis yang dipilih tidak valid.                                                                     |        |
| 403    | —           | Viewer / token tanpa `write`                                                                        | BR-B09 |
| 404    | —           | tujuan keluarga lain                                                                                | BR-I06 |

## Penyesuaian dari implementasi lama (M2 — Tabungan, 4 Nov 2026)

- Semua endpoint baru (sebelumnya hanya ada di web dan widget dashboard).
