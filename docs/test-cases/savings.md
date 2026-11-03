# Test Case — Tabungan (Tujuan Menabung)

**Form**: nama · target (bebas ketik `30jt`) · dompet terkait (opsional) · tenggat (opsional) · ikon/warna. Tombol "Setor" / "Ambil" + nominal untuk tujuan tanpa dompet.
**Logika**: progres = saldo dompet terkait atau total setoran (BR-K01) · ambil tidak boleh melebihi terkumpul; tujuan berdompet tidak menerima setoran manual (BR-K02) · persen, sisa, tercapai (BR-K03) · setoran per bulan sampai tenggat (BR-K04) · Owner/Admin mengelola, Member boleh setor/ambil (BR-B09) · kiriman ganda tidak menyetor dua kali (BR-B13).
**Kontrak**: [api-contracts/savings.md](../api-contracts/savings.md) · Test: `tests/Feature/Api/V1/SavingGoalTest.php` (+ `SavingGoalCalculatorTest` untuk perhitungan)

Data awal = keluarga golden (BCA 15.100.000, Tunai 1.200.000), hari ini 31 Okt 2026 (periode Oktober).

## Positif

| ID     | Skenario (yang dilakukan user)                                               | Hasil yang diharapkan                                                                                                            |
| ------ | ---------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| SAV-01 | Ayah membuat "Dana Darurat" target `30jt`, tenggat 31 Mar 2027, tanpa dompet | 201; dibaca ulang: target 30.000.000, progres 0, sisa 30.000.000, `monthly_needed` 5.000.000 (6 periode Okt–Mar), tercapai false |
| SAV-02 | Ayah membuat "Liburan" terhubung ke dompet Tunai, target 1jt                 | progres = saldo Tunai 1.200.000, 120%, tercapai true, sisa 0, `monthly_needed` null                                              |
| SAV-03 | Ibu (member) menyetor `500rb` lalu mengambil `200rb` dari Dana Darurat       | progres 500.000 → 300.000; pesan "Tabungan ditambahkan." / "Tabungan diambil."                                                   |
| SAV-04 | Setoran dikirim dua kali dengan `Idempotency-Key` sama                       | progres hanya naik sekali                                                                                                        |
| SAV-05 | Setoran mencapai target                                                      | `achieved` true, `achieved_at` terisi; setelah diambil lagi di bawah target → false, `achieved_at` kosong (BR-K03)               |
| SAV-06 | Ayah mengubah nama & target, menghapus tenggat                               | 200; nilai baru terbaca; `monthly_needed` null                                                                                   |
| SAV-07 | Ayah menghapus tujuan                                                        | 204; hilang dari daftar; saldo dompet tidak berubah; tercatat di activity log                                                    |
| SAV-08 | Ibu membuka daftar                                                           | 200; `can_manage` false, `can_contribute` true                                                                                   |

## Negatif

| ID     | Skenario                                                     | Hasil yang diharapkan                    |
| ------ | ------------------------------------------------------------ | ---------------------------------------- |
| SAV-21 | Nama kosong / target kosong, 0, `abc` / tenggat `31-03-2027` | 422 field terkait; tidak ada tujuan baru |
| SAV-22 | Mengambil lebih dari yang terkumpul                          | 422 `amount` BR-K02; progres tetap       |
| SAV-23 | Menyetor manual ke tujuan yang terhubung dompet              | 422 `amount` BR-K02                      |
| SAV-24 | Setoran `direction` tidak dikenal / nominal 0                | 422                                      |
| SAV-25 | Ibu (member) membuat / mengubah / menghapus tujuan           | 403; data tidak berubah (BR-B09)         |
| SAV-26 | Viewer / token `read` menyetor                               | 403; progres tetap                       |
| SAV-27 | Memakai dompet keluarga lain / membuka tujuan keluarga lain  | 404                                      |
