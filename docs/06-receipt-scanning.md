# 06 — Deteksi Struk

## 1. Tujuan

Pengguna cukup memotret struk atau mengunggah bukti transaksi; sistem mengisi toko, tanggal, total, item, dan kategori. Pengguna hanya mengonfirmasi.

Urutan implementasi: **web upload & REST API lebih dulu**, input via Telegram menyusul di fase bot (lihat [09-roadmap.md](09-roadmap.md)). Service, parser, dan aturan yang sama dipakai keduanya.

### 1.1 Jenis dokumen yang diterima (BR-H11)

| `document_type`  | Contoh                                                               | Catatan                                       |
| ---------------- | -------------------------------------------------------------------- | --------------------------------------------- |
| `receipt`        | struk kertas minimarket, restoran, SPBU                              | ada daftar item                               |
| `transfer_proof` | screenshot bukti transfer m-banking                                  | biasanya tanpa item; merchant = nama penerima |
| `ewallet`        | screenshot GoPay, OVO, DANA, ShopeePay                               | dompet bisa ditebak dari aplikasi             |
| `invoice`        | invoice/e-receipt Tokopedia, Shopee, PLN, Grab (gambar atau **PDF**) | bisa banyak item                              |

Format: JPG, PNG, WEBP, HEIC (dikonversi ke JPG), PDF (maks. 3 halaman). Ukuran maks. 10 MB.

## 2. Prinsip: Wajib Konfirmasi User

> Hasil baca struk **tidak pernah langsung disimpan sebagai transaksi** (BR-H08). Hasil AI dikirim balik ke user, lalu user meninjau dan mengoreksi. Transaksi baru dibuat setelah user menekan **Simpan**.

- Selama menunggu konfirmasi, data hanya ada sebagai **draft** di tabel `receipts` (status `parsed`) + `receipt_items`. Tabel `transactions` belum tersentuh, sehingga dashboard, laporan, saldo, dan budget **tidak** berubah.
- Draft dipakai juga untuk cek duplikat, kuota, dan audit (`raw_response`).
- Draft yang tidak dikonfirmasi 24 jam → `expired` (BR-H10).
- Status: `pending → processing → parsed (menunggu user) → confirmed | rejected | expired`; `failed` bila gagal dibaca.

## 3. Alur

```
Web upload / REST API / Telegram (foto atau dokumen)
   │
   ▼
ReceiptService::store()
   - validasi tipe & ukuran (BR-H11)
   - cek kuota bulanan (usage_logs)        → QuotaExceededException
   - download file (Telegram getFile) / simpan upload
   - gambar JPG/PNG/WEBP: resize max 1600px, JPEG q80 (intervention/image, GD); PDF & HEIC: disimpan apa adanya (GD tidak bisa membaca HEIC)
   - sha256 → tolak bila file identik sudah ada
   - receipts.status = pending
   - dispatch ProcessReceiptJob (queue `RECEIPT_QUEUE`, default `default` agar worker biasa ikut memproses)
   - balas "⏳ Membaca struk..."
   │
   ▼
ProcessReceiptJob
   - TenantContext::set(family)
   - status = processing
   - ReceiptParserInterface::parse(image) → ReceiptData
   - map kategori: nama kategori AI → categories keluarga (keywords/fuzzy)
   - cek duplikat: merchant + tanggal + total sama dalam keluarga
   - status = parsed, simpan receipt_items        ← DRAFT, belum ada transaksi
   - kirim hasil baca ke user untuk ditinjau
   │
   ▼
User meninjau hasil baca:
   🧾 Indomaret · 31 Okt 2026
   Total: Rp 87.500 · Belanja Harian · 👛 Tunai
   Item: Susu UHT 1L ×2 Rp 37.000, Roti Tawar Rp 15.500, ...
   ⚠️ (peringatan bila ada: total tidak cocok / kemungkinan duplikat / tanggal diganti)
   [✅ Simpan] [✏️ Ubah total] [🏷 Kategori] [👛 Dompet] [📅 Tanggal] [📋 Pecah per item] [❌ Batal]
   │
   ├─ Ubah …      → update draft, kirim ulang preview (masih belum jadi transaksi)
   ├─ Simpan      → ReceiptService::confirm() → 1 transaksi total, source=receipt, receipt_id
   ├─ Pecah item  → preview ulang per kategori → Simpan → transaksi per kategori (BR-H02)
   └─ Batal       → status = rejected, tidak ada transaksi
```

