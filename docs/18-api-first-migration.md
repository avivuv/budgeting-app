# 18 — Migrasi ke Arsitektur API-first (Frontend–Backend Terpisah)

Status: disetujui 3 Nov 2026 · dikerjakan sebelum Fase 5 ([roadmap](09-roadmap.md#fase-m--migrasi-api-first))

## 1. Latar Belakang & Keputusan

Fase 1–4 dibangun sebagai **monolith Inertia**: halaman Vue menerima data langsung dari controller web, dan REST API hanya pintu tambahan dengan sebagian fitur. Karena **aplikasi Android native (Kotlin)** direncanakan, semua klien harus memakai **satu REST API yang sama**.

| Keputusan      | Pilihan                                                                                                      |
| -------------- | ------------------------------------------------------------------------------------------------------------ |
| Arsitektur     | Laravel = **backend API only** (`/api/v1`); web = **SPA Vue terpisah**; Android native memakai API yang sama |
| Letak frontend | Satu repo, folder `frontend/`                                                                                |
| Domain         | Satu domain: SPA di `/`, API di `/api` (login web via cookie, tanpa CORS rumit)                              |
| Fase 5 & 6     | Ditunda sampai migrasi selesai, lalu dibangun API-first                                                      |
| Test           | Test skenario per fitur **lewat REST API** ([test-cases](test-cases/README.md)); E2E Playwright terhadap SPA |

Yang **tidak** berubah: Service, Repository, Model, DTO, business rules, tenancy, Job, activity log. Logika bisnis tidak ditulis ulang — hanya pintu masuknya.

## 2. Arsitektur Tujuan

```
┌─ Web SPA (frontend/) ─┐  cookie Sanctum + CSRF
│ Vue 3 + Vue Router    │──┐
└───────────────────────┘  │     ┌─ Laravel (backend) ─────────────────────────────┐
┌─ Android (Kotlin) ────┐  ├────▶│ /api/v1 → Api Controller → Service → Repository │──▶ MySQL
│ Retrofit/Ktor         │──┘     │ Auth: Fortify (headless) + Sanctum              │    Redis
└───────────────────────┘ Bearer │ OpenAPI: /docs/api                              │
                          token  └──────────────▲──────────────────────────────────┘
Telegram (fase 6) ── webhook ───────────────────┘ (handler → Service, tanpa REST)
```

## 3. Keputusan Teknis

### 3.1 Autentikasi

- **Satu guard `auth:sanctum`** untuk semua klien:
    - **SPA** — cookie session (Sanctum stateful) + `GET /sanctum/csrf-cookie`. Token tidak pernah disimpan di browser.
    - **Android** — personal access token: `POST /api/v1/auth/token` (`email`, `password`, `device_name`, kode 2FA bila aktif) → token + abilities; `DELETE /api/v1/auth/token` untuk logout. Masa berlaku mengikuti `SANCTUM_EXPIRATION`.
- **Fortify headless** (`'views' => false`): login, register, logout, lupa/reset password, verifikasi email, konfirmasi password, 2FA (aktifkan, QR, recovery code, challenge) menjawab JSON.
- **Passkey** tetap ada lewat endpoint JSON WebAuthn (options → verify), bisa dipakai SPA dan Android (Credential Manager).
- Abilities `read`/`write` berlaku untuk token; sesi SPA dianggap punya semua ability. **Role tetap dicek Policy** (BR-B09) untuk semua klien.

### 3.2 Kontrak API

- Semua aksi user punya endpoint di `/api/v1`. Format response, error, status code, `X-Family-Id`, dan `Idempotency-Key` mengikuti [13-rest-api.md](13-rest-api.md).
- Pelanggaran business rule menyertakan ID rule: `{"message": "...", "errors": {"field": ["..."]}, "rule": "BR-C05"}` agar klien mobile bisa bereaksi tanpa membaca teks.
- **OpenAPI** di-generate otomatis dari FormRequest + Resource (`dedoc/scramble`) di `/docs/api` (hanya non-production / ter-auth). Dipakai untuk:
    - tipe TypeScript SPA (`openapi-typescript`) — menggantikan Wayfinder & tipe Inertia,
    - client Kotlin (OpenAPI Generator) saat Android dibuat.
- File (gambar struk, export Excel/PDF) diunduh lewat endpoint API ter-auth; tidak ada URL publik.

### 3.3 Frontend SPA (`frontend/`)

- Vue 3 + TS (`<script setup>`), Vite+, **Vue Router**, **Pinia** (state sesi: user, keluarga aktif), **TanStack Query** (`@tanstack/vue-query`, cache & refetch data API), Tailwind 4 + shadcn-vue, ApexCharts, `@vueuse/core`.
- Struktur modul tetap: `src/core/` (ui, layouts, composables, lib) + `src/modules/<fitur>/` (pages, components, api, `index.ts`). Antar modul hanya via `index.ts`. Komponen UI & tema dari `resources/js` **dipindah**, bukan dibuat ulang.
- Tetap **tidak ada perhitungan bisnis di Vue** — angka (saldo, total, %, status budget) dari API.
- Build ke `public/app/`; Laravel melayani `index.html` untuk semua route non-`/api`. Dev: Vite dev server dengan proxy `/api` & `/sanctum` ke `budgeting-app.test`.
- PWA (manifest, service worker aset) ikut dipindah.

### 3.4 Testing

| Lapisan            | Alat                                         | Isi                                                                                                             |
| ------------------ | -------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| Skenario per fitur | Pest, `tests/Feature/Api/V1/<Fitur>Test.php` | positif & negatif dari [docs/test-cases](test-cases/README.md), Given–When–Then, cek response + DB + baca ulang |
| Logika murni       | Pest unit                                    | `app/Support`, parser, splitter (tidak berubah)                                                                 |
| Konsistensi        | `INV-xx`, golden dataset                     | sekarang dijalankan lewat API                                                                                   |
| Kontrak            | OpenAPI                                      | spec tervalidasi di CI; response test sesuai schema                                                             |
| SPA                | Vitest                                       | composable, komponen, batas modul                                                                               |
| E2E                | Playwright                                   | alur utama di SPA sampai DB                                                                                     |

Test web (Inertia) lama dihapus bersamaan dengan halamannya; skenarionya pindah ke test API.

## 4. Tahapan

Web Inertia lama **tetap jalan** sampai halaman SPA penggantinya siap, jadi aplikasi selalu bisa dipakai.

| Tahap                        | Isi                                                                                                                                                                                                                                                                                                                                                                                               | Selesai bila                                                                      |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| **M0** Dokumen               | Dokumen ini, roadmap, CLAUDE.md, catatan di 02/13/15/17, format test case                                                                                                                                                                                                                                                                                                                         | ✅                                                                                |
| **M1** Fondasi API           | Fortify headless + Sanctum stateful, `/api/v1/auth/*` (SPA & token), format error + `rule`, Scramble, CORS/cookie config, test skenario auth                                                                                                                                                                                                                                                      | ✅ Login/register/2FA/reset/passkey/token jalan lewat API                         |
| **M2** API lengkap per fitur | Untuk tiap fitur: API contract ([api-contracts](api-contracts/README.md)) + dokumen skenario → review → endpoint yang kurang / disesuaikan dengan kontrak → test skenario (menggantikan test API lama). Urutan: Dompet → Kategori → Transaksi → Budget → Ringkasan & Laporan & Export → Tabungan → Scan Struk → Keluarga, Anggota & Undangan → Onboarding → Notifikasi → Profil, Keamanan & Token | ✅ Semua fitur web punya endpoint + test skenario hijau; Android sudah bisa mulai |
| **M3** Kerangka SPA          | `frontend/` + tooling, router, auth pages, layout (sidebar, bottom nav, FAB), switch keluarga, tema, PWA                                                                                                                                                                                                                                                                                          | Login & navigasi jalan di SPA                                                     |
| **M4** Pindah halaman        | Per fitur (urutan sama dengan M2): halaman SPA memakai API → E2E → hapus halaman, controller, route & test Inertia-nya                                                                                                                                                                                                                                                                            | Semua halaman di SPA                                                              |
| **M5** Bersih-bersih         | Hapus Inertia, Wayfinder, `resources/js`, typescript-transformer bila tak terpakai; update CI, docs 02/10/15/17                                                                                                                                                                                                                                                                                   | Tidak ada sisa Inertia; semua gate hijau                                          |

Push ke GitHub per tahap (M1, M2, …) setelah gate hijau.

## 5. Endpoint yang Perlu Ditambah (M2)

| Fitur               | Sudah ada                                 | Ditambah                                                                                                            |
| ------------------- | ----------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Dompet              | list, create, update, delete              | arsip/aktifkan                                                                                                      |
| Kategori            | list (tree)                               | create, update, delete, arsip, gabung, keywords                                                                     |
| Transaksi           | list, show, create, update, delete, parse | aksi massal (hapus, ganti kategori), daftar tag, riwayat perubahan                                                  |
| Budget              | list, set bulk                            | salin periode lalu, hapus                                                                                           |
| Ringkasan & Laporan | summary, reports                          | dashboard widget lengkap, export Excel/PDF                                                                          |
| Tabungan            | —                                         | list, create, update, delete, setor                                                                                 |
| Scan Struk          | upload, show, koreksi, confirm, batal     | daftar terakhir + kuota, gambar                                                                                     |
| Keluarga & Anggota  | `GET /me`                                 | buat keluarga (onboarding), switch, keluar, anggota (ubah peran, keluarkan), undangan (kirim, batal, lihat, terima) |
| Notifikasi          | —                                         | list, tandai dibaca                                                                                                 |
| Profil & Keamanan   | —                                         | profil, password, tampilan, hapus akun, 2FA, passkey, token API                                                     |

## 6. Risiko & Mitigasi

| Risiko                              | Mitigasi                                                                                                        |
| ----------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| Fitur hilang saat pindah halaman    | Halaman Inertia baru dihapus setelah halaman SPA + E2E-nya lulus                                                |
| Perilaku web ≠ API                  | Web lama & API memanggil Service yang sama; test skenario lewat API sebelum halaman dipindah                    |
| Keamanan cookie SPA                 | Satu domain, `SameSite=Lax`, CSRF Sanctum, `SESSION_DOMAIN` & `SANCTUM_STATEFUL_DOMAINS` diatur per environment |
| Kontrak berubah diam-diam (Android) | OpenAPI di CI; perubahan breaking → `/api/v2`                                                                   |
