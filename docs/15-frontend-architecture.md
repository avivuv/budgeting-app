# 15 — Frontend Architecture (Vue 3 + Inertia)

## 1. Prinsip

1. **Modular per fitur (feature-based)** — kode dikelompokkan per domain (`transactions`, `budgets`, ...), bukan per jenis file. Fitur baru = folder modul baru, tanpa menyentuh modul lain.
2. **Server adalah sumber kebenaran** — semua perhitungan (saldo, total, persentase, status budget) dihitung di backend (Service) dan dikirim sebagai props. Frontend **hanya memformat & menampilkan**. Ini menjamin angka di web sama dengan API, bot, dan export (INV-02/03 di [12-testing-strategy.md](12-testing-strategy.md)).
3. **Layer jelas** — Page (container) → Feature component (presentational) → UI primitive.
4. **Type-safe end-to-end** — tipe TypeScript di-generate dari DTO/Resource PHP, bukan ditulis ulang manual.
5. **Batas import tegas** — modul hanya boleh memakai `core` dan *public API* modul lain; dijaga ESLint.

## 2. Struktur Folder

```
resources/js/
├── app.ts                       # bootstrap Inertia, plugin, page resolver
├── core/                        # shared, tanpa logika domain
│   ├── components/
│   │   ├── ui/                  # shadcn-vue primitives (Button, Card, Dialog, Input, ...)
│   │   ├── common/              # Money, DateText, EmptyState, ConfirmDialog, PageHeader, Avatar
│   │   ├── charts/              # BaseChart (ApexCharts wrapper + theme token), BarChart, DonutChart, AreaChart, Heatmap
│   │   └── form/                # FormField, AmountInput, DatePicker, SelectSearch, FileDrop
│   ├── layouts/                 # AppLayout, AuthLayout, GuestLayout, partials (Sidebar, BottomNav, Fab, FamilySwitcher)
│   ├── composables/             # useMoney, useDate, usePermission, useFlash, useFilters, useBreakpoint, useTheme
│   ├── lib/                     # formatter murni (money.ts, date.ts), http.ts (axios utk endpoint non-Inertia), cn.ts
│   ├── plugins/                 # i18n, ziggy, apexcharts, pwa
│   ├── i18n/id.json
│   └── types/
│       ├── generated.d.ts       # HASIL GENERATE dari PHP — jangan diedit manual
│       ├── inertia.d.ts         # SharedProps (auth, family, can, flash)
│       └── global.d.ts
│
└── modules/
    ├── auth/
    ├── onboarding/
    ├── dashboard/
    ├── transactions/
    ├── receipts/
    ├── wallets/
    ├── categories/
    ├── budgets/
    ├── savings/
    ├── recurring/
    ├── reports/
    ├── family/                  # anggota, undangan, pengaturan keluarga
    ├── telegram/                # linking akun & grup
    ├── settings/                # profil, password, API token, tampilan
    └── admin/                   # super admin
```

### 2.1 Anatomi satu modul
```
modules/transactions/
├── pages/                       # di-render oleh Inertia (container)
│   ├── Index.vue
│   ├── Create.vue
│   └── Edit.vue
├── components/                  # presentational, khusus modul ini
│   ├── TransactionList.vue
│   ├── TransactionItem.vue
│   ├── TransactionForm.vue
│   └── TransactionFilterBar.vue
├── composables/                 # logika UI modul
│   ├── useTransactionForm.ts
│   └── useTransactionFilters.ts
├── constants.ts                 # opsi enum untuk select, ikon sumber, dll
├── types.ts                     # tipe lokal (re-export dari generated bila perlu)
├── __tests__/                   # Vitest
└── index.ts                     # PUBLIC API modul (komponen/composable yang boleh dipakai modul lain)
```

Contoh: `dashboard` memakai `TransactionItem` → harus lewat `@/modules/transactions` (index.ts), bukan `@/modules/transactions/components/TransactionItem.vue`.

## 3. Layer & Tanggung Jawab

| Layer | Tugas | Tidak boleh |
|---|---|---|
| **Page** (`modules/*/pages`) | Terima props Inertia, pilih layout, susun komponen, hubungkan event ke `router`/`useForm` | Markup detail berulang, hitung angka bisnis |
| **Feature component** (`modules/*/components`) | Tampilkan data via `props`, kirim aksi via `emit` | Akses `router`/`usePage` langsung (kecuali komponen form khusus) |
| **Composable** | State & perilaku yang dapat dipakai ulang (filter ↔ query string, form, polling) | Markup |
| **Core UI** | Primitive generik tanpa pengetahuan domain | Import dari `modules/*` |
| **lib** | Fungsi murni (format uang/tanggal) | Side effect, state |

## 4. Page Resolver & Penamaan

Backend: `Inertia::render('transactions/Index', [...])` → `modules/transactions/pages/Index.vue`.

