# API Contract — Auth & Sesi

Skenario test: [test-cases/auth.md](../test-cases/auth.md) · Konvensi umum: [README](README.md)

Dua cara masuk ([13-rest-api](../13-rest-api.md#1-autentikasi--tenant)):

- **Web SPA** — sesi cookie. Endpoint bertanda _SPA_ hanya menerima request dari domain SPA (stateful, `SANCTUM_STATEFUL_DOMAINS`): panggil `GET /sanctum/csrf-cookie` dulu, lalu kirim header `X-XSRF-TOKEN`. Klien lain mendapat `400`.
- **Android / integrasi** — token `Bearer` dari `POST /auth/token`.

| Method | Path                                    | Fungsi                                     | Siapa         | Status |
| ------ | --------------------------------------- | ------------------------------------------ | ------------- | ------ |
| POST   | `/auth/login`                           | login sesi                                 | tamu · _SPA_  | ✅ ada |
| POST   | `/auth/two-factor-challenge`            | lanjutkan login dengan kode 2FA            | tamu · _SPA_  | ✅ ada |
| GET    | `/auth/passkeys/options`                | opsi WebAuthn untuk login passkey          | tamu · _SPA_  | ✅ ada |
| POST   | `/auth/passkeys/login`                  | login dengan passkey                       | tamu · _SPA_  | ✅ ada |
| POST   | `/auth/logout`                          | akhiri sesi                                | login · _SPA_ | ✅ ada |
| POST   | `/auth/register`                        | daftar akun baru + langsung login          | tamu · _SPA_  | ✅ ada |
| POST   | `/auth/forgot-password`                 | kirim link reset password                  | tamu          | ✅ ada |
| POST   | `/auth/reset-password`                  | simpan password baru dari link email       | tamu          | ✅ ada |
| POST   | `/auth/email/verification-notification` | kirim ulang email verifikasi               | login         | ✅ ada |
| POST   | `/auth/token`                           | buat token (Android)                       | tamu          | ✅ ada |
| DELETE | `/auth/token`                           | cabut token yang sedang dipakai            | login (token) | ✅ ada |
| GET    | `/me`                                   | profil + keluarga, dipanggil setelah login | login         | ✅ ada |

## Objek `Me`

| Field                  | Tipe            | Keterangan                                                                                                                                                                     |
| ---------------------- | --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `id`                   | integer         |                                                                                                                                                                                |
| `name`                 | string          |                                                                                                                                                                                |
| `email`                | string          |                                                                                                                                                                                |
| `email_verified`       | boolean         |                                                                                                                                                                                |
| `two_factor_enabled`   | boolean         | 2FA aktif & terkonfirmasi                                                                                                                                                      |
| `appearance`           | enum            | `light` · `dark` · `system`                                                                                                                                                    |
| `current_family_id`    | integer \| null | `null` → belum punya keluarga, arahkan ke onboarding                                                                                                                           |
| `families`             | array           | `{ id, name, role, role_label, can: { write, manage } }`, `role`: `owner` · `admin` · `member` · `viewer`; `can` dihitung backend (BR-B09) — klien hanya menyembunyikan tombol |
| `unread_notifications` | integer         | jumlah notifikasi belum dibaca (badge lonceng)                                                                                                                                 |

```json
{
    "data": {
        "id": 2,
        "name": "Ayah",
        "email": "ayah@budgeting.test",
        "email_verified": true,
        "two_factor_enabled": false,
        "appearance": "system",
        "current_family_id": 1,
        "families": [
            {
                "id": 1,
                "name": "Keluarga Demo",
                "role": "owner",
                "role_label": "Pemilik",
                "can": { "write": true, "manage": true }
            }
        ],
        "unread_notifications": 0
    }
}
```

---

### POST `/auth/login` — _SPA_

| Field      | Tipe    | Wajib | Contoh                                           |
| ---------- | ------- | ----- | ------------------------------------------------ |
| `email`    | string  | ya    | `"ayah@budgeting.test"` (tidak peka huruf besar) |
| `password` | string  | ya    |                                                  |
| `remember` | boolean | tidak | `true` → sesi bertahan lama                      |

**Response**

| Status | Body                                                                         | Arti                                                                                 |
| ------ | ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| `200`  | `{ "data": Me }`                                                             | login berhasil, cookie sesi dipasang                                                 |
| `202`  | `{ "message": "Masukkan kode autentikasi dua faktor.", "two_factor": true }` | password benar tapi 2FA aktif → lanjut ke `/auth/two-factor-challenge` (belum login) |

**Error**

| Status | Field                | Pesan                                                                                     |
| ------ | -------------------- | ----------------------------------------------------------------------------------------- |
| 422    | `email`              | Email atau password salah.                                                                |
| 422    | `email` / `password` | Email wajib diisi. / Password wajib diisi.                                                |
| 429    | —                    | Terlalu banyak percobaan login. Coba lagi dalam :seconds detik. (5×/menit per email + IP) |

---

### POST `/auth/two-factor-challenge` — _SPA_

| Field           | Tipe   | Wajib      | Keterangan                                             |
| --------------- | ------ | ---------- | ------------------------------------------------------ |
| `code`          | string | salah satu | 6 digit dari aplikasi authenticator                    |
| `recovery_code` | string | salah satu | kode pemulihan (sekali pakai, diganti setelah dipakai) |

**Response `200`** — `{ "data": Me }`, sesi dipasang.

| Status | Field                    | Pesan                                                                                  |
| ------ | ------------------------ | -------------------------------------------------------------------------------------- |
| 422    | `code` / `recovery_code` | Kode autentikasi tidak valid.                                                          |
| 422    | `general`                | Sesi login sudah kedaluwarsa. Silakan login ulang. (tidak ada login yang menunggu 2FA) |
| 429    | —                        | 5×/menit                                                                               |

---

### GET `/auth/passkeys/options` · POST `/auth/passkeys/login` — _SPA_

- `options` → `200 { "options": PublicKeyCredentialRequestOptions }` (diteruskan ke `navigator.credentials.get`).
- `login` body `{ "credential": <hasil navigator.credentials.get>, "remember": false }` → `200 { "data": Me }`; passkey tidak valid → `422`.
- Login passkey untuk Android menyusul (Credential Manager + token) saat aplikasi Android dibuat.

---

### POST `/auth/logout` — _SPA_

**Response `204`** — sesi dihapus, token CSRF diganti.

---

### POST `/auth/register` — _SPA_

| Field                   | Tipe   | Wajib | Aturan                          |
| ----------------------- | ------ | ----- | ------------------------------- |
| `name`                  | string | ya    | maks 255                        |
| `email`                 | string | ya    | email valid, unik               |
| `password`              | string | ya    | aturan password default (min 8) |
| `password_confirmation` | string | ya    | sama dengan `password`          |

**Response `201`** — `{ "data": Me }` (`current_family_id: null`, `email_verified: false`); langsung login dan email verifikasi dikirim.

| Status | Field      | Pesan                                                                |
| ------ | ---------- | -------------------------------------------------------------------- |
| 422    | `email`    | Email sudah terdaftar. / Email harus berupa alamat email yang valid. |
| 422    | `password` | Konfirmasi password tidak cocok. / Password minimal 8 karakter.      |

---

### POST `/auth/forgot-password`

Body `{ "email": "..." }` → **`200`** `{ "message": "Jika email terdaftar, link reset password sudah dikirim." }` — **selalu sama**, terdaftar atau tidak (mencegah menebak email). Link mengarah ke `/reset-password/{token}?email=...` di SPA. `422` hanya bila format email salah. `429` bila terlalu sering.

---

### POST `/auth/reset-password`

| Field                   | Tipe   | Wajib |
| ----------------------- | ------ | ----- |
| `token`                 | string | ya    |
| `email`                 | string | ya    |
| `password`              | string | ya    |
| `password_confirmation` | string | ya    |

**Response `200`** — `{ "message": "Password berhasil diganti. Silakan login." }`. Semua token API user **tidak** dicabut otomatis.

| Status | Field      | Pesan                                                   |
| ------ | ---------- | ------------------------------------------------------- |
| 422    | `email`    | Link reset password tidak valid atau sudah kedaluwarsa. |
| 422    | `password` | aturan password                                         |

---

### POST `/auth/email/verification-notification`

**Response `202`** — `{ "message": "Link verifikasi baru sudah dikirim ke email kamu." }`; bila sudah terverifikasi → `200 { "message": "Email sudah terverifikasi." }`. Maks 6×/menit.

---

### POST `/auth/token` — Android

| Field           | Tipe   | Wajib             | Keterangan                                                |
| --------------- | ------ | ----------------- | --------------------------------------------------------- |
| `email`         | string | ya                |                                                           |
| `password`      | string | ya                |                                                           |
| `device_name`   | string | ya                | maks 100, mis. `"Pixel 7 — Ayah"`; tampil di daftar token |
| `code`          | string | bila 2FA aktif    | 6 digit                                                   |
| `recovery_code` | string | alternatif `code` |                                                           |

**Response**

| Status | Body                                                                                                                                                   |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `201`  | `{ "data": { "token": "12\|abc...", "token_type": "Bearer", "abilities": ["read", "write"], "expires_at": "2027-02-01T10:00:00+07:00", "user": Me } }` |
| `202`  | `{ "message": "Masukkan kode autentikasi dua faktor.", "two_factor": true }` — kirim ulang request yang sama dengan `code`                             |

`token` hanya ditampilkan sekali. Masa berlaku `SANCTUM_EXPIRATION` (default 90 hari).

| Status | Field                    | Pesan                         |
| ------ | ------------------------ | ----------------------------- |
| 422    | `email`                  | Email atau password salah.    |
| 422    | `code` / `recovery_code` | Kode autentikasi tidak valid. |
| 422    | `device_name`            | Nama perangkat wajib diisi.   |
| 429    | —                        | 5×/menit per email + IP       |

---

### DELETE `/auth/token` — Android

**Response `204`** — token yang dipakai di request ini dicabut; request berikutnya dengan token itu → `401`. Dipanggil dengan sesi SPA → `204` tanpa efek (pakai `/auth/logout`).

---

### GET `/me`

**Response `200`** — `{ "data": Me }`. Tidak membutuhkan keluarga (user baru → `current_family_id: null`, `families: []`), sehingga SPA/Android bisa mengarahkan ke onboarding.

## Perubahan umum di M1 (berlaku semua endpoint)

- Pesan validasi & error dalam Bahasa Indonesia (`lang/id`).
- `401` `{ "message": "Silakan login terlebih dahulu." }` · `403` `{ "message": "Kamu tidak punya akses untuk aksi ini." }` (kecuali pesan khusus, mis. keluarga ditangguhkan) · `404` `{ "message": "Data tidak ditemukan." }` · `409` `{ "message": "Buat atau gabung ke keluarga terlebih dahulu.", "code": "no_family" }` · `429` pesan Indonesia.
- Pelanggaran business rule menyertakan `rule`.
- Dokumentasi OpenAPI di `/docs/api` (lingkungan lokal).
