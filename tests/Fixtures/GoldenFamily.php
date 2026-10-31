<?php

namespace Tests\Fixtures;

use App\Enums\FamilyRole;
use App\Enums\WalletType;
use App\Models\Category;
use App\Models\Family;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;

/**
 * Golden dataset from docs/12-testing-strategy.md §3 (October 2026, frozen at 31 Oct 2026).
 * Expected values are hand-calculated in GoldenFamily::EXPECTED.
 */
final class GoldenFamily
{
    public const array EXPECTED = [
        'income' => 8_000_000,
        'expense' => 2_287_500,
        'net' => 5_712_500,
        'balances' => [
            'Tunai' => 1_200_000,
            'BCA' => 15_100_000,
            'GoPay' => 112_500,
        ],
        'total_balance' => 16_412_500,
        'food_including_restaurant' => 650_000,
        'by_member' => ['Ibu' => 737_500, 'Ayah' => 1_550_000],
    ];

    public Family $family;

    public User $ayah;

    public User $ibu;

    /** @var array<string, Wallet> */
    public array $wallets = [];

    /** @var array<string, Category> */
    public array $categories = [];

    public static function seed(): self
    {
        $golden = new self;
        $golden->ayah = User::factory()->create(['name' => 'Ayah']);
        $golden->ibu = User::factory()->create(['name' => 'Ibu']);
        $golden->family = createFamily($golden->ayah, 'Keluarga Golden');
        addMember($golden->family, FamilyRole::Member, $golden->ibu);

        // Replace the default wallet with the golden wallets.
        Wallet::query()->delete();
        $golden->wallets['Tunai'] = Wallet::factory()->for($golden->family)->balance(500_000)->create(['name' => 'Tunai']);
        $golden->wallets['BCA'] = Wallet::factory()->for($golden->family)->ofType(WalletType::Bank)->balance(10_000_000)->create(['name' => 'BCA']);
        $golden->wallets['GoPay'] = Wallet::factory()->for($golden->family)->ofType(WalletType::Ewallet)->balance(200_000)->create(['name' => 'GoPay']);

        $category = fn (string $name): Category => Category::query()->where('name', $name)->whereNull('parent_id')->firstOrFail();
        $golden->categories['Gaji'] = $category('Gaji');
        $golden->categories['Makanan'] = $category('Makanan & Minuman');
        $golden->categories['Tagihan'] = $category('Tagihan & Utilitas');
        $golden->categories['Belanja'] = $category('Belanja Harian');
        $golden->categories['Transportasi'] = $category('Transportasi');
        $golden->categories['Restoran'] = Category::factory()->childOf($golden->categories['Makanan'])->create(['name' => 'Restoran']);

        $w = $golden->wallets;
        $c = $golden->categories;

        $make = fn (User $by, string $date) => Transaction::factory()->on("2026-10-{$date}")->state(['created_by' => $by->id]);

        $make($golden->ayah, '01')->income(8_000_000, $w['BCA'], $c['Gaji'])->create();
        $make($golden->ayah, '02')->transfer(1_000_000, $w['BCA'], $w['Tunai'])->create();
        $make($golden->ibu, '03')->expense(250_000, $w['Tunai'], $c['Makanan'])->create();
        $make($golden->ayah, '05')->expense(1_500_000, $w['BCA'], $c['Tagihan'])->create();
        $make($golden->ibu, '10')->expense(87_500, $w['GoPay'], $c['Belanja'])->create();
        $make($golden->ayah, '15')->expense(50_000, $w['Tunai'], $c['Transportasi'])->create();
        $make($golden->ibu, '20')->expense(400_000, $w['BCA'], $c['Restoran'])->create();
        $make($golden->ibu, '25')->expense(99_000, $w['Tunai'], $c['Makanan'])->create()->delete();

        return $golden;
    }
}
