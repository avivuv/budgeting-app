<?php

use App\Enums\FamilyRole;
use App\Models\Activity;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\GoldenFamily;

/*
| Scenarios: docs/test-cases/categories.md · Contract: docs/api-contracts/categories.md
|
| Golden family: Ayah owner, Ibu member, default categories plus the
| sub-category "Restoran" under "Makanan & Minuman" (docs/12 §3).
*/

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->golden = GoldenFamily::seed();
});

function signIn(User $user, array $abilities = ['read', 'write']): void
{
    Sanctum::actingAs($user, $abilities);
}

/**
 * Names in the category tree, children as "Parent > Child".
 *
 * @return list<string>
 */
function categoryNames(string $query = ''): array
{
    $names = [];
    foreach (test()->getJson('/api/v1/categories'.$query)->assertOk()->json('data') as $parent) {
        $names[] = $parent['name'];
        foreach ($parent['children'] as $child) {
            $names[] = $parent['name'].' > '.$child['name'];
        }
    }

    return $names;
}

function hobby(array $attributes = []): Category
{
    return Category::factory()->expense()->create([
        'family_id' => test()->golden->family->id,
        'name' => 'Hobi',
        ...$attributes,
    ]);
}

function expenseTotal(): int
{
    return test()->getJson('/api/v1/transactions')->assertOk()->json('meta.totals.expense');
}

