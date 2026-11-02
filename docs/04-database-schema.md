# 04 — Database Schema

Konvensi umum:

- **Nominal uang disimpan sebagai `BIGINT` (satuan terkecil, Rupiah utuh)**, bukan float/decimal. Formatting hanya di layer tampilan.
- Semua tabel tenant punya `family_id` (FK `families.id`, `cascadeOnDelete`) dan index komposit yang diawali `family_id`.
- Timestamp `created_at`/`updated_at`; `deleted_at` (soft delete) pada tabel yang dicatat ⚑.
- Enum disimpan sebagai `string` dan di-cast ke PHP Enum.

## 1. ERD Ringkas

```
users ──< family_user >── families ──< wallets
  │                          │ ├──< categories (self parent_id)
  │                          │ ├──< tags
  │                          │ ├──< transactions >── transaction_tag >── tags
  │                          │ ├──< budgets
  │                          │ ├──< recurring_rules
  │                          │ ├──< saving_goals
  │                          │ ├──< receipts ──< receipt_items
  │                          │ ├──< telegram_chats
  │                          │ └──< invitations
  └──< telegram_link_codes
```

## 2. Tabel

### families ⚑

| Kolom                 | Tipe           | Keterangan                                   |
| --------------------- | -------------- | -------------------------------------------- |
| id                    | bigint PK      |                                              |
| name                  | string(100)    |                                              |
| slug                  | string unique  |                                              |
| currency              | char(3)        | default `IDR`                                |
| timezone              | string         | default `Asia/Jakarta`                       |
| period_start_day      | tinyint        | 1–28, awal periode bulanan                   |
| receipt_quota_monthly | int            | default 50                                   |
| settings              | json           | preferensi notifikasi, privasi saldo di grup |
| suspended_at          | timestamp null |                                              |

### users

| Kolom                                                                 | Tipe               | Keterangan                                                                   |
| --------------------------------------------------------------------- | ------------------ | ---------------------------------------------------------------------------- |
| id, name, email (unique), password, email_verified_at, remember_token |                    | standar Laravel                                                              |
| current_family_id                                                     | FK null            | keluarga aktif di web                                                        |
| telegram_default_family_id                                            | FK null            | keluarga tujuan input chat pribadi Telegram (BR-J09)                         |
| telegram_user_id                                                      | bigint null unique | id user Telegram                                                             |
| telegram_username                                                     | string null        |                                                                              |
| is_super_admin                                                        | bool               | default false                                                                |
| appearance                                                            | string(10)         | `system`/`light`/`dark`, default `system` (tema, tersinkron antar perangkat) |
| locale                                                                | string             | default `id`                                                                 |

### family_user

| Kolom              | Tipe      | Keterangan                        |
| ------------------ | --------- | --------------------------------- |
| family_id, user_id | FK        | unique(family_id, user_id)        |
| role               | string    | `owner`/`admin`/`member`/`viewer` |
| joined_at          | timestamp |                                   |

### invitations

`id, family_id, email, role, token (unique, 64), invited_by (FK users), accepted_at null, expires_at`

### telegram_link_codes

`id, user_id, family_id null, code (unique, 8), purpose (private|group), expires_at, used_at null`
Kode sekali pakai, berlaku 15 menit.

### telegram_chats

| Kolom                                     | Tipe          | Keterangan                           |
| ----------------------------------------- | ------------- | ------------------------------------ |
| id                                        | PK            |                                      |
| family_id                                 | FK            |                                      |
| chat_id                                   | bigint unique | id chat/grup Telegram                |
| type                                      | string        | `private`/`group`/`supergroup`       |
| title                                     | string null   | nama grup                            |
| linked_by                                 | FK users      |                                      |
| notify_daily_summary, notify_budget_alert | bool          |                                      |
| is_active                                 | bool          | false jika bot dikeluarkan dari grup |

### wallets ⚑

`id, family_id, name, type (cash|bank|ewallet|credit_card|other), initial_balance bigint, color, icon, is_archived, sort_order`

Saldo berjalan **dihitung** (`initial_balance + Σ income − Σ expense ± transfer`), di-cache per wallet dan di-invalidate saat ada transaksi.

### categories ⚑

`id, family_id, parent_id null (self FK), type (income|expense), name, icon, color, keywords json, is_default, sort_order`
Index: `(family_id, type)`.

### tags

`id, family_id, name` — unique(family_id, name)

### transactions ⚑

