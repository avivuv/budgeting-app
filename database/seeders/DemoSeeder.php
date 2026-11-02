<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\DTOs\Family\FamilyData;
use App\DTOs\Transaction\TransactionData;
use App\DTOs\Wallet\WalletData;
use App\Enums\CategoryType;
use App\Enums\FamilyRole;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Models\Category;
use App\Models\User;
use App\Models\Wallet;
use App\Repositories\Contracts\FamilyRepositoryInterface;
use App\Services\Category\CategoryService;
use App\Services\Family\FamilyService;
use App\Services\Tenancy\TenantContext;
use App\Services\Transaction\TransactionService;
use App\Services\Wallet\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Local demo data: "Keluarga Demo" with two members and three months of
 * transactions, created through the services so every business rule applies.
 *
 *   php artisan db:seed --class=DemoSeeder
 *   login: demo@budgeting.test / password
 */
class DemoSeeder extends Seeder
{
    public function run(
        FamilyService $families,
        FamilyRepositoryInterface $familyRepository,
        WalletService $wallets,
        CategoryService $categories,
        TransactionService $transactions,
        TenantContext $tenant,
    ): void {
        if (User::query()->where('email', 'demo@budgeting.test')->exists()) {
            $this->command->warn('Demo data already exists (demo@budgeting.test).');

            return;
        }

        $ayah = User::factory()->create(['name' => 'Ayah Demo', 'email' => 'demo@budgeting.test']);
        $ibu = User::factory()->create(['name' => 'Ibu Demo', 'email' => 'ibu@budgeting.test']);

        $family = $families->create(new FamilyData(name: 'Keluarga Demo'), $ayah);
        $tenant->set($family);
        $familyRepository->addMember($family, $ibu, FamilyRole::Member);

        $tunai = $wallets->list()->first();
        $bca = $wallets->create(new WalletData(name: 'BCA', type: WalletType::Bank, initialBalance: 12_000_000));
        $gopay = $wallets->create(new WalletData(name: 'GoPay', type: WalletType::Ewallet, initialBalance: 250_000));

        $category = fn (CategoryType $type, string $name): Category => $categories->tree($type)
            ->first(fn (Category $c): bool => $c->name === $name)
            ?? $categories->tree($type)->firstOrFail();

        $expenses = [
            ['Makanan & Minuman', [15_000, 85_000], ['makan siang', 'kopi', 'sarapan', 'bakso']],
            ['Belanja Harian', [40_000, 350_000], ['sayur', 'indomaret', 'beras']],
            ['Transportasi', [10_000, 60_000], ['bensin', 'parkir', 'ojek']],
        ];

        $today = CarbonImmutable::now($family->timezone)->startOfDay();
        $start = $today->subMonthsNoOverflow(3)->startOfMonth();
        mt_srand(2026);

        $record = function (TransactionType $type, int $amount, CarbonImmutable $date, Wallet $wallet, ?Category $cat, ?string $note, User $by, ?Wallet $to = null) use ($transactions): void {
            $transactions->create(new TransactionData(
                type: $type,
                amount: $amount,
                date: $date,
                walletId: $wallet->id,
                toWalletId: $to?->id,
                categoryId: $cat?->id,
                note: $note,
                source: TransactionSource::Web,
            ), $by);
        };

        for ($date = $start; $date->lessThanOrEqualTo($today); $date = $date->addDay()) {
            if ($date->day === 1) {
                $record(TransactionType::Income, 9_500_000, $date, $bca, $category(CategoryType::Income, 'Gaji'), 'gaji', $ayah);
                $record(TransactionType::Transfer, 1_500_000, $date, $bca, null, 'isi dompet', $ayah, $tunai);
            }

            if ($date->day === 5) {
                $record(TransactionType::Expense, 1_250_000, $date, $bca, $category(CategoryType::Expense, 'Tagihan & Utilitas'), 'listrik & internet', $ayah);
            }

            foreach ($expenses as [$name, [$min, $max], $notes]) {
                if (mt_rand(0, 2) === 0) {
                    continue;
                }

                $amount = intdiv(mt_rand($min, $max), 500) * 500;
                $by = mt_rand(0, 1) === 0 ? $ayah : $ibu;
                // E-wallet for small purchases only, so no wallet runs deep into the red.
                $wallet = $amount <= 50_000 ? [$tunai, $gopay][mt_rand(0, 1)] : [$tunai, $bca][mt_rand(0, 1)];
                $record(TransactionType::Expense, $amount, $date, $wallet, $category(CategoryType::Expense, $name), $notes[array_rand($notes)], $by);
            }
        }

        $this->command->info('Demo family seeded. Login: demo@budgeting.test / password');
    }
}
