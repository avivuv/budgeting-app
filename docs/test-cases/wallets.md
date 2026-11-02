# Test Case — Dompet

**Form**: nama (wajib, maks 50) · jenis (tunai / bank / e-wallet / kartu kredit) · saldo awal (opsional, boleh minus, default 0) · warna, ikon (opsional).
**Logika**: saldo = saldo awal + pemasukan − pengeluaran ± transfer (BR-C01) · total saldo hanya dompet aktif (BR-C02) · dompet yang sudah dipakai transaksi tidak bisa dihapus, hanya diarsipkan (BR-C05).
**Hak akses**: Owner/Admin boleh mengelola, Member & Viewer hanya melihat. Dompet keluarga lain → 404.
**Endpoint**: `GET/POST /api/v1/wallets`, `PUT/DELETE /api/v1/wallets/{id}`, `PATCH /api/v1/wallets/{id}/archive` · Test: `tests/Feature/Api/V1/WalletTest.php`

## Positif

| ID     | Skenario (yang dilakukan user)                                           | Hasil yang diharapkan                                                                                                         |
| ------ | ------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------- |
| WAL-01 | Owner membuat dompet "Tabungan Agus", jenis bank, saldo awal `1.500.000` | Tersimpan persis; muncul di daftar dengan saldo Rp 1.500.000; total saldo naik 1.500.000                                      |
| WAL-02 | Membuat dompet tanpa mengisi saldo awal, warna, dan ikon                 | Tersimpan dengan saldo 0, tidak error                                                                                         |
| WAL-03 | Membuat kartu kredit dengan saldo awal `-2jt` (tagihan berjalan)         | Saldo −2.000.000; total saldo turun 2.000.000 (BR-C02, BR-C06)                                                                |
| WAL-04 | Melihat daftar dompet setelah ada transaksi                              | Saldo tiap dompet = saldo awal + pemasukan − pengeluaran ± transfer (BR-C01)                                                  |
| WAL-05 | Mengganti nama "Tabungan Agus" → "Tabungan Budi"                         | Nama baru tersimpan & tampil; saldo tidak berubah                                                                             |
| WAL-06 | Owner mengarsipkan dompet, lalu mengaktifkannya lagi                     | Saat diarsipkan: tidak ikut total saldo dan ditolak untuk transaksi baru (BR-C02, BR-B05); setelah aktif lagi: kembali normal |
| WAL-07 | Menghapus dompet yang belum pernah dipakai                               | Dompet hilang dari daftar dan database                                                                                        |
| WAL-08 | Admin (bukan Owner) membuat & mengubah dompet                            | Berhasil                                                                                                                      |

## Negatif

| ID     | Skenario                                             | Hasil yang diharapkan                                                          |
| ------ | ---------------------------------------------------- | ------------------------------------------------------------------------------ |
| WAL-11 | Nama dikosongkan / lebih dari 50 huruf               | 422, error di `name`; tidak ada dompet baru                                    |
| WAL-12 | Jenis tidak dipilih / tidak dikenal                  | 422, error di `type`; tidak ada dompet baru                                    |
| WAL-13 | Saldo awal ditulis asal (`abc`, `50,5`)              | 422, error di `initial_balance`; tidak ada dompet baru                         |
| WAL-14 | Menghapus dompet yang sudah punya transaksi          | Ditolak dengan pesan "arsipkan saja"; dompet & transaksinya tetap ada (BR-C05) |
| WAL-15 | Member mencoba membuat / mengubah / menghapus dompet | 403; data tidak berubah                                                        |
| WAL-16 | Viewer mencoba membuat dompet                        | 403; tidak ada dompet baru                                                     |
| WAL-17 | Token hanya `read` dipakai untuk membuat dompet      | 403; tidak ada dompet baru                                                     |
| WAL-18 | Mengubah / menghapus dompet milik keluarga lain      | 404; dompet keluarga lain tidak berubah                                        |
| WAL-19 | Daftar dompet tidak menampilkan dompet keluarga lain | Hanya dompet keluarga sendiri yang muncul                                      |
| WAL-20 | Tanpa login / token                                  | 401                                                                            |