Di web, hasil baca tampil sebagai **form yang sudah terisi** (bisa diedit); transaksi dibuat hanya saat tombol Simpan ditekan. REST API: `POST /receipts` → `GET /receipts/{id}` (draft) → `PATCH /receipts/{id}` (koreksi) → `POST /receipts/{id}/confirm`.

Retry job: 3x dengan backoff 10s/30s/60s; gagal permanen → `status = failed`, bot membalas _"Struk tidak terbaca, ketik manual: `belanja 87500`"_.

## 4. Abstraksi Parser

```php
interface ReceiptParserInterface
{
    /** @param string $filePath gambar (jpg/png/webp) atau PDF */
    public function parse(string $filePath, string $mimeType, ReceiptParseContext $context): ReceiptData;
}
```

- `GeminiReceiptParser` — rencana implementasi utama (Google Gemini, model Flash free tier via Google AI Studio).
- `ClaudeReceiptParser` — alternatif bila butuh akurasi lebih tinggi (belum dibuat; cukup implementasi interface + lulus test kontrak).
- `FakeReceiptParser` — untuk test & development tanpa API key (`RECEIPT_PARSER=fake`). **Default sampai API key tersedia.**
- Binding di `AppServiceProvider` berdasarkan `config('receipt.driver')`. Ganti provider = ganti env, tanpa mengubah service/handler.
- Semua parser memakai prompt & skema output yang sama (`resources/prompts/receipt.md`, §5.4) dan wajib lulus test kontrak yang sama (`tests/Feature/Integration/ReceiptParserContractTest.php`) dengan respons API di-fake.

`ReceiptParseContext` membawa daftar nama kategori pengeluaran **dan nama dompet** keluarga agar AI memilih dari daftar tersebut (mis. screenshot GoPay → dompet "GoPay").

## 5. Provider AI

API key menyusul; sampai saat itu `RECEIPT_PARSER=fake`.

### 5.1 Gemini (rencana utama)

- Model **Gemini Flash** (free tier Google AI Studio). Model ID di config (`RECEIPT_MODEL`), tidak di-hardcode.
- Kirim gambar/PDF inline (base64, `mimeType` sesuai file) + prompt; pakai `responseMimeType: application/json` + `responseSchema` agar output valid sesuai §5.4.
- **Free tier dibatasi rate limit (request/menit & per hari)**: queue `receipts` di-throttle (`RateLimited` middleware job) sesuai `RECEIPT_RATE_PER_MINUTE`; saat kena `429`, job di-release dengan backoff, user mendapat pesan "Struk sedang antre".
- **Privasi**: pada free tier, Google dapat memakai data input untuk peningkatan produk. Tampilkan pemberitahuan di halaman pengaturan; pertimbangkan tier berbayar sebelum dipakai keluarga lain (multi-tenant).

### 5.2 Claude (alternatif)

- Model ringan sebagai default, model lebih besar sebagai fallback bila `confidence < 0.6`.

### 5.3 Umum

- Fallback model (`RECEIPT_FALLBACK_MODEL`) opsional, untuk semua provider.
- Timeout 30 detik.

### 5.4 Skema output

```json
{
    "is_receipt": true,
    "document_type": "receipt",
    "merchant": "Indomaret Jl. Sudirman",
    "date": "2026-10-31",
    "currency": "IDR",
    "items": [
        {
            "name": "Susu UHT 1L",
            "qty": 2,
            "unit_price": 18500,
            "total": 37000,
            "category": "Belanja Harian"
        }
    ],
    "subtotal": 85000,
    "discount": 0,
    "tax": 2500,
    "total": 87500,
    "payment_method": "QRIS",
    "suggested_wallet": null,
    "suggested_category": "Belanja Harian",
    "confidence": 0.92
}
```

