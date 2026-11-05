# 15 — Frontend Architecture (Vue 3 SPA)

Web adalah **SPA terpisah** di folder `frontend/` yang hanya berbicara dengan backend lewat **REST API `/api/v1`** ([13-rest-api.md](13-rest-api.md)), sama seperti aplikasi Android. Status migrasi dari versi lama: [18-api-first-migration.md](18-api-first-migration.md).

## 1. Prinsip

1. **API adalah satu-satunya pintu** — SPA tidak punya akses lain ke backend selain `/api/v1` (+ `/sanctum/csrf-cookie`). Apa pun yang bisa dilakukan di web harus bisa dilakukan Android lewat endpoint yang sama.
2. **Server adalah sumber kebenaran** — semua perhitungan (saldo, total, persentase, status budget) dihitung di backend (Service) dan dikirim dalam response API. Frontend **hanya memformat & menampilkan**. Ini menjamin angka di web sama dengan Android, bot, dan export (INV-02/03 di [12-testing-strategy.md](12-testing-strategy.md)).
3. **Modular per fitur** — kode dikelompokkan per domain (`transactions`, `budgets`, ...), bukan per jenis file. Fitur baru = folder modul baru.
4. **Layer jelas** — Page (container) → Feature component (presentational) → UI primitive.
5. **Type-safe end-to-end** — tipe request/response di-generate dari **OpenAPI** backend, tidak ditulis ulang manual.
6. **Batas import tegas** — modul hanya boleh memakai `core` dan _public API_ modul lain.

## 2. Stack

| Kebutuhan   | Pilihan                                                                                                                           |
| ----------- | --------------------------------------------------------------------------------------------------------------------------------- |
| Framework   | Vue 3 + TypeScript (`<script setup>`), Vite+                                                                                      |
| Routing     | **Vue Router** (lazy load per halaman, guard auth & keluarga)                                                                     |
| Data server | **TanStack Query** (`@tanstack/vue-query`) — cache, refetch, polling, infinite list, invalidasi setelah mutasi                    |
| HTTP        | `ofetch`/`fetch` wrapper di `core/api/client.ts` (cookie + CSRF, `X-Family-Id`, `Idempotency-Key`, error 401/403/404/409/422/429) |
| Tipe API    | `openapi-typescript` → `core/api/schema.d.ts` dari spec Scramble                                                                  |
| State lokal | **Pinia** — sesi (user, keluarga aktif, izin) & preferensi UI; **bukan** cache data server                                        |
| UI          | Tailwind 4 + shadcn-vue (reka-ui), `@lucide/vue`, ApexCharts (`BaseChart`), `@vueuse/core`                                        |
| Tabel       | TanStack Table (headless) bila perlu show/hide kolom; data selalu server-side                                                     |
| Test        | Vitest + `@vue/test-utils`; Playwright E2E                                                                                        |

## 3. Struktur Folder

```
frontend/
├── index.html
├── vite.config.ts               # proxy /api & /sanctum → backend saat dev; build ke ../public/spa (halaman di /app selama migrasi)
├── public/                      # manifest.webmanifest, sw.js, offline.html, ikon
└── src/
    ├── main.ts                  # createApp + router + pinia + vue-query
    ├── router/                  # routes.ts (gabungan routes tiap modul), guards.ts
    ├── core/                    # shared, tanpa logika domain
    │   ├── api/                 # client.ts, schema.d.ts (GENERATED), errors.ts, useApiForm.ts
    │   ├── components/
    │   │   ├── ui/              # shadcn-vue primitives
    │   │   ├── common/          # Money, DateText, EmptyState, ConfirmDialog, PageHeader, SearchableSelect
    │   │   ├── charts/          # BaseChart + Bar/Donut/Area/Heatmap
    │   │   └── form/            # FormField, AmountInput, DatePicker, FileDrop
    │   ├── layouts/             # AppLayout (sidebar, bottom nav, FAB, FamilySwitcher), AuthLayout
    │   ├── composables/         # useMoney, useDate, usePermission, useToast, useFilters, useBreakpoint, useTheme
    │   ├── stores/              # session.ts (user, families, currentFamily, can), ui.ts
    │   └── lib/                 # formatter murni, cn.ts, pwa.ts
    └── modules/
        ├── auth/                # login, register, 2FA, passkey, lupa/reset password, verifikasi email
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
        ├── family/              # anggota, undangan, pengaturan keluarga
        ├── notifications/
        ├── telegram/            # linking akun & grup
        ├── settings/            # profil, keamanan, API token, tampilan
        └── admin/               # super admin
```

### 3.1 Anatomi satu modul

