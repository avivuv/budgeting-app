# Test Case — Kategori

**Form**: jenis (pengeluaran / pemasukan) · nama (wajib, maks 50) · induk (opsional) · ikon, warna (opsional) · kata kunci (opsional) · arsip · gabung ke kategori lain.
**Logika**: maks 2 level dan sub-kategori sejenis dengan induk (BR-E01) · nama unik per jenis + induk (BR-E02) · kategori terpakai tidak bisa dihapus, hanya diarsipkan atau digabung (BR-E03) · total induk di laporan = induk + sub-kategori (BR-E04) · kata kunci dipakai menebak kategori (BR-E05).
**Hak akses**: Owner/Admin mengelola; Member & Viewer melihat. Kategori keluarga lain → 404.
**Kontrak**: [api-contracts/categories.md](../api-contracts/categories.md) · Test: `tests/Feature/Api/V1/CategoryTest.php`

Keluarga baru sudah punya kategori default (mis. pengeluaran "Transportasi", "Makanan & Minuman"; pemasukan "Gaji").

## Positif

| ID     | Skenario (yang dilakukan user)                                                 | Hasil yang diharapkan                                                                                                                                                                          |
| ------ | ------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| CAT-01 | Ayah membuat kategori pengeluaran "Hobi" dengan kata kunci `"mancing, futsal"` | Tersimpan dengan `keywords` `["mancing","futsal"]`; muncul di pohon pengeluaran                                                                                                                |
| CAT-02 | Ayah membuat sub-kategori "Ojek Online" di bawah "Transportasi"                | Muncul sebagai `children` Transportasi                                                                                                                                                         |
| CAT-03 | Melihat daftar kategori pemasukan saja                                         | Hanya kategori `income`, berupa pohon                                                                                                                                                          |
| CAT-04 | Ayah mengganti nama "Hobi" → "Hobi & Olahraga" dan menambah kata kunci         | Nama & kata kunci baru tersimpan dan tampil                                                                                                                                                    |
| CAT-05 | Ayah mengarsipkan "Hobi", lalu mengaktifkannya lagi                            | Saat arsip: hilang dari daftar default, muncul dengan `include_archived`, ditolak untuk transaksi baru; setelah aktif: normal lagi                                                             |
| CAT-06 | Ayah menggabungkan "Jajan" (3 transaksi) ke "Makanan & Minuman"                | `moved_transactions` 3; transaksi pindah kategori; "Jajan" hilang; total pengeluaran tidak berubah; tiap transaksi tercatat di activity log ⚠️; budget "Jajan" pindah/dijumlahkan ke tujuan ⚠️ |
| CAT-07 | Ayah menghapus kategori yang belum pernah dipakai                              | 204; hilang dari daftar dan database                                                                                                                                                           |
| CAT-08 | Admin membuat dan mengubah kategori                                            | Berhasil                                                                                                                                                                                       |
| CAT-09 | Kata kunci kategori dipakai menebak kategori di "Ketik cepat" (`bensin 50rb`)  | Parse transaksi menyarankan kategori yang punya kata kunci `bensin` (BR-E05)                                                                                                                   |

## Negatif

| ID     | Skenario                                                                                        | Hasil yang diharapkan                                                      |
| ------ | ----------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| CAT-11 | Nama kosong / > 50 huruf, jenis kosong / tidak dikenal                                          | 422 di field terkait; tidak ada kategori baru                              |
| CAT-12 | Membuat "Transportasi" lagi di level yang sama                                                  | 422 `name` "Nama kategori sudah dipakai." (BR-E02)                         |
| CAT-13 | Nama sama tapi jenis berbeda, atau di bawah induk berbeda                                       | **Boleh** (unik hanya per jenis + induk)                                   |
| CAT-14 | Membuat sub-kategori di bawah sub-kategori                                                      | 422 `parent_id` (BR-E01)                                                   |
| CAT-15 | Membuat sub-kategori pemasukan di bawah induk pengeluaran                                       | 422 `parent_id` (BR-E01)                                                   |
| CAT-16 | Mengubah jenis kategori yang sudah dipakai transaksi                                            | 422 `type` dengan pesan yang tepat ⚠️ (BR-E06 usulan); jenis tidak berubah |
| CAT-17 | Menghapus kategori yang sudah dipakai                                                           | 422 (BR-E03); kategori & transaksinya tetap                                |
| CAT-18 | Menghapus / menggabung kategori yang masih punya sub-kategori                                   | 422 (BR-E01); tidak ada yang berubah                                       |
| CAT-19 | Menggabung ke dirinya sendiri / ke kategori beda jenis / ke kategori arsip ⚠️                   | 422 `target_id` (BR-E03); tidak ada transaksi yang pindah                  |
| CAT-20 | Member / Viewer / token `read` mencoba membuat, mengubah, mengarsip, menggabung, atau menghapus | 403; data tidak berubah                                                    |
| CAT-21 | Memakai induk, mengubah, atau menggabung ke kategori keluarga lain                              | 404; kategori keluarga lain tidak berubah                                  |
| CAT-22 | Daftar kategori tidak menampilkan kategori keluarga lain                                        | Hanya kategori keluarga sendiri                                            |

⚠️ = bergantung keputusan di bagian bawah [kontrak](../api-contracts/categories.md#️-perlu-keputusan-sebelum-implementasi).
