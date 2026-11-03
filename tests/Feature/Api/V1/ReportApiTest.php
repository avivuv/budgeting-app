<?php

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
