# 11 — Business Rules

Aturan bisnis yang **wajib** dipatuhi oleh semua channel (web, REST API, Telegram, struk, recurring). Setiap rule punya ID `BR-xxx` dan **wajib punya minimal satu test** yang mereferensikan ID tersebut (lihat [12-testing-strategy.md](12-testing-strategy.md)).

Rule diimplementasikan di **Service** (bukan controller/handler) agar berlaku sama untuk semua channel.

---

## A. Nominal & Angka

| ID | Rule |
|---|---|
| BR-A01 | Nominal disimpan sebagai integer rupiah (`BIGINT`), tidak pernah float. |
| BR-A02 | Nominal transaksi harus `> 0` dan `≤ 999.999.999.999`. Arah uang ditentukan oleh `type`, bukan tanda nominal. |
| BR-A03 | Parser nominal: `50rb`/`50k`/`50ribu` = 50.000; `1,5jt`/`1.5jt`/`1,5 juta` = 1.500.000; `2jt300` = 2.300.000; `50.000` = 50.000 (titik = pemisah ribuan); `50,5` → ditolak untuk rupiah (tidak ada sen). |
| BR-A04 | Persentase dibulatkan 1 desimal, *half up* (`79,95%` → `80,0%`). Perhitungan memakai integer, pembulatan hanya di tampilan/DTO output. |
| BR-A05 | Perbandingan periode: `Δ% = (sekarang − lalu) / lalu × 100`. Jika `lalu = 0` → `Δ% = null` (ditampilkan "Baru"). |
| BR-A06 | Format tampilan: `Rp 1.250.000`; ringkas di grafik: `1,25 jt`, `850 rb`. |

## B. Transaksi

| ID | Rule |
|---|---|
| BR-B01 | Jenis transaksi: `income`, `expense`, `transfer`. |
| BR-B02 | `income`/`expense` wajib `category_id` dengan `category.type` yang **sama** dengan jenis transaksi. |
| BR-B03 | `transfer` wajib `wallet_id` ≠ `to_wallet_id`, dan `category_id = null`. |
| BR-B04 | Wallet, category, tag harus milik keluarga yang sama dengan transaksi (cek tenant). |
| BR-B05 | Wallet yang diarsipkan tidak bisa dipakai untuk transaksi baru; transaksi lama tetap dihitung. |
| BR-B06 | Tanggal transaksi tidak boleh lebih dari hari ini (zona waktu keluarga) dan tidak lebih dari 5 tahun ke belakang. |
| BR-B07 | Setiap transaksi menyimpan `created_by` (user pencatat) dan `source` (`web`/`api`/`telegram`/`receipt`/`recurring`). |
| BR-B08 | Hapus = soft delete. Transaksi terhapus **tidak** ikut dalam perhitungan apa pun. |
| BR-B09 | Member hanya boleh edit/hapus transaksi miliknya; Owner/Admin boleh semua; Viewer tidak boleh menulis. |
| BR-B10 | `/undo` hanya menghapus transaksi terakhir **milik pengirim**, dibuat ≤ 24 jam lalu, di keluarga aktif. |
| BR-B11 | Transfer **tidak** dihitung sebagai pemasukan maupun pengeluaran di laporan/dashboard; hanya memengaruhi saldo dompet. |
| BR-B12 | Input yang sama lewat web, API, dan Telegram menghasilkan record yang identik (kecuali `source`). |

## C. Saldo Dompet

| ID | Rule |
|---|---|
| BR-C01 | `saldo(wallet, s.d. tanggal T) = initial_balance + Σincome − Σexpense − Σtransfer_keluar + Σtransfer_masuk`, untuk transaksi tidak terhapus dengan `date ≤ T`. |
| BR-C02 | `total_saldo = Σ saldo semua wallet tidak diarsipkan`. Kartu kredit dihitung sebagai saldo negatif (utang). |
| BR-C03 | Saldo boleh negatif; sistem memberi peringatan (bukan menolak) jika transaksi membuat dompet non-kredit negatif. |
| BR-C04 | Cache saldo wajib di-invalidate setiap create/update/delete transaksi yang menyentuh wallet tersebut (termasuk `to_wallet_id` dan wallet lama saat edit). |
| BR-C05 | Wallet dengan transaksi tidak bisa dihapus permanen, hanya diarsipkan. |

## D. Periode

| ID | Rule |
|---|---|
| BR-D01 | Periode bulanan dimulai pada `families.period_start_day` (1–28). Contoh `start_day = 25`: periode "Nov 2026" = 25 Okt 2026 s.d. 24 Nov 2026. |
| BR-D02 | Periode diberi label berdasarkan bulan tempat **tanggal akhir** periode berada. |
| BR-D03 | "Hari ini", "minggu ini" (Senin–Minggu), "bulan ini" dihitung di zona waktu keluarga. |
| BR-D04 | Rentang tanggal laporan bersifat inklusif di kedua ujung. |

## E. Kategori

| ID | Rule |
|---|---|
| BR-E01 | Kedalaman maksimal 2 level (kategori → sub-kategori). Sub-kategori harus punya `type` sama dengan induknya. |
| BR-E02 | Nama kategori unik per `(family, type, parent)`. |
| BR-E03 | Kategori yang sudah dipakai transaksi tidak bisa dihapus; pilihannya diarsipkan atau **digabung** (semua transaksi dipindah ke kategori lain, lalu dihapus). |
| BR-E04 | Total kategori induk di laporan = total transaksi langsung di induk + Σ total sub-kategori. |
| BR-E05 | Tebakan kategori dari teks: eksplisit `!nama` > keyword > riwayat keluarga > tanya user. Keyword dicocokkan per kata utuh, case-insensitive. |

## F. Budget

