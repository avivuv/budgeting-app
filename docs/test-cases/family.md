# Test Case — Keluarga, Anggota & Undangan

**Form**: halaman Anggota (daftar, ubah peran, keluarkan, undang email + peran, batalkan undangan) · halaman terima undangan · pemilih keluarga aktif · tombol "keluar dari keluarga".
**Logika**: satu Owner yang tidak bisa diubah/dikeluarkan/keluar (BR-I01) · undangan 7 hari, sekali pakai, hanya email terundang (BR-I02) · Admin tidak mengatur Admin lain (BR-I03) · transaksi anggota yang keluar tetap (BR-I04) · keluarga lain → 404 (BR-I06) · undangan dicari lewat token tanpa keluarga aktif (BR-I07).
**Kontrak**: [api-contracts/family.md](../api-contracts/family.md) · Test: `tests/Feature/Api/V1/FamilyTest.php` (+ `tests/Feature/Family/*` untuk service)

Data awal = keluarga golden: Ayah owner, Ibu member. Hari ini 31 Okt 2026.

## Positif

| ID     | Skenario (yang dilakukan user)                        | Hasil yang diharapkan                                                                                          |
| ------ | ----------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| FAM-01 | Ibu membuka daftar anggota                            | Ayah (owner) & Ibu (member, `is_self`), `can_manage` false                                                     |
| FAM-02 | Ayah mengundang `Adik@Contoh.com` sebagai viewer      | 201; email terkirim; undangan tercatat huruf kecil, kedaluwarsa 7 Nov; tampil di `GET /invitations`            |
| FAM-03 | Adik (akun email itu) membuka tautan lalu menerima    | lihat: nama keluarga, peran Hanya lihat, `can_accept` true; terima: 200, keluarga aktif = golden, peran viewer |
| FAM-04 | Ayah mengundang ulang email yang sama                 | undangan lama diganti (token baru), tetap satu undangan                                                        |
| FAM-05 | Ayah membatalkan undangan                             | 204; hilang dari daftar; tautan lama → 404                                                                     |
| FAM-06 | Ayah mengubah Ibu menjadi admin, lalu viewer          | peran tersimpan & terbaca                                                                                      |
| FAM-07 | Ayah mengeluarkan Ibu                                 | 204; Ibu tidak lagi di daftar; transaksi Ibu tetap atas namanya (BR-I04); Ibu membuka data keluarga → 409/404  |
| FAM-08 | Ibu keluar sendiri dari keluarga                      | 200; `/me` tidak lagi memuat keluarga golden                                                                   |
| FAM-09 | Ayah yang punya dua keluarga berpindah keluarga aktif | 200; `current_family_id` keluarga kedua; data yang terbaca milik keluarga kedua                                |

## Negatif

| ID     | Skenario                                                                                                       | Hasil yang diharapkan             |
| ------ | -------------------------------------------------------------------------------------------------------------- | --------------------------------- |
| FAM-21 | Ibu (member) / viewer mengundang, mengubah peran, mengeluarkan, melihat undangan                               | 403; tidak ada perubahan          |
| FAM-22 | Mengundang email anggota yang sudah ada / email tidak valid / peran `owner`                                    | 422 `email` / `role`              |
| FAM-23 | Admin mengundang admin, menjadikan orang admin, atau mengeluarkan admin lain                                   | 422 BR-I03                        |
| FAM-24 | Mengubah peran atau mengeluarkan Owner                                                                         | 422 BR-I01                        |
| FAM-25 | Owner keluar dari keluarganya                                                                                  | 422 BR-I01; tetap owner           |
| FAM-26 | Menerima undangan dengan email lain / yang kedaluwarsa / yang sudah dipakai                                    | 422 BR-I02; tidak menjadi anggota |
| FAM-27 | Berpindah ke / keluar dari keluarga yang bukan miliknya; mengubah anggota & membatalkan undangan keluarga lain | 404 (BR-I06)                      |
| FAM-28 | Token undangan tidak dikenal                                                                                   | 404                               |