### 5.5 Poin instruksi prompt

- Struk Indonesia: titik = pemisah ribuan, `Rp`, format tanggal `dd/mm/yy`, singkatan item.
- Nominal dalam rupiah utuh (integer).
- `total` = jumlah yang dibayar (setelah diskon/pajak), **bukan** uang tunai/kembalian.
- Kategori wajib dari daftar yang diberikan.
- Bukti transfer / e-wallet: `total` = nominal transfer/bayar (bukan saldo tersisa, bukan biaya admin kecuali ikut terpotong), `merchant` = penerima, `items` boleh kosong.
- Jika bukan struk / bukti transaksi / tidak terbaca → `is_receipt: false`.
- Tanggal tidak terbaca → `null` (sistem pakai hari ini).

### 5.6 Validasi hasil

- `is_receipt = false` → balas "Ini sepertinya bukan struk".
- Σ item ± diskon/pajak ≠ total (selisih > 2%) → turunkan confidence, tampilkan peringatan di preview.
- Tanggal di masa depan / > 1 tahun lalu → pakai hari ini, beri tanda.

## 6. Kuota & Biaya

- `families.receipt_quota_monthly` (default 50), dihitung di `usage_logs` per periode.
- Sisa kuota ditampilkan di web & saat `/struk`.
- Super Admin bisa ubah kuota per keluarga.
- Log `model` dan token usage di `raw_response` untuk memantau biaya.

## 6b. Idempotensi

- Konfirmasi memakai lock `receipt:{id}:confirm` + cek status `parsed` di dalam `DB::transaction` dengan `lockForUpdate()`; tekan Simpan dua kali → transaksi tetap satu (BR-H07, BR-B13).

## 7. Penyimpanan & Privasi

- File disimpan di disk `config('filesystems.uploads')` (`UPLOADS_DISK`): `local` (`storage/app/private`) di awal, **MinIO** (S3-compatible) saat sudah jalan, storage S3-compatible lain di production. Path: `receipts/{family_id}/{yyyy}/{mm}/{uuid}.{ext}`. Tidak pernah di disk `public`.
- Semua akses file lewat `App\Services\Storage\FileStorageService` (simpan, hapus, stream, temporary URL) agar ganti disk tidak mengubah kode lain.
- Akses via route terproteksi `GET /receipts/{receipt}/image` (Policy + scope tenant).
- Struk yang `rejected` dihapus filenya setelah 7 hari (scheduler).

## 8. Web Upload

- Halaman _Transaksi → Scan Struk_: drag & drop, pilih file (gambar/PDF), atau kamera HP (`<input accept="image/*,application/pdf" capture="environment">`).
- Polling status (`usePoll` Inertia) sampai `parsed`, lalu hasil baca tampil sebagai **form review yang sudah terisi** (total, tanggal, merchant, kategori, dompet, item; semua bisa diedit). **Belum ada transaksi tersimpan** sampai user menekan Simpan (BR-H08, BR-H09).

## 9. Konfigurasi

```env
RECEIPT_PARSER=fake            # fake | gemini
GEMINI_API_KEY=
ANTHROPIC_API_KEY=
RECEIPT_MODEL=gemini-2.5-flash
RECEIPT_FALLBACK_MODEL=
RECEIPT_RATE_PER_MINUTE=10
RECEIPT_MAX_IMAGE_PX=1600
RECEIPT_MAX_FILE_MB=10
RECEIPT_MAX_PDF_PAGES=3
RECEIPT_QUEUE=default
```

Lokal: hasil baca diproses oleh queue worker (`composer run dev` sudah menjalankannya). Tanpa worker, struk tetap berstatus "Antre".
