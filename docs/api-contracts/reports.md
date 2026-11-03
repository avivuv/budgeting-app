# API Contract — Ringkasan, Dashboard, Laporan & Export

Skenario test: [test-cases/reports.md](../test-cases/reports.md) · Rule: BR-A05, BR-B11, BR-C01/C02, BR-D01…D04, BR-E04, INV-02…06 · Konvensi umum: [README](README.md) · Isi widget & laporan: [07-dashboard-reports](../07-dashboard-reports.md)

| Method | Path                       | Fungsi                                                  | Peran         | Ability | Status |
| ------ | -------------------------- | ------------------------------------------------------- | ------------- | ------- | ------ |
| GET    | `/summary`                 | kartu ringkasan periode (saldo, masuk, keluar, selisih) | semua anggota | `read`  | ✅ ada |
| GET    | `/dashboard/{widget}`      | satu widget dashboard                                   | semua anggota | `read`  | ✅ ada |
| GET    | `/reports/{report}`        | satu laporan                                            | semua anggota | `read`  | ✅ ada |
| GET    | `/reports/{report}/export` | unduh laporan Excel / PDF                               | semua anggota | `read`  | ✅ ada |

Semua angka dari service yang sama dengan bot & export, sehingga selalu sama (INV-03). Transfer tidak dihitung sebagai pemasukan/pengeluaran (BR-B11). Transaksi terhapus tidak dihitung (BR-B08). Viewer boleh membaca & mengekspor.

## Filter dashboard (`/summary`, `/dashboard/{widget}`)

| Param       | Tipe    | Keterangan                                                                                           |
| ----------- | ------- | ---------------------------------------------------------------------------------------------------- |
| `period`    | string  | `YYYY-MM` (bulan label, BR-D02); kosong = periode berjalan; **tidak boleh** setelah periode berjalan |
| `wallet_id` | integer | hanya transaksi dompet ini (asal/tujuan); id keluarga lain tidak cocok dengan apa pun → angka 0      |
| `user_id`   | integer | hanya transaksi yang dicatat anggota ini                                                             |

| Status | Field    | Pesan                           |
| ------ | -------- | ------------------------------- |
| 422    | `period` | Format periode tidak valid.     |
| 422    | `period` | Periode tersebut belum dimulai. |

### Objek `Period`

`{ "key": "2026-10", "label": "Oktober 2026", "range": "1 Okt – 31 Okt 2026", "start": "2026-10-01", "end": "2026-10-31", "previous_key": "2026-09", "next_key": null, "is_current": true }` — `next_key` null untuk periode berjalan.

### Objek `Stat`

`{ "amount": 2287500, "formatted": "Rp 2.287.500", "previous": 1800000, "change": 27.1 }` — `change` = % dibanding periode sebelumnya, 1 desimal; `null` bila periode lalu 0 (tampil "Baru", BR-A05).

---

### GET `/summary`

**Response `200`**

```json
{ "data": {
    "period": Period,
    "summary": { "balance": Stat, "income": Stat, "expense": Stat, "net": Stat }
} }
```

`balance` = total saldo dompet aktif di akhir periode (atau hari ini untuk periode berjalan, BR-C02). `net` = income − expense.

---

### GET `/dashboard/{widget}`

**Response `200`** — `{ "data": <isi widget>, "meta": { "period": Period } }`. Widget tidak dikenal → `404`.

| `widget`     | `data`                                                                                                                                                                                 |
| ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `cashflow`   | 6 periode terakhir s.d. periode ini: `[{ key, label, income, expense, net }]`                                                                                                          |
| `categories` | pengeluaran per kategori induk (sub ikut induk, BR-E04), terbesar dulu: `[{ category_id, name, color, icon, amount, formatted, percentage }]` — jumlahnya = total pengeluaran (INV-04) |
| `trend`      | per hari periode: `[{ date, label, cumulative, pace }]`; `cumulative` null untuk hari setelah hari ini; `pace` = laju rata-rata periode lalu                                           |
| `members`    | pengeluaran per pencatat: `[{ user_id, name, amount, formatted, count, percentage }]` — jumlahnya = total pengeluaran (INV-05); anggota terhapus `user_id` null "Mantan anggota"       |
| `wallets`    | saldo tiap dompet di akhir periode: `[{ id, name, type, type_label, initial_balance, balance, balance_formatted, color, icon, is_archived }]`                                          |
| `budgets`    | budget periode (objek `Budget`, [budgets.md](budgets.md)) — berlaku sekeluarga, filter dompet/anggota diabaikan                                                                        |
| `goals`      | tujuan tabungan + progres ([savings.md](savings.md))                                                                                                                                   |
| `recent`     | 10 transaksi terbaru s.d. akhir periode (objek `Transaction`, [transactions.md](transactions.md))                                                                                      |
| `insights`   | kalimat singkat: `["Pengeluaran naik 27,1% dibanding periode lalu.", ...]`                                                                                                             |