```ts
// app.ts
createInertiaApp({
  title: (title) => (title ? `${title} · Budgeting` : 'Budgeting'),
  resolve: (name) => {
    const [module, ...page] = name.split('/')
    const pages = import.meta.glob<DefineComponent>('./modules/*/pages/**/*.vue')
    return resolvePageComponent(`./modules/${module}/pages/${page.join('/')}.vue`, pages)
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin).use(ZiggyVue).use(i18n)
      .mount(el)
  },
  progress: { color: '#2563EB' },
})
```

- Halaman di-*lazy load* per modul (code splitting otomatis via `import.meta.glob`).
- Layout default dipasang per page: `defineOptions({ layout: AppLayout })`.

## 5. State & Data

| Jenis data | Sumber | Alat |
|---|---|---|
| Data halaman (list, detail, ringkasan) | Props Inertia dari controller | `defineProps<...>()` |
| Data global (user, keluarga aktif, role, izin, flash) | Shared props `HandleInertiaRequests` | `usePage<SharedProps>()` via `useAuth()`, `usePermission()` |
| Widget berat (grafik dashboard, laporan) | **Deferred props** Inertia v2 | `<Deferred data="cashflow">` + skeleton |
| Status async (scan struk) | Polling | `usePoll()` Inertia v2 / partial reload `only: ['receipt']` |
| List panjang (transaksi) | Pagination / `WhenVisible` infinite scroll | Inertia v2 |
| Filter | Query string | `useFilters()` → `router.get(url, filters, { preserveState, replace })` |
| Preferensi UI (tema, sidebar, tab terakhir) | Lokal | **Pinia** + persist `localStorage` |
| Form | — | Inertia `useForm()` (error validasi dari server otomatis) |

**Pinia hanya untuk state UI lintas halaman**, bukan untuk cache data server. Ini mencegah data basi dan duplikasi sumber kebenaran.

### 5.1 Shared props
```ts
// core/types/inertia.d.ts
export interface SharedProps {
  auth: { user: App.Data.UserData | null }
  family: App.Data.CurrentFamilyData | null      // id, name, currency, timezone, role
  families: App.Data.FamilyOptionData[]           // untuk switcher
  can: Record<App.Enums.Ability, boolean>          // dari Policy backend
  flash: { success?: string; error?: string }
  quota: { receipt_remaining: number } | null
}
```

`usePermission()` hanya membaca `can` dari server — frontend **tidak** menentukan izin sendiri (cukup menyembunyikan tombol; backend tetap memvalidasi).

## 6. Type Safety

- **`spatie/laravel-typescript-transformer`** membaca DTO (`#[TypeScript]`), API Resource data class, dan Enum PHP → `core/types/generated.d.ts` (namespace `App.Data.*`, `App.Enums.*`).
- Perintah: `php artisan typescript:transform` — dijalankan di `composer run dev` dan CI (CI gagal bila file generated berbeda → tipe frontend selalu sinkron dengan backend).
- Route: **Ziggy** `route('transactions.index')` dengan tipe ter-generate.
- `vue-tsc --noEmit` wajib bersih.

## 6b. List Data / Tabel

**Tidak memakai jQuery DataTables.** Library itu memanipulasi DOM langsung sehingga bentrok dengan Vue, memproses data di browser (lambat untuk ribuan transaksi), dan sulit diberi tema. Penggantinya:

| Bagian | Pilihan |
|---|---|
| Engine tabel | **TanStack Table v8** (`@tanstack/vue-table`) — headless, dipakai oleh komponen *Data Table* shadcn-vue, tampilan 100% Tailwind |
| Data | **Server-side**: pagination, sort, filter, search dikerjakan di backend (Repository `paginateFiltered`), frontend hanya mengirim query string |
| Komponen | `core/components/data-table/` — `DataTable.vue`, `DataTablePagination.vue`, `DataTableToolbar.vue` (search, filter chips, reset), `DataTableColumnHeader.vue` (sort), `DataTableViewOptions.vue` (show/hide kolom), `DataTableRowActions.vue` |
| State | `useDataTable()` → sinkron ke query string (`?page=2&sort=-date&type=expense&q=bensin`) via `router.get(..., { preserveState: true, preserveScroll: true, replace: true })`, search di-debounce 300ms |

### Kontrak backend ↔ frontend
- Query: `page`, `per_page` (10/20/50/100, default 20), `sort` (`date`, `-date`, `amount`, `-amount`; whitelist per resource), `q`, dan filter spesifik.
- Response: `LengthAwarePaginator` dari Repository → `Data::collect()` → `{ data, meta: { current_page, last_page, per_page, total }, links }` — **format sama untuk Inertia dan REST API**.
- Total/subtotal (mis. "Total pengeluaran sesuai filter: Rp 2.287.500") dihitung backend dan dikirim sebagai prop terpisah `aggregates`, bukan dijumlah di browser (INV-06).
- Kolom sort & filter di-whitelist di `FormRequest` (cegah SQL injection & query berat).

