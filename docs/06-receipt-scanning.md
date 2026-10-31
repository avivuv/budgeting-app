# 06 — Deteksi Struk

## 1. Tujuan
Pengguna cukup memotret struk (Telegram atau upload web); sistem mengisi toko, tanggal, total, item, dan kategori. Pengguna hanya mengonfirmasi.

## 2. Alur

```
Telegram foto / Web upload
   │
   ▼
ReceiptService::store()
   - cek kuota bulanan (usage_logs)        → QuotaExceededException
   - download file (Telegram getFile) / simpan upload
   - resize max 1600px, JPEG q80 (intervention/image)
   - sha256 → tolak bila file identik sudah ada
   - receipts.status = pending
   - dispatch ProcessReceiptJob (queue: receipts)
   - balas "⏳ Membaca struk..."
   │
   ▼
ProcessReceiptJob
   - TenantContext::set(family)
   - status = processing
   - ReceiptParserInterface::parse(image) → ReceiptData
   - map kategori: nama kategori AI → categories keluarga (keywords/fuzzy)
   - cek duplikat: merchant + tanggal + total sama dalam keluarga
   - status = parsed, simpan receipt_items
   - kirim/edit pesan preview
   │
   ▼
User: [✅ Simpan] [📋 Pecah per item] [✏️ Ubah] [❌ Batal]
   - Simpan       → 1 transaksi total, source=receipt, receipt_id
   - Pecah item   → transaksi per kategori (item dikelompokkan per kategori)
   - status = confirmed / rejected
```

Retry job: 3x dengan backoff 10s/30s/60s; gagal permanen → `status = failed`, bot membalas *"Struk tidak terbaca, ketik manual: `belanja 87500`"*.

## 3. Abstraksi Parser

```php
interface ReceiptParserInterface
{
    public function parse(string $imagePath, ReceiptParseContext $context): ReceiptData;
}
```

- `GeminiReceiptParser` — rencana implementasi utama (Google Gemini, model Flash free tier via Google AI Studio).
- `ClaudeReceiptParser` — alternatif bila butuh akurasi lebih tinggi.
- `FakeReceiptParser` — untuk test & development tanpa API key (`RECEIPT_PARSER=fake`). **Default sampai API key tersedia.**
- Binding di `AppServiceProvider` berdasarkan `config('receipt.driver')`. Ganti provider = ganti env, tanpa mengubah service/handler.
- Semua parser memakai prompt & skema output yang sama (`resources/prompts/receipt.md`, §4.1) dan wajib lulus test kontrak yang sama (`tests/Feature/Integration/ReceiptParserContractTest.php`) dengan respons API di-fake.

`ReceiptParseContext` membawa daftar nama kategori pengeluaran keluarga agar AI memilih dari daftar tersebut.

## 4. Provider AI

API key menyusul; sampai saat itu `RECEIPT_PARSER=fake`.

### 4.0 Gemini (rencana utama)
- Model **Gemini Flash** (free tier Google AI Studio). Model ID di config (`RECEIPT_MODEL`), tidak di-hardcode.
- Kirim gambar inline (base64) + prompt; pakai `responseMimeType: application/json` + `responseSchema` agar output valid sesuai §4.1.
- **Free tier dibatasi rate limit (request/menit & per hari)**: queue `receipts` di-throttle (`RateLimited` middleware job) sesuai `RECEIPT_RATE_PER_MINUTE`; saat kena `429`, job di-release dengan backoff, user mendapat pesan "Struk sedang antre".
- **Privasi**: pada free tier, Google dapat memakai data input untuk peningkatan produk. Tampilkan pemberitahuan di halaman pengaturan; pertimbangkan tier berbayar sebelum dipakai keluarga lain (multi-tenant).

### 4.0b Claude (alternatif)
- Model ringan sebagai default, model lebih besar sebagai fallback bila `confidence < 0.6`.

### Umum
- Fallback model (`RECEIPT_FALLBACK_MODEL`) opsional, untuk semua provider.
- Timeout 30 detik.

### 4.1 Skema output
```json
{
  "is_receipt": true,
  "merchant": "Indomaret Jl. Sudirman",
  "date": "2026-10-31",
  "currency": "IDR",
  "items": [
    { "name": "Susu UHT 1L", "qty": 2, "unit_price": 18500, "total": 37000, "category": "Belanja Harian" }
  ],
  "subtotal": 85000,
  "discount": 0,
  "tax": 2500,
  "total": 87500,
  "payment_method": "QRIS",
  "suggested_category": "Belanja Harian",
  "confidence": 0.92
}
```

### 4.2 Poin instruksi prompt
- Struk Indonesia: titik = pemisah ribuan, `Rp`, format tanggal `dd/mm/yy`, singkatan item.
- Nominal dalam rupiah utuh (integer).
- `total` = jumlah yang dibayar (setelah diskon/pajak), **bukan** uang tunai/kembalian.
- Kategori wajib dari daftar yang diberikan.
- Jika bukan struk / tidak terbaca → `is_receipt: false`.
- Tanggal tidak terbaca → `null` (sistem pakai hari ini).

### 4.3 Validasi hasil
- `is_receipt = false` → balas "Ini sepertinya bukan struk".
- Σ item ± diskon/pajak ≠ total (selisih > 2%) → turunkan confidence, tampilkan peringatan di preview.
- Tanggal di masa depan / > 1 tahun lalu → pakai hari ini, beri tanda.

## 5. Kuota & Biaya
- `families.receipt_quota_monthly` (default 50), dihitung di `usage_logs` per periode.
- Sisa kuota ditampilkan di web & saat `/struk`.
- Super Admin bisa ubah kuota per keluarga.
- Log `model` dan token usage di `raw_response` untuk memantau biaya.

## 6. Penyimpanan & Privasi
- File di disk `local` (`storage/app/private/receipts/{family_id}/{yyyy}/{mm}/`), **tidak** di `public`.
- Akses via route terproteksi `GET /receipts/{receipt}/image` (Policy + scope tenant).
- Struk yang `rejected` dihapus filenya setelah 7 hari (scheduler).

## 7. Web Upload
- Halaman *Transaksi → Scan Struk*: drag & drop / kamera HP (`<input capture="environment">`).
- Polling status (atau Inertia reload tiap 2 detik) sampai `parsed`, lalu form transaksi terisi otomatis.

## 8. Konfigurasi
```env
RECEIPT_PARSER=fake            # fake | gemini | claude
GEMINI_API_KEY=
ANTHROPIC_API_KEY=
RECEIPT_MODEL=
RECEIPT_FALLBACK_MODEL=
RECEIPT_RATE_PER_MINUTE=10
RECEIPT_MAX_IMAGE_PX=1600
```
