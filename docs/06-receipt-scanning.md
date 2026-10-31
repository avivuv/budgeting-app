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

- `ClaudeReceiptParser` — implementasi utama (Claude API, vision).
- `FakeReceiptParser` — untuk test & development tanpa API key (`RECEIPT_PARSER=fake`).
- Binding di `RepositoryServiceProvider`/`AppServiceProvider` berdasarkan `config('receipt.driver')`.

`ReceiptParseContext` membawa daftar nama kategori pengeluaran keluarga agar AI memilih dari daftar tersebut.

## 4. Claude API

- Model default: Claude **Haiku** (cepat & hemat); fallback ke **Sonnet** bila `confidence < 0.6` atau total tidak cocok dengan jumlah item. Model ID diatur di config (`RECEIPT_MODEL`, `RECEIPT_FALLBACK_MODEL`), bukan di-hardcode.
- Kirim gambar sebagai base64 + instruksi; minta output **JSON** sesuai skema (gunakan tool/structured output agar valid).
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
RECEIPT_PARSER=claude          # claude | fake
ANTHROPIC_API_KEY=
RECEIPT_MODEL=
RECEIPT_FALLBACK_MODEL=
RECEIPT_MAX_IMAGE_PX=1600
```
