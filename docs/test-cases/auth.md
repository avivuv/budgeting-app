# Test Case — Auth & Sesi

**Form**: login (email, password, ingat saya) · kode 2FA / kode pemulihan · daftar (nama, email, password + konfirmasi) · lupa password (email) · reset password (password baru + konfirmasi) · token Android (email, password, nama perangkat, kode 2FA).
**Logika**: email tidak peka huruf besar · 2FA aktif → login baru selesai setelah kode benar · kode pemulihan sekali pakai · batas 5 percobaan/menit · lupa password tidak membocorkan email terdaftar · token berlaku 90 hari dan bisa dicabut · user tanpa keluarga tetap bisa membaca `/me`.
**Kontrak**: [api-contracts/auth.md](../api-contracts/auth.md) · Test: `tests/Feature/Api/V1/AuthTest.php`, `tests/Feature/Api/V1/ApiErrorTest.php`

## Positif

| ID      | Skenario (yang dilakukan user)                                                        | Hasil yang diharapkan                                                                                             |
| ------- | ------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| AUTH-01 | Ayah login di web dengan email `AYAH@budgeting.test` (huruf besar) dan password benar | 200 + profil Ayah; request berikutnya ke `/me` dikenali sebagai Ayah                                              |
| AUTH-02 | Ayah (2FA aktif) login, lalu memasukkan kode dari authenticator                       | Login pertama 202 `two_factor` dan belum masuk; setelah kode benar 200 + profil, sesi aktif                       |
| AUTH-03 | Ayah (2FA aktif) memakai kode pemulihan karena HP hilang                              | Masuk; kode pemulihan itu tidak bisa dipakai lagi                                                                 |
| AUTH-04 | Ayah logout                                                                           | 204; `/me` sesudahnya 401                                                                                         |
| AUTH-05 | Budi mendaftar akun baru                                                              | 201, langsung login, `current_family_id` null, email verifikasi terkirim, user tersimpan dengan password ter-hash |
| AUTH-06 | Ayah lupa password lalu mengganti lewat link email                                    | Link terkirim; reset dengan token dari email berhasil; login dengan password baru berhasil, password lama gagal   |
| AUTH-07 | Budi meminta kirim ulang email verifikasi                                             | 202, email verifikasi terkirim                                                                                    |
| AUTH-08 | Ayah login dari aplikasi Android (minta token)                                        | 201 + token Bearer, abilities `read`+`write`, kedaluwarsa 90 hari; token bisa dipakai membaca `/me`               |
| AUTH-09 | Ayah (2FA aktif) minta token: pertama tanpa kode, lalu dengan kode                    | Pertama 202 `two_factor`, tidak ada token dibuat; kedua 201 + token                                               |
| AUTH-10 | Ayah logout dari Android (cabut token)                                                | 204; token itu tidak bisa dipakai lagi (401), token lain tetap aktif                                              |
| AUTH-12 | Ayah memilih "Login dengan passkey" di web                                            | 200 + opsi WebAuthn (`challenge`) untuk browser; verifikasi passkey sendiri diuji package `laravel/passkeys`      |
| AUTH-11 | User baru tanpa keluarga membuka `/me`                                                | 200, `current_family_id` null, `families` kosong (bukan 409)                                                      |

## Negatif

| ID      | Skenario                                                                 | Hasil yang diharapkan                                            |
| ------- | ------------------------------------------------------------------------ | ---------------------------------------------------------------- |
| AUTH-21 | Login dengan password salah                                              | 422 di `email` "Email atau password salah."; tidak ada sesi      |
| AUTH-22 | Login dengan email yang tidak terdaftar                                  | 422 pesan yang **sama** dengan AUTH-21                           |
| AUTH-23 | Login salah 6× berturut-turut                                            | Percobaan ke-6 → 429                                             |
| AUTH-24 | Kode 2FA salah                                                           | 422 di `code`; belum login                                       |
| AUTH-25 | Kirim kode 2FA tanpa login sebelumnya                                    | 422 "Sesi login sudah kedaluwarsa"                               |
| AUTH-26 | Daftar dengan email yang sudah dipakai / konfirmasi password beda        | 422 di `email` / `password`; tidak ada user baru                 |
| AUTH-27 | Lupa password dengan email tidak terdaftar                               | 200 pesan sama seperti email terdaftar; tidak ada email terkirim |
| AUTH-28 | Reset password dengan token salah                                        | 422 di `email`; password tidak berubah                           |
| AUTH-29 | Minta token dengan password salah / tanpa nama perangkat                 | 422; tidak ada token dibuat                                      |
| AUTH-30 | Minta token dengan kode 2FA salah                                        | 422 di `code`; tidak ada token dibuat                            |
| AUTH-31 | Memakai token yang sudah kedaluwarsa                                     | 401 "Silakan login terlebih dahulu."                             |
| AUTH-32 | Klien non-browser memanggil `/auth/login` (tanpa sesi)                   | 400, diarahkan memakai `/auth/token`                             |
| AUTH-33 | User yang sudah login memanggil `/auth/login` atau `/auth/register` lagi | Tidak membuat akun/sesi ganda (200 profil saat ini)              |

## Format error umum (`ApiErrorTest`)

| ID     | Skenario                                                  | Hasil yang diharapkan                                                               |
| ------ | --------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| ERR-01 | Request tanpa login ke endpoint terlindungi               | 401 "Silakan login terlebih dahulu."                                                |
| ERR-02 | Viewer mencoba membuat data                               | 403 "Kamu tidak punya akses untuk aksi ini."                                        |
| ERR-03 | Membuka data keluarga lain                                | 404 "Data tidak ditemukan."                                                         |
| ERR-04 | User tanpa keluarga membuka daftar dompet                 | 409 dengan `code: no_family`                                                        |
| ERR-05 | Validasi gagal                                            | 422, pesan Bahasa Indonesia memakai nama field Indonesia (mis. "Nama wajib diisi.") |
| ERR-06 | Melanggar business rule (hapus dompet yang sudah dipakai) | 422 dengan `rule: "BR-C05"` dan `errors.general`                                    |