describe('positive', function () {
    it('[CAT-01] creates an expense category with keywords typed as text', function () {
        signIn($this->golden->ayah);

        $response = $this->postJson('/api/v1/categories', ['type' => 'expense', 'name' => 'Hobi', 'keywords' => 'mancing, futsal']);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'expense')
            ->assertJsonPath('data.name', 'Hobi')
            ->assertJsonPath('data.parent_id', null)
            ->assertJsonPath('data.keywords', ['mancing', 'futsal'])
            ->assertJsonPath('data.is_archived', false);

        $stored = Category::findOrFail($response->json('data.id'));
        expect($stored->family_id)->toBe($this->golden->family->id)
            ->and($stored->keywords)->toBe(['mancing', 'futsal'])
            ->and(categoryNames('?type=expense'))->toContain('Hobi');
    });

    it('[CAT-02] creates a sub-category under Transportasi [BR-E01]', function () {
        signIn($this->golden->ayah);
        $transport = $this->golden->categories['Transportasi'];

        $this->postJson('/api/v1/categories', ['type' => 'expense', 'name' => 'Ojek Online', 'parent_id' => $transport->id])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $transport->id);

        expect(categoryNames('?type=expense'))->toContain('Transportasi > Ojek Online');
    });

    it('[CAT-03] lists only income categories as a tree', function () {
        signIn($this->golden->ibu, ['read']);

        $data = $this->getJson('/api/v1/categories?type=income')->assertOk()->json('data');

        expect($data)->toHaveCount(count(config('budgeting.default_categories.income')))
            ->and(collect($data)->pluck('type')->unique()->all())->toBe(['income'])
            ->and(categoryNames('?type=expense'))->toContain('Makanan & Minuman > Restoran');
    });

    it('[CAT-04] renames a category and replaces its keywords', function () {
        signIn($this->golden->ayah);
        $hobby = hobby(['keywords' => ['mancing']]);

        $this->putJson("/api/v1/categories/{$hobby->id}", ['type' => 'expense', 'name' => 'Hobi & Olahraga', 'keywords' => ['mancing', 'futsal', 'badminton']])
            ->assertOk()
            ->assertJsonPath('data.name', 'Hobi & Olahraga')
            ->assertJsonPath('data.keywords', ['mancing', 'futsal', 'badminton']);

        expect($hobby->fresh()->name)->toBe('Hobi & Olahraga')
            ->and(categoryNames())->toContain('Hobi & Olahraga')->not->toContain('Hobi');
    });

    it('[CAT-05] archives a category out of the list and new transactions, then restores it [BR-B05]', function () {
        signIn($this->golden->ayah);
        $hobby = hobby();
        $newExpense = fn () => $this->postJson('/api/v1/transactions', [
            'type' => 'expense', 'amount' => '75rb', 'date' => '2026-10-31',
            'wallet_id' => $this->golden->wallets['Tunai']->id, 'category_id' => $hobby->id,
        ]);

        $this->patchJson("/api/v1/categories/{$hobby->id}/archive", ['archived' => true])
            ->assertOk()
            ->assertJsonPath('data.is_archived', true);

        expect(categoryNames())->not->toContain('Hobi')
            ->and(categoryNames('?include_archived=1'))->toContain('Hobi');
        $newExpense()->assertUnprocessable()->assertJsonPath('errors.category_id.0', 'Kategori ini sudah diarsipkan dan tidak bisa dipakai untuk transaksi baru.');

        $this->patchJson("/api/v1/categories/{$hobby->id}/archive", ['archived' => false])->assertOk();

        expect(categoryNames())->toContain('Hobi');
        $newExpense()->assertCreated();
    });

    it('[CAT-06] merges a category: transactions move and are logged, budgets move and add up [BR-E03] [BR-B14]', function () {
        signIn($this->golden->ayah);
        $food = $this->golden->categories['Makanan'];
        $snacks = Category::factory()->expense()->create(['family_id' => $this->golden->family->id, 'name' => 'Jajan']);
        $wallet = $this->golden->wallets['Tunai'];
        $moved = collect([10_000, 15_000, 20_000])->map(fn (int $amount) => Transaction::factory()->on('2026-10-30')
            ->expense($amount, $wallet, $snacks)->create(['created_by' => $this->golden->ayah->id]));
        // Budgets: October exists on both (added up), November only on "Jajan" (moved).
        Budget::factory()->forCategory($food, '2026-10-01', 1_000_000)->create();
        Budget::factory()->forCategory($snacks, '2026-10-01', 300_000)->create();
        Budget::factory()->forCategory($snacks, '2026-11-01', 200_000)->create();
        $totalBefore = expenseTotal();

        $this->postJson("/api/v1/categories/{$snacks->id}/merge", ['target_id' => $food->id])
            ->assertOk()
            ->assertJsonPath('data.id', $food->id)
            ->assertJsonPath('meta.moved_transactions', 3);

        expect(Transaction::whereIn('id', $moved->pluck('id'))->pluck('category_id')->unique()->all())->toBe([$food->id])
            ->and(Category::find($snacks->id))->toBeNull()
            ->and(categoryNames())->not->toContain('Jajan')
            ->and(expenseTotal())->toBe($totalBefore)
            ->and(Budget::where('category_id', $food->id)->orderBy('period')->pluck('amount', 'period')->mapWithKeys(fn ($amount, $period) => [substr((string) $period, 0, 10) => $amount])->all())
            ->toBe(['2026-10-01' => 1_300_000, '2026-11-01' => 200_000]);

        $logs = Activity::query()->where('subject_type', (new Transaction)->getMorphClass())->whereIn('subject_id', $moved->pluck('id'))->where('event', 'updated')->get();
        expect($logs)->toHaveCount(3)
            ->and($logs->pluck('attribute_changes.old.category_id')->unique()->all())->toBe([$snacks->id])
            ->and($logs->pluck('attribute_changes.attributes.category_id')->unique()->all())->toBe([$food->id]);
    });

    it('[CAT-07] deletes a category that was never used, with its budget', function () {
        signIn($this->golden->ayah);
        $hobby = hobby();
        Budget::factory()->forCategory($hobby, '2026-10-01', 500_000)->create();

        $this->deleteJson("/api/v1/categories/{$hobby->id}")->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $hobby->id]);
        $this->assertDatabaseMissing('budgets', ['category_id' => $hobby->id]);
        expect(categoryNames('?include_archived=1'))->not->toContain('Hobi');
    });

    it('[CAT-08] lets an admin create and change categories [BR-B09]', function () {
        signIn(addMember($this->golden->family, FamilyRole::Admin));

        $id = $this->postJson('/api/v1/categories', ['type' => 'income', 'name' => 'Hadiah Lomba'])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/categories/{$id}", ['type' => 'income', 'name' => 'Hadiah Lomba Lari'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Hadiah Lomba Lari');
    });

    it('[CAT-09] guesses the category from its keywords in quick entry [BR-E05]', function () {
        signIn($this->golden->ayah);
        $hobby = hobby(['keywords' => ['mancing']]);

        $this->postJson('/api/v1/transactions/parse', ['text' => 'mancing 75rb'])
            ->assertOk()
            ->assertJsonPath('data.0.category_id', $hobby->id)
            ->assertJsonPath('data.0.amount', 75_000);
    });
});

