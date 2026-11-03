# Test Case — Profil, Keamanan & Token

**Form**: Profil (nama, email, tampilan, hapus akun) · Keamanan (ganti password, pasang/matikan 2FA dengan QR + kode, kode cadangan, daftar & hapus passkey) · Token API (buat dengan nama + izin tulis, cabut).
**Logika**: email baru harus diverifikasi ulang · aksi sensitif meminta password · 2FA aktif setelah kode dikonfirmasi, lalu dipakai saat login · token baru hanya terlihat sekali; token dicabut langsung tidak berlaku · Owner dengan anggota lain tidak bisa menghapus akun (BR-I01); transaksi user yang dihapus tetap (BR-I04).
**Kontrak**: [api-contracts/settings.md](../api-contracts/settings.md) · Test: `tests/Feature/Api/V1/SettingsTest.php`

Data awal = keluarga golden: Ayah owner, Ibu member; password semua `password`.

## Positif

| ID     | Skenario (yang dilakukan user)                                       | Hasil yang diharapkan                                                                                    |
| ------ | -------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| SET-01 | Ibu mengganti nama "Ibu Ani", email `ani@contoh.com`, tampilan gelap | 200; `/me` membaca nilai baru, `email_verified` false; link verifikasi terkirim ke email baru            |
| SET-02 | Ibu mengganti password                                               | 200; login dengan password baru berhasil, password lama gagal                                            |
| SET-03 | Ibu memasang 2FA: minta QR, lalu konfirmasi kode dari aplikasi       | QR SVG + kunci + 8 kode cadangan; setelah konfirmasi `two_factor_enabled` true; login meminta kode       |
| SET-04 | Ibu membuat ulang kode cadangan, lalu mematikan 2FA                  | 8 kode baru (berbeda); setelah dimatikan `two_factor_enabled` false                                      |
| SET-05 | Ibu melihat dan menghapus passkey                                    | daftar berisi passkey "Laptop"; setelah dihapus kosong                                                   |
| SET-06 | Ibu membuat token "Spreadsheet" baca saja, memakainya, lalu mencabut | 201 + token teks; token bisa membaca tapi tidak menulis (403); `is_current` benar; setelah dicabut → 401 |
| SET-07 | Ibu (member) menghapus akunnya                                       | 204; tidak bisa login; transaksi Ibu tetap ada dengan pencatat "Mantan anggota" (BR-I04)                 |

## Negatif

| ID     | Skenario                                                                     | Hasil yang diharapkan               |
| ------ | ---------------------------------------------------------------------------- | ----------------------------------- |
| SET-21 | Ganti email ke email Ayah / format salah; nama kosong                        | 422; profil tidak berubah           |
| SET-22 | Ganti password dengan password lama salah / konfirmasi beda / terlalu pendek | 422; password tetap                 |
| SET-23 | Aksi sensitif (2FA, hapus passkey, hapus akun) dengan password salah         | 422 `password`; tidak ada perubahan |
| SET-24 | Konfirmasi 2FA dengan kode salah; pasang 2FA saat sudah aktif                | 422; 2FA belum / tetap aktif        |
| SET-25 | Ayah (owner, keluarga masih punya Ibu) menghapus akun                        | 422 BR-I01; akun tetap ada          |
| SET-26 | Menghapus passkey / token milik orang lain                                   | 404                                 |
| SET-27 | Token `read` mengubah profil / membuat token; belum login                    | 403 / 401                           |
