# API Contract — Profil, Keamanan & Token

Skenario test: [test-cases/settings.md](../test-cases/settings.md) · Rule: BR-I01, BR-I04, BR-I08 · Konvensi umum: [README](README.md) · Login & 2FA saat masuk: [auth.md](auth.md)

| Method | Path                            | Fungsi                                 | Ability | Status |
| ------ | ------------------------------- | -------------------------------------- | ------- | ------ |
| PATCH  | `/me`                           | ubah nama, email, tampilan             | `write` | ✅ ada |
| DELETE | `/me`                           | hapus akun                             | `write` | ✅ ada |
| PUT    | `/me/password`                  | ganti password                         | `write` | ✅ ada |
| POST   | `/me/two-factor`                | mulai pasang 2FA (QR + kode cadangan)  | `write` | ✅ ada |
| POST   | `/me/two-factor/confirm`        | aktifkan 2FA dengan kode dari aplikasi | `write` | ✅ ada |
| DELETE | `/me/two-factor`                | matikan 2FA                            | `write` | ✅ ada |
| POST   | `/me/two-factor/recovery-codes` | buat ulang kode cadangan               | `write` | ✅ ada |
| GET    | `/me/passkeys`                  | daftar passkey                         | `read`  | ✅ ada |
| DELETE | `/me/passkeys/{id}`             | hapus passkey                          | `write` | ✅ ada |
| GET    | `/me/tokens`                    | daftar token API                       | `read`  | ✅ ada |
| POST   | `/me/tokens`                    | buat token API                         | `write` | ✅ ada |
| DELETE | `/me/tokens/{id}`               | cabut token                            | `write` | ✅ ada |

Semua untuk akun user yang login sendiri; tidak butuh keluarga aktif. Aksi sensitif (2FA, hapus passkey, hapus akun) meminta **`password` saat ini** di body — pengganti langkah "konfirmasi password" web agar bisa dipakai token Android.

**Passkey baru** didaftarkan dari browser lewat endpoint paket passkeys (`GET /user/passkeys/options` → `POST /user/passkeys`, sesi web + konfirmasi password) karena tantangan WebAuthn disimpan di sesi. Pendaftaran passkey dari Android menyusul bersama aplikasinya.

---

### PATCH `/me`

| Field        | Tipe   | Wajib | Aturan                      |
| ------------ | ------ | ----- | --------------------------- |
| `name`       | string | tidak | maks 255                    |
| `email`      | string | tidak | email, unik                 |
| `appearance` | enum   | tidak | `light` · `dark` · `system` |

Email berubah → `email_verified` jadi `false` dan link verifikasi dikirim ke email baru. **Response `200`** — `{ "data": Me }`.

| Status | Field   | Pesan                                     |
| ------ | ------- | ----------------------------------------- |
| 422    | `email` | Email sudah dipakai. / format tidak valid |
| 422    | `name`  | Nama wajib diisi (bila dikirim kosong).   |

### PUT `/me/password`

Body `{ "current_password", "password", "password_confirmation" }` (min 8). **Response `200`** — `{ "message": "Password diperbarui." }`. Password lama salah → `422 current_password` "Password salah."

### DELETE `/me`

Body `{ "password" }`. **Response `204`** — akun & semua token dihapus, sesi web diakhiri. Transaksinya tetap ada atas nama "Mantan anggota" (BR-I04, BR-I08).

| Status | Field      | Pesan                                                                           | Rule   |
| ------ | ---------- | ------------------------------------------------------------------------------- | ------ |
| 422    | `password` | Password salah.                                                                 |        |
| 422    | `general`  | Pemilik tidak bisa keluar dari keluarga. Pindahkan kepemilikan terlebih dahulu. | BR-I01 |

Owner dari keluarga yang masih punya anggota lain tidak bisa menghapus akun (keluarga tidak boleh tanpa Owner).

### POST `/me/two-factor`

Body `{ "password" }`. **Response `200`** — `{ "data": { "qr_code_svg": "<svg ...>", "setup_key": "JBSWY3DP...", "recovery_codes": ["abcd-efgh", ...] } }`. Belum aktif sampai dikonfirmasi; memanggil lagi sebelum konfirmasi membuat rahasia baru. Sudah aktif → `422` "Autentikasi dua faktor sudah aktif. Matikan dulu bila ingin mengatur ulang."

### POST `/me/two-factor/confirm`

Body `{ "code": "123456" }`. **Response `200`** — `{ "message": "Autentikasi dua faktor aktif.", "data": { "two_factor_enabled": true } }`. Kode salah → `422 code` "Kode autentikasi tidak valid." Login berikutnya meminta kode ([auth.md](auth.md)).

### DELETE `/me/two-factor`

Body `{ "password" }`. **Response `204`**.

### POST `/me/two-factor/recovery-codes`

Body `{ "password" }`. **Response `200`** — `{ "data": ["abcd-efgh", ...] }` (8 kode baru; yang lama tidak berlaku). 2FA belum aktif → `422`.

### GET `/me/passkeys` · DELETE `/me/passkeys/{id}`

`GET` → `{ "data": [{ "id", "name", "authenticator", "created_at", "last_used_at" }] }`. `DELETE` body `{ "password" }` → `204`; passkey milik orang lain → `404`.

### GET `/me/tokens`

**Response `200`** — `{ "data": [{ "id", "name", "can_write", "last_used_at", "expires_at", "is_current" }] }`. `is_current` = token yang sedang dipakai request ini.

### POST `/me/tokens`

Body `{ "name": "Spreadsheet", "can_write": false }`. **Response `201`** — `{ "message": "Token dibuat. Salin sekarang, token tidak akan ditampilkan lagi.", "data": { ..., "plain_text_token": "5|abc..." } }`. Hak `read` saja, atau `read`+`write` bila `can_write`.

### DELETE `/me/tokens/{id}`

**Response `204`** — token langsung tidak berlaku. Token milik orang lain → `404`.

## Penyesuaian dari implementasi lama (M2 — Profil & Keamanan, 4 Nov 2026)

- Semua endpoint baru (sebelumnya hanya halaman web pengaturan).
- Hapus akun kini menolak Owner yang keluarganya masih punya anggota (berlaku juga di web).
- Ganti email lewat API mengirim link verifikasi ke email baru.