```
modules/transactions/
├── routes.ts                    # route modul (path, name, meta: { auth, permission, title })
├── api.ts                       # query & mutation TanStack untuk endpoint modul ini
├── pages/                       # container: Index.vue, Create.vue, Edit.vue
├── components/                  # presentational: TransactionList, TransactionItem, TransactionForm, ...
├── composables/                 # logika UI modul (useTransactionFilters, ...)
├── constants.ts
├── __tests__/
└── index.ts                     # PUBLIC API modul
```

Contoh: `dashboard` memakai `TransactionItem` → harus lewat `@/modules/transactions` (index.ts).

## 4. Layer & Tanggung Jawab

| Layer                 | Tugas                                                                          | Tidak boleh                                           |
| --------------------- | ------------------------------------------------------------------------------ | ----------------------------------------------------- |
| **Page**              | Ambil data via query modul, susun komponen, hubungkan event ke mutation/router | Hitung angka bisnis, `fetch` langsung                 |
| **Feature component** | Tampilkan data via `props`, kirim aksi via `emit`                              | Memanggil API / router langsung (kecuali form khusus) |
| **Module `api.ts`**   | Satu-satunya tempat modul memanggil `core/api/client`; query key, invalidasi   | Markup                                                |
| **Composable**        | State & perilaku UI yang dapat dipakai ulang                                   | Markup                                                |
| **Core**              | Primitive generik, client API, sesi                                            | Import dari `modules/*`                               |

## 5. Data & State

| Jenis data                        | Sumber               | Alat                                                                                  |
| --------------------------------- | -------------------- | ------------------------------------------------------------------------------------- |
| List, detail, ringkasan, laporan  | Endpoint API         | `useQuery` (query key memuat `familyId` + filter)                                     |
| Simpan / ubah / hapus             | Endpoint API         | `useMutation` → invalidasi query terkait (mis. transaksi → dompet, dashboard, budget) |
| Sesi: user, keluarga, peran, izin | `GET /api/v1/me`     | Pinia `session` (dimuat saat start & setelah login/switch keluarga)                   |
| Widget berat (dashboard, laporan) | Endpoint per widget  | query paralel + skeleton                                                              |
| Status async (scan struk)         | `GET /receipts/{id}` | `refetchInterval` selama status membaca                                               |
| List panjang (transaksi)          | Pagination API       | desktop: halaman; HP: `useInfiniteQuery`                                              |
| Filter                            | Query string URL     | `useFilters()` ↔ `router.replace({ query })` → masuk query key                        |
| Preferensi UI                     | Lokal                | Pinia + `localStorage`                                                                |

**Pinia tidak menyimpan data server** selain sesi; data selalu lewat TanStack Query agar tidak basi.

### 5.1 Client API

```ts
// core/api/client.ts (ringkas)
export const api = $fetch.create({
    baseURL: '/api/v1',
    credentials: 'include', // cookie Sanctum
    onRequest({ options }) {
        options.headers.set('Accept', 'application/json');
        options.headers.set('X-XSRF-TOKEN', readCookie('XSRF-TOKEN'));
        const family = useSession().currentFamilyId;
        if (family) options.headers.set('X-Family-Id', String(family));
    },
    onResponseError({ response }) {
        // 401 → ke /login · 403 → toast "tidak berhak" · 404 → halaman tidak ditemukan
        // 409 (belum punya keluarga) → /onboarding · 422 → ApiValidationError (errors per field) · 429 → toast
    },
});
```

- `POST` pembuat data otomatis mengirim `Idempotency-Key` (UUID per submit) — klik ganda tidak menggandakan data (BR-B13).
- Login SPA: `GET /sanctum/csrf-cookie` → `POST /api/v1/auth/login` → `GET /api/v1/me`.

### 5.2 Izin

`usePermission()` membaca `can` dari `GET /me` (dihitung Policy backend). Frontend hanya menyembunyikan tombol; backend tetap memvalidasi setiap request.

## 6. Type Safety

- Backend menghasilkan **OpenAPI** (Scramble) dari FormRequest + API Resource; `pnpm run api:types` → `core/api/schema.d.ts`. CI gagal bila file generated tidak sinkron dengan spec.
- Komponen memakai tipe dari schema (`components['schemas']['Transaction']`), tidak menulis interface sendiri.
- Route frontend diberi `name`; navigasi memakai `router.push({ name: 'transactions.edit', params: { id } })`.
- `vue-tsc --noEmit` wajib bersih.

## 7. List Data / Tabel

**Tidak memakai jQuery DataTables** (bentrok dengan Vue, proses di browser).

- **Server-side**: pagination, sort, filter, search dikerjakan backend (Repository `paginateFiltered`); frontend hanya mengirim query.
- Query: `page`, `per_page` (1–100, UI 10/20/50/100, default 20), `sort` (whitelist per resource), `q`, filter spesifik. Transaksi: `from`, `to`, `type`, `category_id[]`, `wallet_id[]`, `user_id[]`, `tag[]`, `source`, `min`, `max` (boleh `50rb`).
- Response: `{ data, meta: { current_page, last_page, per_page, total, ... }, links }`.
- Total/subtotal (mis. total sesuai filter, subtotal harian) dihitung backend dan dikirim di `meta`, tidak dijumlah di browser (INV-06).