describe('negative', function () {
    it('[CAT-11] rejects a missing or too long name and a missing or unknown type', function () {
        signIn($this->golden->ayah);
        $before = Category::count();

        $this->postJson('/api/v1/categories', ['type' => 'expense', 'name' => ''])
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'Nama wajib diisi.');
        $this->postJson('/api/v1/categories', ['type' => 'expense', 'name' => str_repeat('a', 51)])
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'Nama maksimal 50 karakter.');
        $this->postJson('/api/v1/categories', ['name' => 'Hobi'])
            ->assertUnprocessable()->assertJsonPath('errors.type.0', 'Jenis wajib diisi.');
        $this->postJson('/api/v1/categories', ['type' => 'transfer', 'name' => 'Hobi'])
            ->assertUnprocessable()->assertJsonPath('errors.type.0', 'Jenis yang dipilih tidak valid.');

        expect(Category::count())->toBe($before);
    });

    it('[CAT-12] rejects a second "Transportasi" on the same level [BR-E02]', function () {
        signIn($this->golden->ayah);
        $before = Category::count();

        $this->postJson('/api/v1/categories', ['type' => 'expense', 'name' => 'Transportasi'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Nama kategori sudah dipakai.')
            ->assertJsonPath('rule', 'BR-E02');

        expect(Category::count())->toBe($before);
    });

    it('[CAT-13] allows the same name for another type or under another parent [BR-E02]', function () {
        signIn($this->golden->ayah);

        $this->postJson('/api/v1/categories', ['type' => 'income', 'name' => 'Transportasi'])->assertCreated();
        $this->postJson('/api/v1/categories', ['type' => 'expense', 'name' => 'Lainnya', 'parent_id' => $this->golden->categories['Transportasi']->id])->assertCreated();
        $this->postJson('/api/v1/categories', ['type' => 'expense', 'name' => 'Lainnya', 'parent_id' => $this->golden->categories['Makanan']->id])->assertCreated();
    });

    it('[CAT-14] rejects a sub-category of a sub-category [BR-E01]', function () {
        signIn($this->golden->ayah);

        $this->postJson('/api/v1/categories', ['type' => 'expense', 'name' => 'Warteg', 'parent_id' => $this->golden->categories['Restoran']->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.parent_id.0', 'Sub-kategori tidak bisa memiliki sub-kategori lagi.')
            ->assertJsonPath('rule', 'BR-E01');

        expect(Category::where('name', 'Warteg')->exists())->toBeFalse();
    });

    it('[CAT-15] rejects an income sub-category under an expense parent [BR-E01]', function () {
        signIn($this->golden->ayah);

        $this->postJson('/api/v1/categories', ['type' => 'income', 'name' => 'Cashback', 'parent_id' => $this->golden->categories['Belanja']->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.parent_id.0', 'Sub-kategori harus memiliki jenis yang sama dengan induknya.');

        expect(Category::where('name', 'Cashback')->exists())->toBeFalse();
    });

    it('[CAT-16] refuses to change the type of a used category [BR-E06]', function () {
        signIn($this->golden->ayah);
        $transport = $this->golden->categories['Transportasi'];

        $this->putJson("/api/v1/categories/{$transport->id}", ['type' => 'income', 'name' => 'Transportasi'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.type.0', 'Jenis kategori tidak bisa diubah karena sudah dipakai transaksi atau punya sub-kategori.')
            ->assertJsonPath('rule', 'BR-E06');

        expect($transport->fresh()->type->value)->toBe('expense');
    });

    it('[CAT-17] refuses to delete a used category and keeps its transactions [BR-E03]', function () {
        signIn($this->golden->ayah);
        $transport = $this->golden->categories['Transportasi'];
        $count = Transaction::withTrashed()->where('category_id', $transport->id)->count();

        $this->deleteJson("/api/v1/categories/{$transport->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kategori sudah dipakai transaksi. Arsipkan atau gabungkan ke kategori lain.')
            ->assertJsonPath('rule', 'BR-E03');

        expect($transport->fresh())->not->toBeNull()
            ->and(Transaction::withTrashed()->where('category_id', $transport->id)->count())->toBe($count);
    });

    it('[CAT-18] refuses to delete or merge a category that still has sub-categories [BR-E01]', function () {
        signIn($this->golden->ayah);
        $food = $this->golden->categories['Makanan'];

        $this->deleteJson("/api/v1/categories/{$food->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.general.0', 'Hapus atau pindahkan sub-kategorinya terlebih dahulu.');
        $this->postJson("/api/v1/categories/{$food->id}/merge", ['target_id' => $this->golden->categories['Belanja']->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.general.0', 'Hapus atau pindahkan sub-kategorinya terlebih dahulu.');

        expect($food->fresh())->not->toBeNull()
            ->and(categoryNames())->toContain('Makanan & Minuman > Restoran');
    });

    it('[CAT-19] refuses to merge into itself, another type or an archived category [BR-E03]', function (string $target, string $message) {
        signIn($this->golden->ayah);
        $transport = $this->golden->categories['Transportasi'];
        $targetId = match ($target) {
            'itself' => $transport->id,
            'income' => $this->golden->categories['Gaji']->id,
            'archived' => hobby(['is_archived' => true])->id,
        };
        $count = Transaction::withTrashed()->where('category_id', $transport->id)->count();

        $this->postJson("/api/v1/categories/{$transport->id}/merge", ['target_id' => $targetId])
            ->assertUnprocessable()
            ->assertJsonPath('errors.target_id.0', $message)
            ->assertJsonPath('rule', 'BR-E03');

        expect($transport->fresh())->not->toBeNull()
            ->and(Transaction::withTrashed()->where('category_id', $transport->id)->count())->toBe($count);
    })->with([
        'itself' => ['itself', 'Pilih kategori tujuan yang berbeda.'],
        'another type' => ['income', 'Kategori hanya bisa digabung dengan kategori berjenis sama.'],
        'archived' => ['archived', 'Kategori tujuan sedang diarsipkan. Aktifkan dulu kategori tersebut.'],
    ]);

    it('[CAT-20] does not let a member, a viewer or a read-only token manage categories [BR-B09]', function (string $who) {
        $hobby = hobby();
        match ($who) {
            'member' => signIn($this->golden->ibu),
            'viewer' => signIn(addMember($this->golden->family, FamilyRole::Viewer)),
            'read token' => signIn($this->golden->ayah, ['read']),
        };

        $this->postJson('/api/v1/categories', ['type' => 'expense', 'name' => 'Baru'])->assertForbidden();
        $this->putJson("/api/v1/categories/{$hobby->id}", ['type' => 'expense', 'name' => 'Diubah'])->assertForbidden();
        $this->patchJson("/api/v1/categories/{$hobby->id}/archive", ['archived' => true])->assertForbidden();
        $this->postJson("/api/v1/categories/{$hobby->id}/merge", ['target_id' => $this->golden->categories['Makanan']->id])->assertForbidden();
        $this->deleteJson("/api/v1/categories/{$hobby->id}")->assertForbidden();

        $hobby->refresh();
        expect(Category::where('name', 'Baru')->exists())->toBeFalse()
            ->and($hobby->name)->toBe('Hobi')
            ->and($hobby->is_archived)->toBeFalse();
    })->with(['member', 'viewer', 'read token']);

    it('[CAT-21] hides categories of another family behind 404 [BR-I06]', function () {
        $neighbour = createFamily(name: 'Tetangga');
        $theirs = Category::where('family_id', $neighbour->id)->where('name', 'Transportasi')->firstOrFail();
        useFamily($this->golden->family);
        signIn($this->golden->ayah);
        $hobby = hobby();

        $this->postJson('/api/v1/categories', ['type' => 'expense', 'name' => 'Numpang', 'parent_id' => $theirs->id])->assertNotFound();
        $this->putJson("/api/v1/categories/{$theirs->id}", ['type' => 'expense', 'name' => 'Diambil'])->assertNotFound();
        $this->postJson("/api/v1/categories/{$hobby->id}/merge", ['target_id' => $theirs->id])->assertNotFound();
        $this->deleteJson("/api/v1/categories/{$theirs->id}")->assertNotFound();

        expect(Category::where('name', 'Numpang')->exists())->toBeFalse()
            ->and($theirs->fresh()->name)->toBe('Transportasi')
            ->and($hobby->fresh())->not->toBeNull();
    });

    it('[CAT-22] lists only the categories of the own family [BR-I06]', function () {
        $neighbour = createFamily(name: 'Tetangga');
        Category::factory()->expense()->create(['family_id' => $neighbour->id, 'name' => 'Rahasia Tetangga']);
        signIn($this->golden->ayah, ['read']);

        expect(categoryNames('?include_archived=1'))->not->toContain('Rahasia Tetangga');
    });
});