### Tampilan per layar
| Layar | Bentuk |
|---|---|
| Desktop ≥ 1024px | Tabel penuh: kolom bisa disort, sembunyikan kolom, aksi baris, **pilih banyak baris** untuk aksi massal (ubah kategori, hapus) |
| HP / tablet | **List kartu** dikelompokkan per tanggal (subtotal harian) + **infinite scroll** (`WhenVisible` Inertia v2); filter di bottom sheet |

Satu sumber data, dua renderer: `DataTable` (desktop) dan `DataList` (mobile) memakai `useDataTable()` yang sama.

### Dipakai di
Transaksi (utama), struk, dompet, kategori, anggota & undangan, transaksi berulang, laporan (tabel detail), admin (daftar keluarga). Daftar pendek (< 50 baris, mis. dompet) tetap memakai komponen yang sama tanpa pagination.

### Export
Tombol Export di toolbar memakai **filter yang sedang aktif** dan memanggil endpoint backend (Excel/PDF); tidak export dari data di browser.

## 7. Form & Validasi

```ts
// modules/transactions/composables/useTransactionForm.ts
export function useTransactionForm(initial?: App.Data.TransactionData) {
  const form = useForm({
    type: initial?.type ?? 'expense',
    amount: initial?.amount ?? null,
    date: initial?.date ?? today(),
    wallet_id: initial?.wallet_id ?? null,
    category_id: initial?.category_id ?? null,
    note: initial?.note ?? '',
    tags: initial?.tags ?? [],
  })

  const submit = () =>
    initial
      ? form.put(route('transactions.update', initial.id), { preserveScroll: true })
      : form.post(route('transactions.store'), { onSuccess: () => form.reset('amount', 'note') })

  return { form, submit }
}
```

- Validasi utama di **backend** (FormRequest, satu sumber dengan API). Frontend hanya validasi ringan (wajib isi, format) untuk UX.
- `AmountInput` menampilkan `Rp 50.000` tetapi mengirim integer `50000`; menerima ketikan `50rb` lalu diparse oleh endpoint `/transactions/parse` (pakai `AmountParser` yang sama dengan bot) — tidak ada parser duplikat di JS.

## 8. Komponen & Tema

- Primitive dari **shadcn-vue** di `core/components/ui` (dikopi ke repo, bebas dimodifikasi).
- Semua warna lewat token CSS ([14-ui-design-system.md](14-ui-design-system.md)); `BaseChart` membaca token agar grafik ikut light/dark.
- Komponen ≤ ~200 baris; lebih dari itu dipecah.
- Props diberi tipe (`defineProps<{ ... }>()`), event (`defineEmits<{ ... }>()`), slot didokumentasikan bila ada.
- Naming: PascalCase multi-word; komponen modul diberi prefix domain (`TransactionItem`, `BudgetProgress`).

## 9. Aturan Import (ESLint)

| Dari | Boleh import |
|---|---|
| `core/**` | `core/**` saja |
| `modules/X/**` | `core/**`, `modules/X/**`, `@/modules/Y` (index.ts saja) |
| `app.ts` | semua |

Ditegakkan dengan `eslint-plugin-boundaries`. Alias: `@/core`, `@/modules`.

## 10. Kualitas & Testing

| Alat | Fungsi |
|---|---|
| ESLint (`@vue/eslint-config-typescript`) + Prettier | lint & format |
| `vue-tsc` | type check |
| **Vitest** + `@vue/test-utils` | unit test `lib/*`, composables, komponen penting (`AmountInput`, `BudgetProgress`, `TransactionFilterBar`) |
| Playwright *(fase lanjutan)* | E2E alur utama: login → catat transaksi → dashboard ter-update |

Test frontend fokus pada **format & interaksi**, karena perhitungan sudah diuji di backend.

## 11. Performa & PWA

- Lazy load per halaman (otomatis), ApexCharts di-import dinamis di `BaseChart`.
- Deferred props untuk widget berat; skeleton loading.
- Prefetch link navigasi utama (`<Link prefetch>`).
- `vite-plugin-pwa`: installable, cache aset statis, halaman offline sederhana. Input transaksi offline **tidak** didukung (Telegram sudah mencakup kebutuhan input cepat).

## 12. Checklist Fitur Baru (Frontend)
1. Buat folder `modules/<fitur>/` dengan `pages/`, `components/`, `index.ts`.
2. Controller `Inertia::render('<fitur>/<Page>')` dengan props dari DTO ber-`#[TypeScript]`.
3. Jalankan `typescript:transform`.
4. Gunakan komponen `core` sebelum membuat yang baru.
5. Tambah route ke navigasi (`core/layouts/partials/navigation.ts`) dengan permission.
6. Vitest untuk composable/komponen baru; lint & type check bersih.
