# Test Case — Onboarding (Buat Keluarga)

**Form**: nama keluarga (diisi saran "Keluarga <nama belakang>") · tanggal awal periode (1–28, opsional).
**Logika**: pembuat jadi satu-satunya Owner (BR-I01) · keluarga baru jadi keluarga aktif · dompet & kategori bawaan dibuat · periode mengikuti tanggal awal (BR-D01) · kiriman ganda tidak membuat dua keluarga (BR-B13).
**Kontrak**: [api-contracts/onboarding.md](../api-contracts/onboarding.md) · Test: `tests/Feature/Api/V1/OnboardingTest.php` (+ `FamilyServiceTest`)

Data awal: Budi baru mendaftar, belum punya keluarga. Hari ini 31 Okt 2026.

## Positif

| ID     | Skenario (yang dilakukan user)                       | Hasil yang diharapkan                                                                                                         |
| ------ | ---------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| ONB-01 | Budi (tanpa keluarga) membuka dompet                 | 409 `code: no_family` — klien membuka layar onboarding                                                                        |
| ONB-02 | Budi membuat "Keluarga Budi"                         | 201; `/me`: keluarga aktif baru, peran owner; dompet Tunai saldo 0; kategori bawaan pengeluaran & pemasukan; periode 1–31 Okt |
| ONB-03 | Budi membuat keluarga dengan awal periode tanggal 25 | Ringkasan periode berjalan: 25 Okt – 24 Nov 2026 (label November)                                                             |
| ONB-04 | Kiriman ganda dengan `Idempotency-Key` sama          | Hanya satu keluarga                                                                                                           |
| ONB-05 | Ayah (sudah punya keluarga) membuat keluarga kedua   | Keluarga kedua jadi aktif; keluarga pertama tetap ada di `families`                                                           |

## Negatif

| ID     | Skenario                     | Hasil yang diharapkan          |
| ------ | ---------------------------- | ------------------------------ |
| ONB-21 | Nama kosong / > 100 karakter | 422 `name`; tidak ada keluarga |
| ONB-22 | Awal periode 0 / 29 / `abc`  | 422 `period_start_day`         |
| ONB-23 | Token `read` / belum login   | 403 / 401                      |
