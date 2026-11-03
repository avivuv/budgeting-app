# API Contract — Notifikasi

Skenario test: [test-cases/notifications.md](../test-cases/notifications.md) · Rule: BR-F05 · Konvensi umum: [README](README.md) · Isi: [07-dashboard-reports §4b](../07-dashboard-reports.md)

| Method | Path                  | Fungsi                                      | Peran      | Ability | Status |
| ------ | --------------------- | ------------------------------------------- | ---------- | ------- | ------ |
| GET    | `/notifications`      | 50 notifikasi terbaru + jumlah belum dibaca | user login | `read`  | ✅ ada |
| POST   | `/notifications/read` | tandai satu / semua dibaca                  | user login | `write` | ✅ ada |

Notifikasi milik **user** (bukan keluarga): daftar memuat notifikasi dari semua keluarganya, masing-masing dengan `family_id`. Tidak butuh keluarga aktif. Saat ini berisi peringatan budget 80% / 100% (BR-F05); jenis lain (struk, undangan, pengingat) menyusul.

## Objek `Notification`

```json
{
    "id": "9b1d2c4e-...",
    "kind": "budget_threshold",
    "title": "Budget Makanan & Minuman sudah 80%",
    "message": "Terpakai Rp 650.000 dari Rp 800.000 (81,3%) untuk Oktober 2026.",
    "family_id": 1,
    "subject": { "budget_id": 12, "period": "2026-10" },
    "read": false,
    "created_at": "2026-10-31T12:00:00+07:00",
    "created_at_human": "5 menit yang lalu"
}
```

`kind` menentukan layar yang dibuka klien: `budget_threshold` → halaman budget `subject.period` di keluarga `family_id`.

### GET `/notifications`

**Response `200`** — `{ "data": Notification[], "meta": { "unread_count": 1 } }`, terbaru dulu.

### POST `/notifications/read`

Body `{ "id": "<uuid>" }` untuk satu notifikasi, atau `{}` untuk semua. **Response `200`** — `{ "meta": { "unread_count": 0 } }`.

| Status | Field | Pesan                                  |
| ------ | ----- | -------------------------------------- |
| 422    | `id`  | format tidak valid                     |
| 404    | —     | notifikasi milik user lain / tidak ada |

## Penyesuaian dari implementasi lama (M2 — Notifikasi, 4 Nov 2026)

- Endpoint baru. Notifikasi budget kini menyimpan `period` (sebelumnya hanya URL halaman web).
- Waktu dalam ISO 8601 (+ teks relatif), bukan hanya "5 menit yang lalu".
