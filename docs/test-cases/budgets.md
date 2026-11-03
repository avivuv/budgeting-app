# Test Case — Budget

**Form**: periode (pilih bulan) · daftar baris kategori pengeluaran + nominal (bebas ketik `800rb`) · tombol "salin dari bulan lalu" · hapus per budget. Halaman menampilkan realisasi, sisa, persen, status, jatah harian, dan total.
**Logika**: satu budget per kategori per periode, hanya kategori pengeluaran (BR-F01) · realisasi termasuk sub-kategori (BR-F02) · sisa boleh minus, persen 1 desimal (BR-F03) · status aman/waspada/lewat (BR-F04) · notifikasi 80%/100% sekali per periode (BR-F05) · jatah harian (BR-F06) · salin hanya kategori yang belum punya budget (BR-F07) · periode maks satu ke depan (BR-F08) · kategori diarsipkan tidak bisa diberi budget baru (BR-F09) · hanya Owner/Admin yang mengatur (BR-B09).
**Kontrak**: [api-contracts/budgets.md](../api-contracts/budgets.md) · Test: `tests/Feature/Api/V1/BudgetTest.php` (+ `tests/Feature/Budget/BudgetTest.php` untuk notifikasi & perhitungan service)

Data awal = keluarga golden: pengeluaran Oktober 2026 kategori Makanan 650.000 (termasuk sub Restoran), Transportasi 50.000; hari ini 31 Okt 2026 (sisa 1 hari periode).

## Positif

| ID     | Skenario (yang dilakukan user)                                                                | Hasil yang diharapkan                                                                                                       |
| ------ | --------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| BUD-01 | Ayah mengatur budget Makanan `800rb` untuk Oktober                                            | 200; dibaca ulang: 800.000, realisasi 650.000 (ikut Restoran), sisa 150.000, 81,3%, `warning` Waspada, jatah harian 150.000 |
| BUD-02 | Ayah mengatur Makanan 800rb + Transportasi 100rb sekaligus                                    | 2 budget; `meta.totals` 900.000 / realisasi 700.000; urut nominal terbesar; Transportasi hilang dari `available_categories` |
| BUD-03 | Ayah mengubah budget Makanan dari 800rb ke 1jt (kirim ulang kategori sama)                    | Tetap **satu** budget Makanan Oktober, nominal 1.000.000, status `safe` (BR-F01)                                            |
| BUD-04 | Ayah memberi budget Makanan 800rb dan sub Restoran 300rb                                      | Restoran tampil sendiri; `meta.totals.amount` = 800.000 (sub tidak dihitung dua kali)                                       |
| BUD-05 | Budget Transportasi 40rb (realisasi 50rb)                                                     | `over` Lewat, sisa −10.000, 125%, jatah harian 0                                                                            |
| BUD-06 | Ayah menyalin dari September (Makanan, Transportasi) ke Oktober yang sudah punya Makanan 1jt  | `meta.copied` 1; Transportasi tersalin; Makanan Oktober tetap 1jt (BR-F07); salin ulang → `copied` 0, tidak ada duplikat    |
| BUD-07 | Menyalin saat kategori Transportasi sudah diarsipkan                                          | Transportasi tidak disalin (BR-F09)                                                                                         |
| BUD-08 | Ayah menghapus budget Makanan                                                                 | 204; hilang dari daftar, kategori kembali ke `available_categories`; tercatat di activity log                               |
| BUD-09 | Ayah merencanakan November (periode depan)                                                    | 200; `meta.period.key` 2026-11, `next_key` null; realisasi 0                                                                |
| BUD-10 | Ibu (member) membuka budget                                                                   | 200, angka sama dengan Ayah, `meta.can_manage` false                                                                        |
| BUD-11 | Ayah mengatur budget Makanan 700rb (realisasi 650rb = 92,9%), lalu menyimpan ulang nilai sama | Notifikasi 80% terkirim ke anggota **sekali** saja (BR-F05)                                                                 |
| BUD-12 | Budget yang sudah ada di kategori yang kemudian diarsipkan diubah nominalnya                  | 200; nominal berubah (BR-F09 hanya untuk budget baru)                                                                       |

## Negatif

| ID     | Skenario                                                                      | Hasil yang diharapkan                                                        |
| ------ | ----------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| BUD-21 | Nominal kosong / 0 / minus / `abc`                                            | 422 `budgets.0.amount`; tidak ada budget                                     |
| BUD-22 | Bulk Makanan (valid) + Gaji (pemasukan)                                       | 422 `budgets.1.category_id`, `rule` BR-F01; Makanan **juga tidak** tersimpan |
| BUD-23 | Budget baru untuk kategori diarsipkan                                         | 422 `budgets.0.category_id`, `rule` BR-F09                                   |
| BUD-24 | Periode 2027-01 (dua ke depan) di GET / PUT / copy; periode `2026-13` / `okt` | 422 `period` (BR-F08 / format); tidak ada budget                             |
| BUD-25 | Kategori sama dua kali dalam satu kiriman                                     | 422 `budgets.1.category_id`                                                  |
| BUD-26 | Kategori milik keluarga lain                                                  | 404; tidak ada budget (BR-I06)                                               |
| BUD-27 | Ibu (member), viewer, dan token `read` mengatur / menyalin / menghapus budget | 403; data tidak berubah (BR-B09)                                             |
| BUD-28 | Menghapus budget keluarga lain                                                | 404; budget tetap ada                                                        |
| BUD-29 | `budgets` kosong / lebih dari 100 baris                                       | 422 `budgets`                                                                |
