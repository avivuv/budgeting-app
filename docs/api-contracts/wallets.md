# API Contract — Dompet

Skenario test: [test-cases/wallets.md](../test-cases/wallets.md) · Rule: BR-C01…C06, BR-B05 · Konvensi umum: [README](README.md)

| Method | Path                    | Fungsi                          | Peran         | Ability | Status |
| ------ | ----------------------- | ------------------------------- | ------------- | ------- | ------ |
| GET    | `/wallets`              | daftar dompet + saldo + total   | semua anggota | `read`  | ✅ ada |
| POST   | `/wallets`              | buat dompet                     | Owner, Admin  | `write` | ✅ ada |
| PUT    | `/wallets/{id}`         | ubah dompet                     | Owner, Admin  | `write` | ✅ ada |
| PATCH  | `/wallets/{id}/archive` | arsipkan / aktifkan             | Owner, Admin  | `write` | ✅ ada |
| DELETE | `/wallets/{id}`         | hapus dompet yang belum dipakai | Owner, Admin  | `write` | ✅ ada |

## Objek `Wallet`

| Field               | Tipe           | Keterangan                                                                                        |
| ------------------- | -------------- | ------------------------------------------------------------------------------------------------- |
| `id`                | integer        |                                                                                                   |
| `name`              | string         | maks 50                                                                                           |
| `type`              | enum           | `cash` · `bank` · `ewallet` · `credit_card` · `other`                                             |
| `type_label`        | string         | `Tunai` · `Bank` · `E-wallet` · `Kartu kredit` · `Lainnya`                                        |
| `initial_balance`   | integer        | saldo awal; boleh minus (tagihan kartu kredit)                                                    |
| `balance`           | integer        | saldo saat ini = saldo awal + pemasukan − pengeluaran − transfer keluar + transfer masuk (BR-C01) |
| `balance_formatted` | string         | `"Rp 1.500.000"`, minus: `"-Rp 2.000.000"`                                                        |
| `color`             | string \| null | mis. `"#16a34a"`                                                                                  |
| `icon`              | string \| null | nama ikon, mis. `"wallet"`                                                                        |
| `is_archived`       | boolean        |                                                                                                   |

```json
{
    "id": 7,
    "name": "Tabungan Agus",
    "type": "bank",
    "type_label": "Bank",
    "initial_balance": 1500000,
    "balance": 1500000,
    "balance_formatted": "Rp 1.500.000",
    "color": null,
    "icon": null,
    "is_archived": false
}
```

---

### GET `/wallets` — daftar dompet

**Query**

| Param              | Tipe    | Wajib | Keterangan                                          |
| ------------------ | ------- | ----- | --------------------------------------------------- |
| `include_archived` | boolean | tidak | `true` → dompet arsip ikut tampil (default `false`) |

**Response `200`**

```json
{
    "data": [
        { "id": 1, "name": "Tunai", "type": "cash", "...": "..." },
        { "id": 7, "name": "Tabungan Agus", "...": "..." }
    ],
    "meta": {
        "total_balance": 3250000,
        "total_balance_formatted": "Rp 3.250.000"
    }
}
```

- Urut: dompet aktif dulu, lalu `sort_order`, lalu nama.
- `meta.total_balance` = Σ saldo dompet **tidak diarsipkan**; kartu kredit menyumbang nilai minus (BR-C02). Tidak terpengaruh `include_archived`.
- Hanya dompet keluarga aktif (`X-Family-Id`).

---

### POST `/wallets` — buat dompet

**Body**

| Field             | Tipe              | Wajib | Aturan                                                                      | Contoh                  |
| ----------------- | ----------------- | ----- | --------------------------------------------------------------------------- | ----------------------- |
| `name`            | string            | ya    | maks 50 karakter                                                            | `"Tabungan Agus"`       |
| `type`            | enum              | ya    | salah satu nilai `type`                                                     | `"bank"`                |
| `initial_balance` | integer \| string | tidak | default `0`; teks uang diparse (BR-A03); −999.999.999.999 … 999.999.999.999 | `"1.500.000"`, `"-2jt"` |
| `color`           | string            | tidak | maks 20                                                                     | `"#2563eb"`             |
| `icon`            | string            | tidak | maks 50                                                                     | `"landmark"`            |

```http
POST /api/v1/wallets
Content-Type: application/json

{ "name": "Tabungan Agus", "type": "bank", "initial_balance": "1.500.000" }
```

**Response `201`** — `{ "data": Wallet }` dengan `balance` = `initial_balance`.

**Error**

| Status | Field             | Pesan                                                | Kapan                                                     |
| ------ | ----------------- | ---------------------------------------------------- | --------------------------------------------------------- |
| 422    | `name`            | Nama wajib diisi. / Nama maksimal 50 karakter.       | kosong / terlalu panjang                                  |
| 422    | `type`            | Jenis wajib diisi. / Jenis yang dipilih tidak valid. | kosong / tidak dikenal                                    |
| 422    | `initial_balance` | Saldo awal tidak valid.                              | teks tidak bisa dibaca (`abc`, `50,5`) atau di luar batas |
| 403    | —                 | Kamu tidak punya akses untuk aksi ini.               | Member / Viewer / token tanpa `write`                     |

---

### PUT `/wallets/{id}` — ubah dompet

**Body** — sama dengan POST (semua field dikirim ulang; field opsional yang tidak dikirim menjadi `null` / `0`).

**Response `200`** — `{ "data": Wallet }`. Mengubah `initial_balance` mengubah `balance` dengan selisih yang sama; transaksi tidak berubah.

**Error** — sama dengan POST, ditambah `404` bila dompet tidak ada atau milik keluarga lain.

---

### PATCH `/wallets/{id}/archive` — arsipkan / aktifkan

**Body**

| Field      | Tipe    | Wajib | Keterangan                            |
| ---------- | ------- | ----- | ------------------------------------- |
| `archived` | boolean | ya    | `true` = arsipkan, `false` = aktifkan |

**Response `200`** — `{ "data": Wallet }`.

- Dompet arsip tidak ikut `total_balance` (BR-C02) dan ditolak untuk transaksi **baru** (BR-B05); transaksi lama tetap dihitung.

**Error** — `422` `archived` wajib boolean · `403` · `404`.

---

### DELETE `/wallets/{id}` — hapus dompet

**Response `204`** — dompet dihapus permanen.

**Error**

| Status | Field     | Pesan                                                                       | Rule   |
| ------ | --------- | --------------------------------------------------------------------------- | ------ |
| 422    | `general` | Dompet sudah memiliki transaksi sehingga tidak bisa dihapus. Arsipkan saja. | BR-C05 |
| 403    | —         | Kamu tidak punya akses untuk aksi ini.                                      | BR-B09 |
| 404    | —         | Data tidak ditemukan.                                                       | BR-I06 |

## Riwayat penyesuaian (M2 — Dompet, 3 Nov 2026)

- ✅ Endpoint `PATCH /wallets/{id}/archive` ditambahkan.
- ✅ `meta.total_balance_formatted` ditambahkan.
- ✅ Error business rule menyertakan `rule` (M1).
- ✅ Pesan validasi Bahasa Indonesia lewat `lang/id` (M1).
- ✅ Request API pindah ke `Api/V1/Wallet/WalletRequest`; aturan dipakai bersama web lewat trait `WalletRules`.
- ✅ Saldo awal minus yang diketik (`-2jt`) kini diterima; sebelumnya ditolak.
- ✅ Saldo awal tidak terbaca → pesan "Saldo awal tidak valid."
