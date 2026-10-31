# 03 — Multi-Tenancy

## 1. Model Tenancy

- **Tenant = `Family`** (keluarga).
- **Single database**, setiap tabel data tenant punya kolom `family_id` (FK + index).
- User adalah entitas global; keanggotaan ke keluarga melalui pivot `family_user` dengan `role`.
- Satu user bisa anggota beberapa keluarga. Keluarga aktif disimpan di `users.current_family_id`.

Alasan memilih single-DB: sederhana untuk dikembangkan di lokal, mudah di-backup, cukup untuk skala keluarga. Bisa dipindah ke DB-per-tenant nanti tanpa mengubah layer service.

## 2. TenantContext

`App\Services\Tenancy\TenantContext` (singleton) menyimpan keluarga aktif untuk request/job yang sedang berjalan.

```php
class TenantContext
{
    private ?Family $family = null;

    public function set(Family $family): void { $this->family = $family; }
    public function get(): Family { return $this->family ?? throw new TenantNotResolvedException(); }
    public function id(): int { return $this->get()->id; }
    public function has(): bool { return $this->family !== null; }
    public function forget(): void { $this->family = null; }
}
```

Siapa yang mengisi `TenantContext`:

| Entry point | Sumber family |
|---|---|
| Web | Middleware `SetCurrentFamily` → `auth()->user()->currentFamily` (validasi masih anggota) |
| Telegram private chat | `users.telegram_user_id` → user → `current_family_id` (bisa diganti `/keluarga`) |
| Telegram group | `telegram_chats.chat_id` → `family_id` |
| Queue job | Job menyimpan `familyId` di constructor, set context di awal `handle()` |
| Scheduler/command | Loop per keluarga, set context per iterasi |

## 3. Global Scope & Trait

```php
// app/Models/Concerns/BelongsToFamily.php
trait BelongsToFamily
{
    protected static function bootBelongsToFamily(): void
    {
        static::addGlobalScope(new FamilyScope());

        static::creating(function (Model $model) {
            $model->family_id ??= app(TenantContext::class)->id();
        });
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}

// app/Models/Scopes/FamilyScope.php
class FamilyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('family_id'), app(TenantContext::class)->id());
    }
}
```

Model yang memakai trait: `Wallet`, `Category`, `Tag`, `Transaction`, `Budget`, `RecurringRule`, `SavingGoal`, `Receipt`, `TelegramChat`, `Invitation`.

**Aturan:**
- Bila `TenantContext` kosong, scope melempar exception — **fail closed**, bukan menampilkan semua data.
- `withoutGlobalScope(FamilyScope::class)` hanya boleh di repository khusus Super Admin dan diberi komentar alasan.
- Validasi `exists` di FormRequest harus ikut membatasi `family_id` (mis. `Rule::exists('wallets','id')->where('family_id', $familyId)`).
- Route model binding otomatis aman karena scope aktif; tetap dilindungi Policy.

## 4. Role & Izin

Enum `FamilyRole`: `owner`, `admin`, `member`, `viewer`.

| Aksi | owner | admin | member | viewer |
|---|:-:|:-:|:-:|:-:|
| Lihat dashboard & laporan | ✅ | ✅ | ✅ | ✅ |
| Tambah transaksi (web/Telegram/struk) | ✅ | ✅ | ✅ | ❌ |
| Edit/hapus transaksi milik sendiri | ✅ | ✅ | ✅ | ❌ |
| Edit/hapus transaksi orang lain | ✅ | ✅ | ❌ | ❌ |
| Kelola dompet, kategori, budget | ✅ | ✅ | ❌ | ❌ |
| Undang / keluarkan anggota | ✅ | ✅ | ❌ | ❌ |
| Hubungkan grup Telegram | ✅ | ✅ | ❌ | ❌ |
| Ubah pengaturan & hapus keluarga | ✅ | ❌ | ❌ | ❌ |
| Transfer kepemilikan | ✅ | ❌ | ❌ | ❌ |

Implementasi: Policy per model + middleware `EnsureFamilyRole:admin`. Helper `$user->roleIn($family)`.

Super Admin adalah flag global `users.is_super_admin`, tidak terkait keluarga.

## 5. Siklus Hidup Keluarga

1. **Registrasi** → wizard: buat keluarga (nama, zona waktu) → `FamilyService::create` membuat pivot owner, **dompet default** (Tunai) dan **kategori default**.
2. **Undang** → `InvitationService::invite(email, role)` → email berisi link `/invitations/{token}` (berlaku 7 hari). User baru mendaftar lalu otomatis bergabung; user lama cukup menerima.
3. **Switch keluarga** → `PUT /families/{family}/switch` update `current_family_id`.
4. **Keluar / dikeluarkan** → hapus pivot; transaksi tetap ada dengan `created_by` lama.
5. **Hapus keluarga** (owner) → soft delete; data dihapus permanen oleh job terjadwal setelah 30 hari.
6. **Suspend** (super admin) → `families.suspended_at`; middleware & bot menolak akses.

## 6. Kategori Default

Pengeluaran: Makanan & Minuman, Belanja Harian, Transportasi, Tagihan & Utilitas, Pendidikan, Kesehatan, Rumah Tangga, Hiburan, Pakaian, Sosial & Donasi, Cicilan, Lain-lain.
Pemasukan: Gaji, Bonus, Usaha, Hadiah, Lain-lain.

Masing-masing membawa `keywords` awal (mis. Transportasi: `bensin, pertalite, pertamax, parkir, tol, grab, gojek, ojek, kereta, krl`). Disimpan di `database/seeders/data/default_categories.php`.

## 7. Testing Isolasi (wajib)

- Test untuk setiap repository: data keluarga A tidak muncul saat context = keluarga B.
- Test endpoint: akses ID milik keluarga lain → 404.
- Test bot: chat grup keluarga A tidak bisa menulis ke keluarga B.
