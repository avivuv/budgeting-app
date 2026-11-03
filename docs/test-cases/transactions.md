# Test Case — Transaksi

**Form**: jenis (pengeluaran / pemasukan / transfer) · nominal (bebas ketik: `12.500`, `75rb`, `1,5jt`) · tanggal · dompet · dompet tujuan (transfer) · kategori (pemasukan/pengeluaran) · catatan · tag. Plus "ketik cepat" (teks bebas), daftar dengan filter, aksi massal, riwayat perubahan.
**Logika**: nominal > 0 (BR-A02) · tanggal ≤ hari ini dan ≤ 5 tahun lalu (BR-B06) · kategori sejenis & wajib kecuali transfer (BR-B02/B03) · dompet & kategori milik keluarga dan aktif (BR-B04/B05) · saldo = saldo awal + pemasukan − pengeluaran ± transfer (BR-C01) · transfer tidak masuk total (BR-B11) · hapus = soft delete (BR-B08) · Member hanya mengubah miliknya (BR-B09) · request ganda tidak menggandakan (BR-B13) · semua perubahan tercatat (BR-B14).
**Kontrak**: [api-contracts/transactions.md](../api-contracts/transactions.md) · Test: `tests/Feature/Api/V1/TransactionTest.php` (+ `TransactionRoundTripTest.php` untuk invariant saldo/total)

Data awal = keluarga golden (docs/12 §3): Ayah owner, Ibu member; Tunai 1.200.000, BCA 15.100.000, GoPay 112.500; total pemasukan 8.000.000, pengeluaran 2.287.500 (Oktober 2026), hari ini 31 Okt 2026.

## Positif

| ID     | Skenario (yang dilakukan user)                                                             | Hasil yang diharapkan                                                                                                              |
| ------ | ------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------- |
| TRX-01 | Ayah mencatat pengeluaran `12.500` "kopi susu" dari GoPay, kategori Makanan, tag `#Kantor` | 201; dibaca ulang persis: 12.500, catatan, tag `kantor`, pencatat Ayah; saldo GoPay turun 12.500; total pengeluaran naik 12.500    |
| TRX-02 | Ayah mencatat pemasukan `1,5jt` ke BCA, kategori Gaji                                      | Saldo BCA naik 1.500.000; total pemasukan naik 1.500.000                                                                           |
| TRX-03 | Ayah transfer `1jt` BCA → Tunai                                                            | BCA turun, Tunai naik 1.000.000; total pemasukan & pengeluaran **tidak** berubah (BR-B11); `category` null                         |
| TRX-04 | Nominal diketik `75rb`, `1,5jt`, `2jt300`, `Rp 50.000`                                     | Tersimpan 75.000 / 1.500.000 / 2.300.000 / 50.000 (BR-A03)                                                                         |
| TRX-05 | Catatan & tag dikosongkan                                                                  | Tersimpan, `note` null, `tags` []                                                                                                  |
| TRX-06 | Ayah mengubah nominal & dompet sebuah pengeluaran (Tunai 50rb → BCA 75rb)                  | Saldo Tunai kembali +50rb, BCA −75rb; `source` & pencatat tetap; riwayat mencatat perubahan                                        |
| TRX-07 | Ayah menghapus transaksi                                                                   | 204; hilang dari daftar, total, dan saldo; tetap ada di DB sebagai terhapus (BR-B08)                                               |
| TRX-08 | Aplikasi mengirim ulang catat transaksi dengan `Idempotency-Key` sama                      | Response sama, hanya 1 transaksi (BR-B13)                                                                                          |
| TRX-09 | Ibu memfilter: pengeluaran, kategori Makanan (ikut sub Restoran), Oktober                  | Hanya transaksi yang cocok; `meta.totals.expense` = 650.000 (seluruh hasil filter)                                                 |
| TRX-10 | Daftar diurut nominal terbesar, 2 per halaman                                              | Urutan benar; `meta.total` & `last_page` benar; `meta.daily` berisi subtotal tanggal di halaman itu                                |
| TRX-11 | "Ketik cepat" `bensin 50rb` dan `parkir 5rb @gopay kemarin`                                | Pratinjau per baris (snake_case): nominal, kategori Transportasi, dompet GoPay, tanggal kemarin; **tidak ada** transaksi tersimpan |
| TRX-12 | Ayah memilih 3 transaksi lalu "hapus" massal                                               | `affected` 3; ketiganya hilang dari daftar & total                                                                                 |
| TRX-13 | Ayah memilih 2 pengeluaran lalu ganti kategori massal ke Belanja                           | Keduanya pindah kategori; tercatat di riwayat masing-masing                                                                        |
| TRX-14 | Membuka riwayat transaksi yang pernah diubah                                               | Terbaru dulu: diubah → dibuat; siapa, kapan (ISO, zona keluarga), field lama → baru                                                |
| TRX-15 | Melihat daftar tag                                                                         | Tag keluarga sendiri, urut abjad                                                                                                   |
| TRX-16 | Transaksi dari web SPA (sesi) vs aplikasi Android (token)                                  | `source` `web` vs `api` (BR-B07)                                                                                                   |
| TRX-17 | Pengeluaran membuat saldo GoPay minus                                                      | 201 dengan `meta.warnings` "Saldo GoPay menjadi minus" (BR-C03)                                                                    |

## Negatif

| ID     | Skenario                                                                                    | Hasil yang diharapkan                                                    |
| ------ | ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------ |
| TRX-21 | Nominal kosong / 0 / minus / `abc` / `50,5`                                                 | 422 `amount`; tidak ada transaksi baru                                   |
| TRX-22 | Tanggal besok / lebih dari 5 tahun lalu / format salah                                      | 422 `date` (BR-B06)                                                      |
| TRX-23 | Pengeluaran tanpa kategori / dengan kategori pemasukan                                      | 422 `category_id` (BR-B02)                                               |
| TRX-24 | Transfer tanpa dompet tujuan / ke dompet yang sama                                          | 422 `to_wallet_id` (BR-B03)                                              |
| TRX-25 | Memakai dompet yang diarsipkan untuk transaksi baru                                         | 422 `wallet_id` (BR-B05); transaksi lama di dompet itu tetap bisa diubah |
| TRX-26 | Memakai dompet / kategori keluarga lain                                                     | 404; tidak ada transaksi baru (BR-B04)                                   |
| TRX-27 | Viewer / token `read` mencatat transaksi                                                    | 403; tidak ada transaksi baru                                            |
| TRX-28 | Ibu (member) mengubah / menghapus transaksi Ayah                                            | 403; transaksi tidak berubah (BR-B09)                                    |
| TRX-29 | Membuka / mengubah transaksi keluarga lain atau yang sudah dihapus                          | 404                                                                      |
| TRX-30 | Aksi massal berisi 1 transaksi milik Ayah oleh Ibu                                          | 403; **tidak ada** yang terhapus                                         |
| TRX-31 | Ganti kategori massal yang menyertakan transfer                                             | 422; tidak ada yang berubah                                              |
| TRX-32 | Aksi massal: `ids` kosong / > 100 / aksi tidak dikenal / ganti kategori tanpa `category_id` | 422                                                                      |
| TRX-33 | Filter tidak valid (`to` sebelum `from`, `sort` tidak dikenal, `per_page` 500)              | 422                                                                      |
| TRX-34 | Ayah membaca keluarga keduanya lewat `X-Family-Id`, lalu mencoba keluarga orang lain        | Keluarga sendiri: 200; keluarga orang lain: 404 (BR-I06)                 |
