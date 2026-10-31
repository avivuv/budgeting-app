# 07 — Dashboard & Laporan

## 1. Prinsip UI
- **Mobile-first** (360px), bottom navigation di HP, sidebar di desktop.
- Tombol aksi cepat **"+ Transaksi"** dan **"📷 Scan Struk"** selalu terlihat (FAB).
- Angka besar & jelas, warna konsisten: hijau = pemasukan, merah = pengeluaran, biru = transfer.
- Dark mode mengikuti sistem.
- Format: `Rp 1.250.000`, singkat di grafik `1,25 jt`; tanggal `31 Okt 2026`.
- Semua angka bisa diklik → drill-down ke daftar transaksi terfilter.
- PWA installable (`vite-plugin-pwa`).

## 2. Navigasi

| Menu | Halaman |
|---|---|
| Dashboard | Ringkasan |
| Transaksi | Daftar, filter, tambah/edit, scan struk |
| Laporan | Arus kas, kategori, anggota, dompet, budget, tahunan |
| Budget | Atur & pantau budget, tujuan tabungan |
| Pengaturan | Keluarga, anggota & undangan, dompet, kategori, transaksi berulang, Telegram, profil |

## 3. Dashboard

Filter global di atas: **periode** (bulan ini default, mengikuti `period_start_day`), dompet, anggota.

| Widget | Isi | Komponen |
|---|---|---|
| Kartu ringkasan | Total saldo · Pemasukan · Pengeluaran · Selisih (dengan % vs periode lalu) | `StatCard` |
| Arus kas 6–12 bulan | Bar pemasukan vs pengeluaran + garis selisih | ApexCharts bar+line |
| Pengeluaran per kategori | Donut + legend dengan nominal & % | ApexCharts donut |
| Budget | Progress bar per kategori (hijau < 80%, kuning 80–100%, merah > 100%) | `BudgetProgress` |
| Tren harian | Area chart pengeluaran kumulatif vs rata-rata bulan lalu | ApexCharts area |
| Per anggota | Bar horizontal siapa mencatat/mengeluarkan berapa | ApexCharts bar |
| Saldo dompet | List dompet + saldo | list |
| Transaksi terbaru | 10 terakhir: ikon kategori, nama, pencatat (avatar), sumber (🌐/✈️/🧾) | list |
| Tujuan tabungan | Progress tiap goal | card |
| Insight singkat | "Pengeluaran makan naik 25% dari bulan lalu", "Sisa budget harian: Rp 85.000" | teks |

Data dashboard berasal dari `DashboardService`, di-cache per `(family, periode, filter)` 5 menit dan di-invalidate saat ada transaksi baru.

## 4. Daftar Transaksi
- Dikelompokkan per tanggal dengan subtotal harian.
- Filter: rentang tanggal, jenis, kategori (multi), dompet, anggota, tag, sumber, nominal min/max, pencarian catatan/merchant.
- Filter disimpan di query string (bisa dibagikan/bookmark).
- Aksi massal: ubah kategori, hapus.
- Thumbnail struk bila ada.

## 5. Laporan

| Laporan | Isi | Visual |
|---|---|---|
| **Arus Kas** | Pemasukan, pengeluaran, selisih per bulan; saldo awal–akhir | bar + tabel |
| **Kategori** | Total per kategori & sub-kategori, % porsi, perbandingan periode lalu, rata-rata 3 bulan | donut, treemap, tabel |
| **Anggota** | Pengeluaran & pencatatan per anggota | bar + tabel |
| **Dompet** | Mutasi per dompet: saldo awal, masuk, keluar, transfer, saldo akhir | tabel |
| **Budget vs Realisasi** | Budget, realisasi, sisa, % per kategori | bullet/progress + tabel |
| **Tahunan** | Heatmap 12 bulan × kategori, total tahun, bulan terboros | heatmap + tabel |
| **Merchant** | Toko paling sering/terbesar (dari struk & catatan) | tabel |
| **Tag** | Total per tag (mis. biaya liburan) | tabel |

Semua laporan: filter periode bebas, tombol **Export PDF** dan **Export Excel**.

### 5.1 Export
- **Excel** (`maatwebsite/excel`): sheet Ringkasan, Transaksi, Kategori, Budget.
- **PDF** (`dompdf`): header keluarga & periode, kartu ringkasan, grafik (gambar statis, dibuat via QuickChart/SVG server-side), tabel.
- Export besar → `GenerateReportJob`, hasil dikirim sebagai notifikasi web + Telegram.

## 6. Layer
- `ReportService` → memanggil `TransactionRepository` (agregasi `SUM ... GROUP BY`) dan `BudgetRepository`; tidak ada query di controller.
- Output berupa DTO (`CashflowReportData`, `CategoryReportData`, ...) yang dipakai **bersama** oleh halaman web, export, dan perintah bot (`/bulan`, `/laporan`).

## 7. Komponen Vue Utama
Mengikuti struktur modul di [15-frontend-architecture.md](15-frontend-architecture.md):
```
core/components/charts/   BaseChart, BarChart, DonutChart, AreaChart, Heatmap
core/layouts/partials/    Sidebar, BottomNav, Fab, FamilySwitcher
modules/dashboard/        StatCard, BalanceHeader, CashflowChart, CategoryDonut, TrendArea, MemberBar, InsightCard
modules/budgets/          BudgetProgress (diekspor via index.ts)
modules/transactions/     TransactionList, TransactionItem, TransactionForm, TransactionFilterBar
modules/receipts/         ReceiptScanner, ReceiptPreview
modules/reports/          ReportFilter, ExportMenu, YearHeatmap
```
Widget dashboard yang berat dikirim sebagai **deferred props** sehingga kartu ringkasan tampil lebih dulu.