| ID | Rule |
|---|---|
| BR-F01 | Budget per `(family, category, period)` unik; hanya untuk kategori `expense`. |
| BR-F02 | Realisasi = Σ `expense` di kategori tersebut **termasuk sub-kategori** dalam periode (BR-D01). |
| BR-F03 | `sisa = budget − realisasi` (boleh negatif); `persen = realisasi / budget × 100`. |
| BR-F04 | Status: `aman` < 80%, `waspada` 80–<100%, `lewat` ≥ 100%. |
| BR-F05 | Notifikasi 80% dan 100% masing-masing dikirim **maksimal sekali per periode**. Jika realisasi turun di bawah ambang (transaksi dihapus/diedit), flag di-reset sehingga bisa terkirim lagi. |
| BR-F06 | Sisa budget harian = `max(sisa, 0) / jumlah hari tersisa di periode (termasuk hari ini)`, dibulatkan ke bawah. |
| BR-F07 | "Salin dari bulan lalu" hanya membuat budget untuk kategori yang belum punya budget di periode tujuan. |

## G. Transaksi Berulang

| ID | Rule |
|---|---|
| BR-G01 | `day_of_month` > jumlah hari bulan berjalan → dijalankan di hari terakhir bulan itu (31 → 28/29 Feb). |
| BR-G02 | Scheduler idempoten: satu rule tidak boleh membuat dua transaksi untuk tanggal jatuh tempo yang sama. |
| BR-G03 | `auto_create = false` → hanya pengingat; transaksi dibuat setelah user menekan "Sudah dibayar". |
| BR-G04 | Rule berhenti setelah `end_at`; rule pada wallet/kategori yang diarsipkan dijeda dan pemilik diberi tahu. |

## H. Struk

| ID | Rule |
|---|---|
| BR-H01 | Nominal transaksi dari struk = `total` yang dibayar (setelah diskon & pajak), bukan uang tunai/kembalian. |
| BR-H02 | "Pecah per item": transaksi per kategori; diskon & pajak dialokasikan **proporsional** terhadap subtotal tiap kategori, sisa pembulatan diberikan ke kategori dengan subtotal terbesar, sehingga Σ transaksi = `total` **persis**. |
| BR-H03 | Jika Σ item ± diskon/pajak berselisih > 2% dari total → confidence diturunkan dan preview menampilkan peringatan; nominal tetap memakai `total`. |
| BR-H04 | File identik (sha256 sama) di keluarga yang sama ditolak. Merchant + tanggal + total sama → peringatan "kemungkinan duplikat", user tetap boleh simpan. |
| BR-H05 | Kuota dihitung saat upload diterima. Kuota dikembalikan hanya jika gagal karena error sistem/AI (bukan "bukan struk"). |
| BR-H06 | Tanggal struk tidak terbaca, di masa depan, atau > 1 tahun lalu → memakai hari ini dan ditandai. |
| BR-H07 | Satu struk hanya bisa dikonfirmasi sekali (`confirmed`); tombol konfirmasi kedua diabaikan. |
| BR-H08 | **Hasil baca struk tidak pernah langsung menjadi transaksi.** Hasil AI hanya disimpan sebagai draft di tabel `receipts` (status `parsed`) lalu dikirim balik ke user untuk ditinjau. Transaksi baru dibuat setelah user menekan **Simpan**. Setting `auto_save` Telegram tidak berlaku untuk struk. |
| BR-H09 | Sebelum konfirmasi, user bisa mengoreksi hasil baca: total, tanggal, merchant, kategori, dompet, dan item. Nilai yang disimpan adalah hasil koreksi user, sedangkan hasil asli AI tetap tersimpan di `raw_response` untuk audit. |
| BR-H10 | Draft struk yang tidak dikonfirmasi dalam 24 jam berstatus `expired`, tidak membuat transaksi apa pun, dan filenya dihapus setelah 7 hari. |

## I. Keluarga, Anggota & Role

| ID | Rule |
|---|---|
| BR-I01 | Setiap keluarga punya tepat **satu** Owner. Owner tidak bisa keluar sebelum mentransfer kepemilikan. |
| BR-I02 | Undangan berlaku 7 hari, sekali pakai, dan hanya bisa diterima oleh email yang diundang. |
| BR-I03 | Admin tidak bisa mengubah role/mengeluarkan Owner atau Admin lain; hanya Owner yang bisa. |
| BR-I04 | Anggota yang keluar tetap tercatat sebagai `created_by` pada transaksi lamanya. |
| BR-I05 | Keluarga yang di-suspend: web & bot menolak semua aksi tulis dan baca (kecuali Super Admin). |
| BR-I06 | Data keluarga lain tidak pernah bisa diakses; akses ID milik keluarga lain menghasilkan 404 (bukan 403) agar tidak membocorkan keberadaan data. |

## J. Telegram

| ID | Rule |
|---|---|
| BR-J01 | Kode linking berlaku 15 menit dan sekali pakai. |
| BR-J02 | Satu akun Telegram hanya terhubung ke satu user; satu grup hanya terhubung ke satu keluarga. |
| BR-J03 | Hanya Owner/Admin yang bisa `/hubungkan` grup. |
| BR-J04 | Di grup, transaksi dicatat atas nama **pengirim pesan**; pengirim yang belum terhubung atau bukan anggota keluarga ditolak. |
| BR-J05 | Tombol konfirmasi hanya bisa ditekan oleh pengirim asli; draft kedaluwarsa setelah 10 menit. |
| BR-J06 | Di grup, bot hanya memproses `/perintah`, mention, atau reply ke bot (tidak membaca obrolan biasa). |
| BR-J07 | `/saldo` di grup mengikuti setting `group_show_balance`. |
