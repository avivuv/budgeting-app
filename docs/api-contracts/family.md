# API Contract — Keluarga, Anggota & Undangan

Skenario test: [test-cases/family.md](../test-cases/family.md) · Rule: BR-I01…I07, BR-B09 · Konvensi umum: [README](README.md) · Peran: [03-multi-tenancy](../03-multi-tenancy.md)

| Method | Path                                | Fungsi                              | Peran                       | Ability | Status |
| ------ | ----------------------------------- | ----------------------------------- | --------------------------- | ------- | ------ |
| PUT    | `/families/{id}/switch`             | jadikan keluarga aktif              | anggota keluarga itu        | `read`  | ✅ ada |
| POST   | `/families/{id}/leave`              | keluar dari keluarga                | anggota selain Owner        | `write` | ✅ ada |
| GET    | `/members`                          | daftar anggota keluarga aktif       | semua anggota               | `read`  | ✅ ada |
| PATCH  | `/members/{userId}`                 | ubah peran                          | Owner, Admin (BR-I03)       | `write` | ✅ ada |
| DELETE | `/members/{userId}`                 | keluarkan anggota                   | Owner, Admin (BR-I03)       | `write` | ✅ ada |
| GET    | `/invitations`                      | undangan yang belum diterima        | Owner, Admin                | `read`  | ✅ ada |
| POST   | `/invitations`                      | undang lewat email                  | Owner, Admin                | `write` | ✅ ada |
| DELETE | `/invitations/{id}`                 | batalkan undangan                   | Owner, Admin                | `write` | ✅ ada |
| GET    | `/invitations/token/{token}`        | lihat undangan (oleh yang diundang) | user login mana pun         | `read`  | ✅ ada |
| POST   | `/invitations/token/{token}/accept` | terima undangan                     | user dengan email terundang | `write` | ✅ ada |

`switch`, `leave`, dan `invitations/token/*` **tidak** memakai keluarga aktif (boleh dipanggil sebelum punya keluarga; undangan dicari lewat token — BR-I07). Pengaturan keluarga (nama, zona waktu, awal periode) belum ada di web; endpoint-nya menyusul di Fase 5.

## Objek

`Member`: `{ "id": 7, "name": "Ibu", "email": "ibu@contoh.com", "role": "member", "role_label": "Anggota", "joined_at": "2026-10-01T08:00:00+07:00", "is_self": false }`

`Invitation`: `{ "id": 3, "email": "adik@contoh.com", "role": "viewer", "role_label": "Hanya lihat", "invited_by": "Ayah", "expires_at": "2026-11-07T12:00:00+07:00", "is_expired": false }`

Peran: `owner` · `admin` · `member` · `viewer` (BR-B09).

---

### PUT `/families/{id}/switch`

**Response `200`** — `{ "data": Me }` (sama dengan `GET /me`, `current_family_id` = keluarga baru). Bukan anggota → `404` (BR-I06).

### POST `/families/{id}/leave`

**Response `200`** — `{ "message": "Kamu telah keluar dari keluarga.", "data": Me }`. Transaksi lama tetap tercatat atas namanya (BR-I04). Bila itu keluarga aktif, keluarga aktif pindah ke keluarga lain miliknya (atau `null`).

| Status | Field     | Pesan                                                                           | Rule   |
| ------ | --------- | ------------------------------------------------------------------------------- | ------ |
| 422    | `general` | Pemilik tidak bisa keluar dari keluarga. Pindahkan kepemilikan terlebih dahulu. | BR-I01 |
| 404    | —         | bukan anggota                                                                   | BR-I06 |

### GET `/members`

**Response `200`** — `{ "data": Member[], "meta": { "can_manage": true, "roles": [{ "value": "admin", "label": "Admin" }, ...] } }`. `roles` = peran yang bisa diberikan (tanpa `owner`).

### PATCH `/members/{userId}` — ubah peran

Body `{ "role": "viewer" }`. **Response `200`** — `{ "data": Member }`.

### DELETE `/members/{userId}` — keluarkan

**Response `204`**. Akses anggota itu ke keluarga langsung hilang; transaksinya tetap (BR-I04).

| Status | Field     | Pesan                                                         | Rule   |
| ------ | --------- | ------------------------------------------------------------- | ------ |
| 422    | `general` | Pemilik keluarga tidak bisa diubah perannya atau dikeluarkan. | BR-I01 |
| 422    | `general` | Hanya pemilik yang bisa mengatur admin.                       | BR-I03 |
| 422    | `role`    | Peran ini tidak bisa diberikan lewat undangan. / tidak valid  | BR-I03 |
| 403    | —         | Member / Viewer                                               | BR-B09 |
| 404    | —         | bukan anggota keluarga ini                                    | BR-I06 |

### GET `/invitations`

**Response `200`** — `{ "data": Invitation[] }` (belum diterima, termasuk yang sudah kedaluwarsa dengan `is_expired: true`). Member/Viewer → `403`.

### POST `/invitations`

| Field   | Tipe   | Wajib | Aturan                                      |
| ------- | ------ | ----- | ------------------------------------------- |
| `email` | string | ya    | email, maks 255; disimpan huruf kecil       |
| `role`  | enum   | ya    | `admin` (hanya Owner) · `member` · `viewer` |

Email undangan berisi tautan berlaku 7 hari (BR-I02). Undangan tertunda ke email yang sama diganti (token baru).

**Response `201`** — `{ "message": "Undangan dikirim ke adik@contoh.com.", "data": Invitation }`.

| Status | Field     | Pesan                                          | Rule   |
| ------ | --------- | ---------------------------------------------- | ------ |
| 422    | `email`   | Email wajib diisi. / harus alamat email valid. |        |
| 422    | `email`   | Orang ini sudah menjadi anggota keluarga.      | BR-I02 |
| 422    | `role`    | Peran ini tidak bisa diberikan lewat undangan. | BR-I03 |
| 422    | `general` | Hanya pemilik yang bisa mengatur admin.        | BR-I03 |
| 403    | —         | Member / Viewer                                |        |

### DELETE `/invitations/{id}`

**Response `204`**. Undangan keluarga lain → `404`.

### GET `/invitations/token/{token}`

**Response `200`** — `{ "data": { "family_name": "Keluarga Golden", "email": "adik@contoh.com", "role": "viewer", "role_label": "Hanya lihat", "expires_at": "...", "can_accept": false, "problem": "Undangan ini untuk alamat email lain. Masuk dengan email yang diundang." } }`. Token tidak dikenal → `404`.

### POST `/invitations/token/{token}/accept`

**Response `200`** — `{ "message": "Selamat datang di Keluarga Golden!", "data": Me }` — keluarga itu menjadi keluarga aktif.

| Status | Field     | Pesan                                                                   | Rule   |
| ------ | --------- | ----------------------------------------------------------------------- | ------ |
| 422    | `general` | Undangan sudah kedaluwarsa. Minta undangan baru ke pengundang.          | BR-I02 |
| 422    | `general` | Undangan ini sudah dipakai.                                             | BR-I02 |
| 422    | `general` | Undangan ini untuk alamat email lain. Masuk dengan email yang diundang. | BR-I02 |
| 404    | —         | token tidak dikenal                                                     |        |

## Penyesuaian dari implementasi lama (M2 — Keluarga, 4 Nov 2026)

- Semua endpoint baru (sebelumnya hanya di web).
- `leave` memakai `/families/{id}/leave` (web: `family/leave` untuk keluarga aktif).