| Kolom               | Tipe             | Keterangan                                   |
| ------------------- | ---------------- | -------------------------------------------- |
| id                  | PK               |                                              |
| family_id           | FK               |                                              |
| type                | string           | `income`/`expense`/`transfer`                |
| amount              | bigint unsigned  | selalu positif                               |
| date                | date             | tanggal transaksi                            |
| wallet_id           | FK               | dompet sumber                                |
| to_wallet_id        | FK null          | khusus transfer                              |
| category_id         | FK null          | null untuk transfer                          |
| note                | string(255) null |                                              |
| merchant            | string null      | dari struk                                   |
| source              | string           | `web`/`api`/`telegram`/`receipt`/`recurring` |
| receipt_id          | FK null          |                                              |
| recurring_rule_id   | FK null          |                                              |
| created_by          | FK users         | pencatat                                     |
| telegram_message_id | bigint null      | untuk edit/undo dari bot                     |

Index: `(family_id, date)`, `(family_id, category_id, date)`, `(family_id, wallet_id)`, `(family_id, created_by)`.

### transaction_tag

`transaction_id, tag_id` (PK komposit)

### budgets

`id, family_id, category_id, period (date), amount bigint, alert_80_sent_at null, alert_100_sent_at null`
Unique `(family_id, category_id, period)`. `period` = **tanggal 1 bulan label** (mis. `2026-11-01` untuk periode "Nov 2026"), bukan tanggal mulai periode, agar budget tidak bergeser bila `period_start_day` keluarga diubah (BR-D02). Hanya kategori `expense` (BR-F01); budget kategori induk menghitung sub-kategorinya (BR-F02). Flag alert di-reset bila realisasi turun di bawah ambang (BR-F05). Opsi "salin dari bulan lalu" (BR-F07).

### recurring_rules ⚑

`id, family_id, type, amount, wallet_id, category_id, note, frequency (daily|weekly|monthly|yearly), interval, day_of_month null, next_run_at, end_at null, auto_create bool, remind_days_before tinyint, created_by`
`auto_create = false` → hanya pengingat (bot tanya "sudah bayar?").

### saving_goals ⚑

`id, family_id, name, target_amount bigint, wallet_id null, saved_amount bigint default 0, deadline date null, icon, color, achieved_at null`
Progres = saldo dompet terkait, atau `saved_amount` (kontribusi manual) bila tanpa dompet (BR-K01…K04).

### receipts ⚑

| Kolom                                 | Tipe              | Keterangan                                                                |
| ------------------------------------- | ----------------- | ------------------------------------------------------------------------- |
| id                                    | PK                |                                                                           |
| family_id, user_id                    | FK                |                                                                           |
| file_path                             | string            | disk `local` (privat)                                                     |
| file_hash                             | char(64)          | sha256 → deteksi file sama                                                |
| mime_type                             | string            | image/jpeg, image/png, application/pdf, ...                               |
| document_type                         | string null       | `receipt`/`transfer_proof`/`ewallet`/`invoice`                            |
| suggested_wallet_id                   | FK null           | tebakan dompet (mis. screenshot GoPay)                                    |
| expires_at                            | timestamp         | draft kedaluwarsa (BR-H10)                                                |
| status                                | string            | `pending`/`processing`/`parsed`/`confirmed`/`failed`/`rejected`/`expired` |
| merchant                              | string null       |                                                                           |
| receipt_date                          | date null         |                                                                           |
| total                                 | bigint null       |                                                                           |
| suggested_category_id                 | FK null           |                                                                           |
| confidence                            | decimal(3,2) null | 0–1                                                                       |
| raw_response                          | json null         | output mentah AI                                                          |
| model                                 | string null       | model AI yang dipakai                                                     |
| error                                 | text null         |                                                                           |
| telegram_chat_id, telegram_message_id | bigint null       | untuk balas preview                                                       |

### receipt_items

`id, receipt_id, name, qty decimal(8,2), unit_price bigint, total bigint, category_id null`

### activity_log — database terpisah `budgeting_app_log` (koneksi `activity`, BR-B14)

`id, family_id, log_name, description, subject_type, subject_id, event, causer_type, causer_id, attribute_changes json, properties json, created_at, updated_at`
Index: `(family_id, subject_type, subject_id)`, `(family_id, created_at)`, `created_at`. Karena beda database: **tanpa foreign key** ke `users`/`families`. Retensi **30 hari**: `activitylog:clean` dijadwalkan harian 01:00. Model `App\Models\Activity` (`$connection = activity`).

### data_exports

`id, family_id, requested_by, status (pending|ready|expired), file_path null, expires_at` — export seluruh data (BR-I08).

### usage_logs

`id, family_id, feature (receipt_scan|...), period (date), count` — unique(family_id, feature, period). Dasar kuota.

## 3. Tabel Bawaan Laravel

`sessions, cache, jobs, failed_jobs, job_batches, password_reset_tokens, notifications`.

## 4. Urutan Migration

1. families → users (alter) → family_user → invitations
2. wallets → categories → tags
3. receipts → recurring_rules → transactions → transaction_tag → receipt_items
4. budgets → saving_goals
5. activity_log → data_exports → usage_logs
6. telegram_chats → telegram_link_codes _(fase bot)_
