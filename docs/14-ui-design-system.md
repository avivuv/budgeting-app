# 14 — UI Design System

Tema utama: **putih & biru** — bersih, terpercaya (umum dipakai aplikasi keuangan), mudah dibaca semua umur. Dark mode tersedia dengan biru yang sama sebagai aksen.

## 1. Warna

Didefinisikan sebagai CSS variable (format shadcn-vue, HSL) di `resources/css/app.css`, lalu dipakai via Tailwind (`bg-primary`, `text-muted-foreground`, ...). **Jangan hardcode hex di komponen.**

### 1.1 Light (default)
| Token | Hex | Pemakaian |
|---|---|---|
| `--background` | `#FFFFFF` | latar halaman |
| `--surface` / `--muted` | `#F5F8FF` | latar section, card sekunder, hover |
| `--card` | `#FFFFFF` | card (dengan border) |
| `--border` | `#E2E8F0` | garis, border input |
| `--foreground` | `#0F172A` | teks utama |
| `--muted-foreground` | `#64748B` | teks sekunder, label |
| `--primary` | `#2563EB` | tombol utama, link, nav aktif, FAB |
| `--primary-hover` | `#1D4ED8` | hover/pressed |
| `--primary-soft` | `#DBEAFE` | badge, highlight, latar ikon |
| `--primary-foreground` | `#FFFFFF` | teks di atas primary |
| `--ring` | `#93C5FD` | focus ring |

### 1.2 Warna semantik (makna uang)
| Token | Hex | Makna |
|---|---|---|
| `--income` | `#16A34A` | pemasukan, budget aman |
| `--expense` | `#DC2626` | pengeluaran, budget lewat |
| `--transfer` | `#2563EB` | transfer (= primary) |
| `--warning` | `#D97706` | budget waspada (80–100%) |

Angka nominal tidak hanya dibedakan warna: pemasukan diberi `+`, pengeluaran `−`, agar tetap terbaca oleh pengguna buta warna.

### 1.3 Dark
| Token | Hex |
|---|---|
| `--background` | `#0B1220` |
| `--card` / `--muted` | `#111A2E` / `#16213A` |
| `--border` | `#1E2A44` |
| `--foreground` | `#E2E8F0` |
| `--muted-foreground` | `#94A3B8` |
| `--primary` | `#3B82F6` |
| `--primary-soft` | `#1E3A8A` |
| `--income` / `--expense` / `--warning` | `#22C55E` / `#F87171` / `#FBBF24` |

### 1.4 Palet grafik (kategori)
Urutan dipakai bergantian untuk donut/bar kategori; warna kategori yang dipilih user (kolom `categories.color`) diprioritaskan.

`#2563EB` `#0EA5E9` `#14B8A6` `#22C55E` `#F59E0B` `#F97316` `#EF4444` `#EC4899` `#8B5CF6` `#64748B`

Grafik arus kas: pemasukan `--income`, pengeluaran `--expense`, garis selisih `--primary`.

## 2. Tipografi
- Font: **Plus Jakarta Sans** (Google Fonts) — modern, buatan Indonesia; fallback `system-ui`.
- Angka nominal: `font-variant-numeric: tabular-nums` agar kolom rata.

| Peran | Ukuran / berat |
|---|---|
| Angka besar (saldo) | 28–32px / 700 |
| Judul halaman | 20–24px / 700 |
| Judul card | 16px / 600 |
| Body | 14–16px / 400 |
| Label / caption | 12–13px / 500, `muted-foreground` |

## 3. Bentuk & Spasi
- Radius: card `16px` (`rounded-2xl`), tombol & input `12px` (`rounded-xl`), badge `full`.
- Shadow halus: `0 1px 2px rgb(15 23 42 / .06)`; card utama boleh `shadow-sm`, hindari shadow tebal.
- Spasi berbasis 4px; padding card `16px` (HP) / `24px` (desktop); gutter halaman `16px` di HP.
- Touch target minimal `44×44px`.

## 4. Komponen Kunci

| Komponen | Spesifikasi |
|---|---|
| **Header saldo** (dashboard) | Card gradien biru `#2563EB → #1D4ED8`, teks putih: total saldo besar, pemasukan/pengeluaran bulan ini di bawahnya |
| **StatCard** | Card putih, ikon dalam lingkaran `primary-soft`, nilai besar, Δ% dengan panah (hijau/merah) |
| **FAB** | Bulat 56px, `primary`, ikon `+`; tap → pilihan *Catat* / *Scan Struk* |
| **Bottom nav (HP)** | 5 item: Beranda, Transaksi, (FAB di tengah), Laporan, Lainnya; item aktif `primary` |
| **Sidebar (desktop ≥ 1024px)** | Putih, item aktif latar `primary-soft` + teks `primary` |
| **Item transaksi** | Ikon kategori (lingkaran berwarna kategori), nama + catatan, avatar pencatat, ikon sumber (🌐/✈️/🧾), nominal kanan dengan warna semantik |
| **BudgetProgress** | Bar 8px rounded; warna sesuai status BR-F04; teks "Rp 650rb / 800rb · 81%" |
| **Empty state** | Ilustrasi garis biru + ajakan "Kirim `bensin 50rb` ke bot Telegram" |
| **Toast** | Sukses hijau, gagal merah, posisi atas di HP |

## 5. Breakpoint
| Nama | Lebar | Layout |
|---|---|---|
| HP | < 640px | 1 kolom, bottom nav, FAB |
| Tablet | 640–1023px | 2 kolom card, bottom nav |
| Desktop | ≥ 1024px | sidebar kiri, grid 3–4 kolom |

## 6. Aksesibilitas
- Kontras teks ≥ 4.5:1 (primary `#2563EB` di atas putih = 5.2:1 ✓).
- Focus ring terlihat (`--ring`) pada semua elemen interaktif.
- Grafik punya tooltip + tabel alternatif ("Lihat sebagai tabel").
- Ikon selalu disertai label/aria-label.

## 7. Ikon
`lucide-vue-next`, stroke 1.75–2px, ukuran 20px (nav/list) dan 24px (aksi utama).
