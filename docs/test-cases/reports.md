# Test Case — Ringkasan, Dashboard, Laporan & Export

**Form**: pilih periode (dashboard) + filter dompet / anggota · pilih laporan + rentang tanggal / tahun / jenis · tombol unduh Excel / PDF.
**Logika**: total tanpa transfer (BR-B11) & tanpa transaksi terhapus (BR-B08) · saldo = BR-C01/C02 · perubahan % vs periode lalu, "Baru" bila sebelumnya 0 (BR-A05) · periode bulanan & rentang inklusif (BR-D01…D04) · sub-kategori ikut induk (BR-E04) · angka web = API = export (INV-02…06).
**Kontrak**: [api-contracts/reports.md](../api-contracts/reports.md) · Test: `tests/Feature/Api/V1/ReportTest.php` (+ `DashboardServiceTest`, `ReportServiceTest` untuk perhitungan)

Data awal = keluarga golden, Oktober 2026: pemasukan 8.000.000, pengeluaran 2.287.500 (Makanan 650.000 termasuk Restoran 400.000, Tagihan 1.500.000, Belanja 87.500, Transportasi 50.000), selisih 5.712.500, total saldo 16.412.500; per pencatat Ayah 1.550.000, Ibu 737.500; satu transfer 1.000.000 dan satu transaksi terhapus 99.000. Hari ini 31 Okt 2026.

## Positif

| ID     | Skenario (yang dilakukan user)                                     | Hasil yang diharapkan                                                                                                                                                                          |
| ------ | ------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| REP-01 | Ibu membuka ringkasan Oktober                                      | saldo 16.412.500, masuk 8.000.000, keluar 2.287.500, selisih 5.712.500; `change` null (September kosong → "Baru"); transfer & terhapus tidak dihitung                                          |
| REP-02 | Ringkasan difilter dompet BCA, lalu anggota Ibu                    | BCA: keluar 1.900.000 (Tagihan + Restoran), saldo 15.100.000 · Ibu: keluar 737.500                                                                                                             |
| REP-03 | November: Ayah mencatat pengeluaran 100rb, buka ringkasan November | keluar 100.000, `change` −95,6% vs Oktober (BR-A05)                                                                                                                                            |
| REP-04 | Widget kategori                                                    | Tagihan 1.500.000 teratas; Makanan 650.000 (Restoran ikut induk); jumlah semua = 2.287.500 (INV-04)                                                                                            |
| REP-05 | Widget anggota                                                     | Ayah 1.550.000, Ibu 737.500; jumlah = total pengeluaran (INV-05)                                                                                                                               |
| REP-06 | Widget arus kas, tren, dompet, budget, recent, insight             | arus kas 6 periode berakhir Oktober (masuk 8jt); tren 31 hari, kumulatif hari terakhir 2.287.500; saldo dompet sama dengan `/wallets`; recent 7 transaksi terbaru dulu; insight berisi kalimat |
| REP-07 | Laporan arus kas Oktober                                           | 1 baris: masuk 8.000.000, keluar 2.287.500, saldo awal 10.700.000, saldo akhir 16.412.500 (INV-02)                                                                                             |
| REP-08 | Laporan kategori pengeluaran, lalu `type=income`                   | pengeluaran: Makanan 650.000 dengan anak Restoran 400.000; pemasukan: Gaji 8.000.000                                                                                                           |
| REP-09 | Laporan anggota, dompet, merchant, tag, budget, tahunan            | anggota & dompet cocok dengan data golden (dompet: transfer masuk Tunai 1jt); tahunan 2026: puncak `Okt`, total 2.287.500                                                                      |
| REP-10 | Tanpa rentang                                                      | memakai periode berjalan 1–31 Okt 2026                                                                                                                                                         |
| REP-11 | Unduh laporan kategori Excel                                       | file `laporan-kategori-2026-10-01_2026-10-31.xlsx`; sheet Ringkasan/Kategori/Transaksi/Budget; angka sama dengan laporan; 7 transaksi                                                          |
| REP-12 | Unduh laporan dompet PDF                                           | `application/pdf`, nama `laporan-dompet-2026-10-01_2026-10-31.pdf`                                                                                                                             |
| REP-13 | Viewer membuka laporan & mengunduh                                 | 200 (baca saja)                                                                                                                                                                                |

## Negatif

| ID     | Skenario                                                                    | Hasil yang diharapkan                                |
| ------ | --------------------------------------------------------------------------- | ---------------------------------------------------- |
| REP-21 | Ringkasan / widget periode Desember 2026 (belum dimulai), periode `2026-13` | 422 `period`                                         |
| REP-22 | Laporan `to` sebelum `from`; rentang > 1 tahun; hanya `from`                | 422 `to` / `from` (BR-D04)                           |
| REP-23 | Laporan / widget / export tidak dikenal                                     | 404                                                  |
| REP-24 | Export `format=docx`; `type=transfer`                                       | 422 `format` / `type`                                |
| REP-25 | Filter dompet milik keluarga lain                                           | 200 dengan angka 0 — tidak membocorkan data (BR-I06) |
| REP-26 | Tetangga (keluarga lain) membuka ringkasan & laporan                        | hanya angka keluarganya sendiri (0)                  |
| REP-27 | Tanpa login                                                                 | 401                                                  |