| Layar            | Bentuk                                                                        |
| ---------------- | ----------------------------------------------------------------------------- |
| Desktop ≥ 1024px | Tabel: sort, aksi baris, pilih banyak baris untuk aksi massal                 |
| HP / tablet      | Kartu per tanggal (subtotal harian) + infinite scroll; filter di bottom sheet |

Export memakai **filter aktif** dan mengunduh file dari endpoint export API; tidak export dari data di browser.

## 8. Pilihan (Select / Combobox)

| Sumber pilihan                                                | Komponen                                                                                                                            |
| ------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| **Master data** (dompet, kategori, anggota, tag, merchant, …) | **`SearchableSelect`** (reka-ui Combobox) — wajib bisa dicari; grup, indentasi sub-item, `keywords` (ketik "bensin" → Transportasi) |
| Enum kecil & tetap (≤ ~7 opsi)                                | `NativeSelect`                                                                                                                      |

- Saat edit, nilai lama yang sudah diarsipkan tetap tampil sebagai "(diarsipkan)".
- Opsi kategori lewat `categoryOptions()` dari `@/modules/categories`.

## 9. Form & Validasi

```ts
// modules/transactions/pages/Create.vue (ringkas)
const form = useApiForm({
    type: 'expense',
    amount: '',
    date: today(),
    wallet_id: null,
    category_id: null,
    note: '',
    tags: [],
});
const create = useCreateTransaction(); // dari modules/transactions/api.ts

async function submit() {
    await form.submit((values) => create.mutateAsync(values)); // 422 → form.errors per field
    toast.success('Transaksi tersimpan');
    router.push({ name: 'transactions.index' });
}
```

- `useApiForm` (core) menyimpan nilai, `processing`, dan `errors` dari response 422 (termasuk pelanggaran business rule yang punya `field`).
- Validasi utama di **backend** (FormRequest). Frontend hanya validasi ringan untuk UX.
- `AmountInput` menerima ketikan `50rb` dan mengirim teks apa adanya; backend mem-parse dengan `AmountParser` — tidak ada parser duplikat di JS.

## 10. Komponen & Tema

- Primitive **shadcn-vue** di `core/components/ui` (dikopi ke repo, bebas dimodifikasi).
- Semua warna lewat token CSS ([14-ui-design-system.md](14-ui-design-system.md)); `BaseChart` membaca token agar grafik ikut light/dark.
- Komponen ≤ ~200 baris; props/emit bertipe; PascalCase multi-word; prefix domain (`TransactionItem`, `BudgetProgress`).

## 11. Aturan Import

| Dari                   | Boleh import                                             |
| ---------------------- | -------------------------------------------------------- |
| `core/**`              | `core/**` saja                                           |
| `modules/X/**`         | `core/**`, `modules/X/**`, `@/modules/Y` (index.ts saja) |
| `main.ts`, `router/**` | semua                                                    |

Dijaga lint (boundaries) + test Vitest batas modul. Alias: `@/core`, `@/modules`.

## 12. Kualitas & Testing

| Alat                           | Fungsi                                                                     |
| ------------------------------ | -------------------------------------------------------------------------- |
| Vite+ (`vp check`)             | lint & format                                                              |
| `vue-tsc`                      | type check                                                                 |
| **Vitest** + `@vue/test-utils` | `lib/*`, client API (header, error mapping), composables, komponen penting |
| **Playwright**                 | E2E alur utama di SPA sampai database                                      |

Test frontend fokus pada **format & interaksi**; perhitungan sudah diuji di backend lewat test API.

## 13. Performa & PWA

- Lazy load per halaman (route `component: () => import(...)`), ApexCharts di-import dinamis.
- Widget dashboard dimuat paralel dengan skeleton; prefetch query saat hover menu utama.
- PWA manual: `manifest.webmanifest`, `sw.js` (cache-first hanya aset build & ikon; data API selalu dari network), `offline.html`. Input offline **tidak** didukung.

## 14. Checklist Fitur Baru (Frontend)

1. Endpoint API sudah ada + test skenario hijau ([test-cases](test-cases/README.md)).
2. `pnpm run api:types` untuk memperbarui `schema.d.ts`.
3. Buat `modules/<fitur>/` dengan `routes.ts`, `api.ts`, `pages/`, `components/`, `index.ts`; daftarkan routes di `router/routes.ts`.
4. Pakai komponen `core` sebelum membuat yang baru.
5. Tambah ke navigasi dengan permission.
6. Vitest untuk composable/komponen baru; E2E untuk alur utama; lint & type check bersih.
