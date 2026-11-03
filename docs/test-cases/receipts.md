# Test Case — Scan Struk

**Form**: unggah foto/PDF → tunggu dibaca → form tinjau (toko, tanggal, total, kategori, dompet, daftar item) → "Simpan" (total / pecah per item) atau "Batal".
**Logika**: hasil AI hanya draft, transaksi dibuat setelah Simpan (BR-H08) · nominal = total dibayar (BR-H01) · pecah per item proporsional, Σ = total (BR-H02, INV-09) · file sama ditolak (BR-H04) · kuota terpakai saat upload (BR-H05) · konfirmasi sekali (BR-H07) · koreksi hanya saat menunggu konfirmasi (BR-H09) · format & ukuran (BR-H11) · Viewer hanya melihat (BR-B09).
**Kontrak**: [api-contracts/receipts.md](../api-contracts/receipts.md) · Test: `tests/Feature/Api/V1/ReceiptTest.php` (+ test parser, job, alokasi di `tests/Feature/Receipt`, `tests/Unit`)

Data awal: keluarga baru dengan dompet & kategori default, parser AI palsu (`FakeReceiptParser`) yang membaca struk Alfamart: Beras 75.000 (Belanja Harian), Teh Botol 3 × 5.000 (Makanan & Minuman), diskon 3.000, total 87.000. Hari ini 31 Okt 2026.

## Positif

| ID     | Skenario (yang dilakukan user)                                                                  | Hasil yang diharapkan                                                                                                                             |
| ------ | ----------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| RCP-01 | Ayah mengunggah foto struk, lalu membuka draft                                                  | 202, kuota terpakai 1; draft `parsed`: Alfamart, total 87.000, diskon 3.000, 2 item, kategori Belanja Harian; **belum ada transaksi**             |
| RCP-02 | Ayah mengoreksi: toko "Alfamart Kemang", tanggal kemarin, total `99rb`, tambah item Roti `12rb` | 200; draft terbaca dengan nilai koreksi, 3 item, `items_total` 102.000; tetap belum ada transaksi                                                 |
| RCP-03 | Ayah menyimpan dengan "pecah per item"                                                          | 201; 2 transaksi: Belanja 72.795 + Makanan 26.205 = 99.000 (BR-H02); `source` receipt, tanggal & catatan hasil koreksi; saldo dompet turun 99.000 |
| RCP-04 | Menyimpan dengan "total"                                                                        | 1 transaksi 87.000 kategori Belanja Harian (BR-H01)                                                                                               |
| RCP-05 | Menekan Simpan dua kali                                                                         | Response sama, transaksi tidak bertambah (BR-H07)                                                                                                 |
| RCP-06 | Ayah membatalkan draft                                                                          | 200 `rejected`; tidak ada transaksi                                                                                                               |
| RCP-07 | Membuka daftar struk dan gambar struk                                                           | Daftar terbaru dulu + `meta.quota`; gambar 200 dengan `Content-Type` image/jpeg                                                                   |
| RCP-08 | Ibu (member) mengunggah & menyimpan; Viewer membuka draft & gambar                              | member boleh (BR-B09); viewer boleh membaca                                                                                                       |

## Negatif

| ID     | Skenario                                                             | Hasil yang diharapkan                            |
| ------ | -------------------------------------------------------------------- | ------------------------------------------------ |
| RCP-21 | Unggah tanpa file / file `.txt`                                      | 422 `file` (BR-H11); kuota tidak terpakai        |
| RCP-22 | Unggah file yang sama dua kali                                       | 422 `file` BR-H04                                |
| RCP-23 | Kuota bulan ini habis                                                | 429 `file` (BR-H05)                              |
| RCP-24 | Koreksi dengan total `banyak` dan tanpa tanggal                      | 422 `total`, `receipt_date`; draft tidak berubah |
| RCP-25 | Koreksi / simpan struk yang sudah tersimpan atau dibatalkan          | 422 `status` (BR-H07 / BR-H09)                   |
| RCP-26 | Simpan tanpa dompet terpilih                                         | 422 `wallet_id` (BR-H08); tidak ada transaksi    |
| RCP-27 | Koreksi memakai kategori / dompet keluarga lain                      | 404; draft tidak berubah                         |
| RCP-28 | Viewer / token `read` mengunggah, mengoreksi, menyimpan, membatalkan | 403                                              |
| RCP-29 | Keluarga lain membuka draft / gambar                                 | 404 (BR-I06)                                     |