---

### GET `/reports/{report}`

`report` ∈ `cashflow` · `categories` · `members` · `wallets` · `budget` · `yearly` · `merchants` · `tags`; lainnya → `404`.

| Param        | Tipe | Keterangan                                                                        |
| ------------ | ---- | --------------------------------------------------------------------------------- |
| `from`, `to` | date | `YYYY-MM-DD`, inklusif (BR-D04), maks 1 tahun; kosong keduanya = periode berjalan |
| `year`       | int  | untuk `yearly` (2000–2100); default tahun `to`                                    |
| `type`       | enum | untuk `categories`: `expense` (default) / `income`                                |

**Response `200`**

```json
{ "data": {
    "tab": "categories", "title": "Kategori",
    "from": "2026-10-01", "to": "2026-10-31", "range_label": "1 Okt 2026 – 31 Okt 2026",
    "year": 2026, "type": "expense",
    "totals": { "income": Money, "expense": Money, "net": Money },
    "rows": [ ... ],
    "budget_totals": null
} }
```

`Money` = `{ "amount": 650000, "formatted": "Rp 650.000" }`.

| `report`     | `rows`                                                                                                                                                    |
| ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `cashflow`   | per periode dalam rentang: `{ key, label, start, end, income, expense, net, opening, closing }` (Money); `closing` = total saldo (INV-02)                 |
| `categories` | `{ id, name, color, amount, share, previous, change, count, children: [...] }` — induk termasuk sub (BR-E04); dibanding rentang sebelumnya sepanjang sama |
| `members`    | `{ user_id, name, income, expense, expense_share, count }`                                                                                                |
| `wallets`    | `{ id, name, type_label, is_archived, opening, income, expense, transfer_in, transfer_out, closing }` (BR-C01); dompet arsip hanya bila ada mutasi        |
| `budget`     | objek `Budget` periode yang memuat `from`; `budget_totals` = total budget                                                                                 |
| `yearly`     | objek `{ year, months: ["Jan", ...], categories: [{ id, name, values: [12 angka], total }], month_totals, total, income, peak_month }`                    |
| `merchants`  | 20 merchant/catatan teratas: `{ name, amount, count, share }`                                                                                             |
| `tags`       | per tag: `{ name, amount, count, share }`                                                                                                                 |

| Status | Field  | Pesan                                                                                                 |
| ------ | ------ | ----------------------------------------------------------------------------------------------------- |
| 422    | `to`   | Tanggal akhir harus ... setelah atau sama dengan tanggal awal. / Rentang laporan maksimal satu tahun. |
| 422    | `from` | tanggal awal wajib diisi bila tanggal akhir diisi                                                     |
| 422    | `type` | Jenis yang dipilih tidak valid.                                                                       |

---

### GET `/reports/{report}/export`

Param sama dengan laporan + `format` = `xlsx` (default) / `pdf`. **Response `200`** = file unduhan:

- `xlsx`: sheet Ringkasan, laporan terpilih, Transaksi (semua transaksi rentang), Kategori, Budget — angka integer rupiah, sama dengan laporan (INV-03).
- `pdf`: tabel laporan terpilih.
- Nama file `laporan-<laporan>-<from>_<to>.<ext>` (tahunan: `laporan-tahunan-<tahun>.<ext>`).

Error sama dengan laporan, ditambah `422 format`. Klien SPA mengunduh lewat sesi (cookie), Android lewat `Authorization: Bearer` lalu menyimpan file.

## Penyesuaian dari implementasi lama (M2 — Laporan, 4 Nov 2026)

- Semua key response **snake_case** (sebelumnya camelCase dari DTO: `rangeLabel`, `peakMonth`, `transferIn`, …).
- Endpoint baru: `GET /dashboard/{widget}` dan `GET /reports/{report}/export`.
- `period` setelah periode berjalan → `422` (sebelumnya diam-diam diganti periode berjalan).
- Export menghormati `type` laporan kategori.
