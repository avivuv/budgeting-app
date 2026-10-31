# 01 — Product Requirements

## 1. Latar Belakang

Mencatat keuangan keluarga sering gagal karena **input merepotkan** (harus buka aplikasi, isi form panjang) dan **laporan sulit dibaca**. Aplikasi ini memindahkan input ke tempat yang sudah dipakai setiap hari — **Telegram** — dan menyajikan laporan yang jelas di web.

## 2. Tujuan

1. Input transaksi < 5 detik lewat satu pesan Telegram (`bensin 50rb`).
2. Input dari foto struk tanpa mengetik.
3. Satu buku keuangan bersama untuk seluruh anggota keluarga, tetap tahu siapa mencatat apa.
4. Dashboard yang menarik dan laporan lengkap (bulanan, tahunan, kategori, anggota, budget).
5. Bisa dipakai banyak keluarga (multi-tenant) dengan data terisolasi.

## 3. Persona

| Persona | Kebutuhan |
|---|---|
| **Owner/Kepala keluarga** | Atur dompet, kategori, budget; lihat laporan lengkap; undang anggota |
| **Anggota (pasangan)** | Catat belanja cepat via Telegram/struk; cek sisa budget |
| **Viewer (anak)** | Lihat saldo uang saku / tabungan; tidak bisa ubah data |
| **Super Admin (pemilik aplikasi)** | Kelola keluarga terdaftar, pantau pemakaian & kuota |

## 4. Ruang Lingkup Fitur

### 4.1 Akun & Keluarga
- Registrasi, login, lupa password, verifikasi email.
- Buat keluarga → otomatis jadi **Owner**.
- Undang anggota via email/link dengan role tertentu.
- Satu user bisa tergabung di beberapa keluarga; switch keluarga aktif.
- Pengaturan keluarga: nama, mata uang (default IDR), zona waktu (default `Asia/Jakarta`), tanggal awal periode bulanan (mis. tanggal gajian).

### 4.2 Master Data
- **Dompet**: Tunai, Bank, E-wallet, Kartu kredit; saldo awal; arsip.
- **Kategori**: pemasukan/pengeluaran, sub-kategori, ikon, warna, **keywords** untuk tebakan otomatis. Kategori default dibuat saat keluarga dibuat.
- **Tag** bebas (mis. `#liburan-bali`).

### 4.3 Transaksi
- Jenis: pemasukan, pengeluaran, transfer antar dompet.
- Field: nominal, tanggal, dompet, kategori, catatan, tag, lampiran (foto struk), pencatat, sumber (`web`/`telegram`/`receipt`/`recurring`).
- Edit, hapus (soft delete), undo terakhir.
- **Transaksi berulang** (gaji, tagihan, cicilan).

### 4.4 Budget & Tabungan
- Budget bulanan per kategori; peringatan di 80% dan 100%.
- Tujuan tabungan dengan target & progress.

### 4.5 Telegram Bot — lihat [05-telegram-bot.md](05-telegram-bot.md)
- Chat pribadi & grup keluarga.
- Input teks bebas, foto struk, tombol konfirmasi.
- Perintah ringkasan, saldo, budget, undo, laporan PDF.
- Notifikasi budget, pengingat tagihan, ringkasan terjadwal.

### 4.6 Deteksi Struk — lihat [06-receipt-scanning.md](06-receipt-scanning.md)

### 4.7 Dashboard & Laporan — lihat [07-dashboard-reports.md](07-dashboard-reports.md)

### 4.8 Super Admin
- Daftar keluarga, jumlah user/transaksi, pemakaian scan struk.
- Suspend / aktifkan keluarga, atur kuota.

## 5. User Story Utama

| ID | Sebagai | Saya ingin | Agar |
|---|---|---|---|
| US-01 | Anggota | mengetik `makan 35rb` di Telegram | transaksi tercatat tanpa buka aplikasi |
| US-02 | Anggota | mengirim foto struk | nominal & toko terisi otomatis |
| US-03 | Anggota | mencatat di grup keluarga | semua anggota tahu pengeluaran terbaru |
| US-04 | Owner | melihat pengeluaran per kategori bulan ini | tahu ke mana uang pergi |
| US-05 | Owner | mendapat peringatan saat budget hampir habis | bisa mengerem pengeluaran |
| US-06 | Owner | export laporan bulanan ke PDF/Excel | arsip / dibahas bersama |
| US-07 | Owner | mengundang pasangan | mencatat di buku yang sama |
| US-08 | Anggota | `/undo` | membatalkan salah input |
| US-09 | Viewer | melihat saldo tabungan | ikut memantau tanpa bisa mengubah |

## 6. Kebutuhan Non-Fungsional

| Aspek | Target |
|---|---|
| Isolasi data | Tidak ada query yang bisa mengakses data keluarga lain (dijamin global scope + test) |
| Respons bot | Balasan teks < 2 detik; struk < 15 detik (diproses di queue) |
| Mobile | Semua halaman nyaman di lebar 360px; PWA installable |
| Bahasa | UI & bot Bahasa Indonesia; format angka `Rp 1.250.000`, tanggal `31 Okt 2026` |
| Keamanan | Webhook Telegram diverifikasi secret token; file struk privat; rate limit |
| Audit | Setiap transaksi menyimpan `created_by` dan `source` |

## 7. Di Luar Lingkup (untuk saat ini)

- Sinkronisasi otomatis mutasi bank.
- Investasi / saham / kurs multi-mata uang real-time.
- Aplikasi native Android/iOS (cukup PWA).
- Pembayaran/langganan berbayar (struktur kuota disiapkan agar mudah ditambah).
