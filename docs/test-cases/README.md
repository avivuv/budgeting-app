# Test Case per Fitur

Test API ditulis **per fitur, berdasarkan skenario**, seolah-olah orang sedang memakai aplikasi: login sebagai user tertentu → isi form → simpan → lihat hasilnya. Bukan test per field.

## Cara menyusun

1. Baca form fitur (FormRequest) dan logikanya (Service + [business rules](../11-business-rules.md)), serta [API contract](../api-contracts/README.md) fiturnya.
2. Tulis semua skenario di `docs/test-cases/<fitur>.md`, positif dan negatif, masing-masing dengan ID (`WAL-01`, `TRX-11`, …).
3. Setiap skenario = satu test di `tests/Feature/Api/V1/<Fitur>Test.php`. Nama test dalam English dan memuat ID skenario + ID rule.
4. Skenario berubah → update dokumen dulu, baru test.

## Format setiap test

```php
it('[WAL-01] creates a bank wallet with an opening balance [BR-C01]', function () {
    // GIVEN — siapa user-nya dan kondisi awal
    $owner = ...;

    // WHEN — user mengisi form dan menyimpan (input persis seperti diketik)
    $response = $this->postJson('/api/v1/wallets', [
        'name' => 'Tabungan Agus',
        'type' => 'bank',
        'initial_balance' => '1.500.000',
    ]);

    // THEN — output sama dengan input, terlihat di response, database, dan saat dibaca ulang
    $response->assertCreated()->assertJsonPath('data.name', 'Tabungan Agus');
    $this->assertDatabaseHas('wallets', ['name' => 'Tabungan Agus', 'initial_balance' => 1_500_000]);
    ...
});
```

Aturan:

- **Positif**: cek response, isi database, dan hasil baca ulang (GET), plus efek logikanya (saldo, total, dsb.).
- **Negatif**: cek status + pesan error di field yang tepat, **dan database tidak berubah**.
- Input ditulis seperti yang diketik user (`"75rb"`, `"1.500.000"`), bukan nilai yang sudah diolah.

## Melihat input/output asli

```bash
composer test:api        # API test + catat setiap request/response ke storage/logs/api-trace.log
```

## Daftar fitur

Urutan mengikuti tahap M1–M2 di [18-api-first-migration.md](../18-api-first-migration.md).

| Fitur                                                | Dokumen                              | Status        |
| ---------------------------------------------------- | ------------------------------------ | ------------- |
| Auth (login SPA, token Android, 2FA, passkey, reset) | [auth.md](auth.md)                   | ✅ test hijau |
| Dompet                                               | [wallets.md](wallets.md)             | ✅ test hijau |
| Kategori                                             | [categories.md](categories.md)       | ✅ test hijau |
| Transaksi                                            | [transactions.md](transactions.md)   | ✅ test hijau |
| Budget                                               | [budgets.md](budgets.md)             | ✅ test hijau |
| Ringkasan, Dashboard, Laporan & Export               | [reports.md](reports.md)             | ✅ test hijau |
| Tabungan                                             | [savings.md](savings.md)             | ✅ test hijau |
| Scan Struk                                           | [receipts.md](receipts.md)           | ✅ test hijau |
| Keluarga, Anggota & Undangan                         | [family.md](family.md)               | ✅ test hijau |
| Onboarding                                           | [onboarding.md](onboarding.md)       | ✅ test hijau |
| Notifikasi                                           | [notifications.md](notifications.md) | ✅ test hijau |
| Profil, Keamanan & Token                             | settings.md                          | belum         |
