# Test Case — Notifikasi

**Form**: lonceng dengan jumlah belum dibaca · daftar notifikasi (ketuk = buka & tandai dibaca) · "tandai semua dibaca".
**Logika**: peringatan budget 80%/100% dikirim ke semua anggota sekali per periode (BR-F05) · notifikasi milik user, tidak bisa dibaca/ditandai user lain.
**Kontrak**: [api-contracts/notifications.md](../api-contracts/notifications.md) · Test: `tests/Feature/Api/V1/NotificationTest.php` (+ `BudgetTest` untuk kapan notifikasi dikirim)

Data awal = keluarga golden; Ayah mengatur budget Makanan 800rb (realisasi 650rb = 81,3%) → Ayah & Ibu masing-masing menerima 1 notifikasi 80%.

## Positif

| ID     | Skenario (yang dilakukan user)         | Hasil yang diharapkan                                                                                            |
| ------ | -------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| NOT-01 | Ibu membuka notifikasi                 | 1 notifikasi `budget_threshold` "Budget Makanan & Minuman sudah 80%", `subject.period` 2026-10, `unread_count` 1 |
| NOT-02 | Ibu mengetuk notifikasi itu            | `unread_count` 0; dibaca ulang `read` true; notifikasi Ayah tetap belum dibaca                                   |
| NOT-03 | Ayah menandai semua dibaca             | `unread_count` 0                                                                                                 |
| NOT-04 | User tanpa keluarga membuka notifikasi | 200, daftar kosong (tidak 409)                                                                                   |

## Negatif

| ID     | Skenario                                   | Hasil yang diharapkan                   |
| ------ | ------------------------------------------ | --------------------------------------- |
| NOT-21 | Ibu menandai notifikasi milik Ayah         | 404; notifikasi Ayah tetap belum dibaca |
| NOT-22 | `id` bukan uuid                            | 422                                     |
| NOT-23 | Token `read` menandai dibaca / belum login | 403 / 401                               |
