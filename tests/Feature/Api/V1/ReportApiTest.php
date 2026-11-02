<?php

use App\Enums\FamilyRole;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\GoldenFamily;

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->golden = GoldenFamily::seed();
});

it('returns the dashboard summary [INV-02] [INV-03] [BR-A05]', function () {
    Sanctum::actingAs($this->golden->ibu, ['read']);

    $this->getJson('/api/v1/summary?period=2026-10')
        ->assertOk()
        ->assertJsonPath('data.period.key', '2026-10')
        ->assertJsonPath('data.summary.income.amount', GoldenFamily::EXPECTED['income'])
        ->assertJsonPath('data.summary.expense.amount', GoldenFamily::EXPECTED['expense'])
        ->assertJsonPath('data.summary.net.amount', GoldenFamily::EXPECTED['net'])
        ->assertJsonPath('data.summary.balance.amount', GoldenFamily::EXPECTED['total_balance'])
        ->assertJsonPath('data.summary.expense.change', null);
});

it('filters the summary by wallet and member', function () {
    Sanctum::actingAs($this->golden->ibu, ['read']);

    $this->getJson('/api/v1/summary?wallet_id='.$this->golden->wallets['BCA']->id.'&user_id='.$this->golden->ibu->id)
        ->assertJsonPath('data.summary.expense.amount', 400_000)
        ->assertJsonPath('data.summary.balance.amount', 15_100_000);
});

it('returns each report with the same numbers as the web [INV-03] [INV-04] [INV-05]', function () {
    Sanctum::actingAs($this->golden->ibu, ['read']);
    $range = 'from=2026-10-01&to=2026-10-31';

    $this->getJson("/api/v1/reports/cashflow?{$range}")
        ->assertOk()
        ->assertJsonPath('data.rows.0.income.amount', 8_000_000)
        ->assertJsonPath('data.rows.0.closing.amount', GoldenFamily::EXPECTED['total_balance']);

    $categories = $this->getJson("/api/v1/reports/categories?{$range}")->assertOk()->json('data.rows');
    expect(collect($categories)->sum('amount.amount'))->toBe(GoldenFamily::EXPECTED['expense']);

    $members = $this->getJson("/api/v1/reports/members?{$range}")->assertOk()->json('data.rows');
    expect(collect($members)->sum('expense.amount'))->toBe(GoldenFamily::EXPECTED['expense']);

    $this->getJson('/api/v1/reports/categories?type=income&'.$range)->assertJsonPath('data.rows.0.name', 'Gaji');
    $this->getJson('/api/v1/reports/yearly?year=2026')->assertJsonPath('data.rows.peakMonth', 'Okt');
    $this->getJson('/api/v1/reports/unknown')->assertNotFound();
});

it('validates the report range as JSON 422', function () {
    Sanctum::actingAs($this->golden->ibu, ['read']);

    $this->getJson('/api/v1/reports/cashflow?from=2026-10-10&to=2026-10-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});

it('sets budgets in bulk and reads them back with realisation [BR-F01] [BR-F02] [BR-F03]', function () {
    Sanctum::actingAs($this->golden->ayah, ['read', 'write']);
    $food = $this->golden->categories['Makanan']->id;
    $transport = $this->golden->categories['Transportasi']->id;

    $this->putJson('/api/v1/budgets', ['period' => '2026-10', 'budgets' => [
        ['category_id' => $food, 'amount' => '800rb'],
        ['category_id' => $transport, 'amount' => 100_000],
    ]])->assertOk();

    $this->getJson('/api/v1/budgets?period=2026-10')
        ->assertOk()
        ->assertJsonPath('data.budgets.0.categoryName', 'Makanan & Minuman')
        ->assertJsonPath('data.budgets.0.amount', 800_000)
        ->assertJsonPath('data.budgets.0.spent', 650_000)
        ->assertJsonPath('data.budgets.0.remaining', 150_000)
        ->assertJsonPath('data.budgets.0.percentage', 81.3)
        ->assertJsonPath('data.budgets.0.status', 'warning')
        ->assertJsonPath('data.budgets.1.spent', 50_000)
        ->assertJsonPath('data.totals.amount', 900_000)
        ->assertJsonPath('data.totals.spent', 700_000);
});

it('rolls back a bulk budget update when one row breaks a rule [BR-F01]', function () {
    Sanctum::actingAs($this->golden->ayah, ['read', 'write']);

    $this->putJson('/api/v1/budgets', ['period' => '2026-10', 'budgets' => [
        ['category_id' => $this->golden->categories['Makanan']->id, 'amount' => 800_000],
        ['category_id' => $this->golden->categories['Gaji']->id, 'amount' => 100_000],
    ]])->assertUnprocessable()->assertJsonValidationErrors('category_id');

    $this->getJson('/api/v1/budgets?period=2026-10')->assertJsonPath('data.budgets', []);
});

it('requires write ability and the owner/admin role for budgets [BR-B09]', function () {
    Sanctum::actingAs($this->golden->ayah, ['read']);
    $this->putJson('/api/v1/budgets', ['budgets' => [['category_id' => 1, 'amount' => 1]]])->assertForbidden();

    $member = addMember($this->golden->family, FamilyRole::Member);
    Sanctum::actingAs($member, ['read', 'write']);
    $this->putJson('/api/v1/budgets', ['budgets' => [['category_id' => $this->golden->categories['Makanan']->id, 'amount' => 1_000]]])
        ->assertForbidden();
});
