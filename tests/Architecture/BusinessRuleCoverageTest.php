<?php

use Illuminate\Support\Facades\File;

/*
| Every business rule in docs/11-business-rules.md needs at least one test that
| mentions its id, e.g. it('...[BR-B08]'). Rules of later roadmap phases are
| listed as pending; the list must shrink as those phases are built.
*/

const PENDING_BUSINESS_RULES = [
    // Phase 2 — dashboard caching
    'BR-C04',
    // Phase 3 — budgets & reports
    'BR-E04', 'BR-F01', 'BR-F02', 'BR-F03', 'BR-F04', 'BR-F05', 'BR-F07',
    // Phase 4 — receipts
    'BR-H01', 'BR-H02', 'BR-H03', 'BR-H04', 'BR-H05', 'BR-H06', 'BR-H07', 'BR-H08', 'BR-H09', 'BR-H10', 'BR-H11',
    // Phase 5 — recurring transactions, data export
    'BR-G01', 'BR-G02', 'BR-G03', 'BR-G04', 'BR-I08',
    // Phase 6 — Telegram bot
    'BR-B10', 'BR-J01', 'BR-J02', 'BR-J03', 'BR-J04', 'BR-J05', 'BR-J06', 'BR-J07', 'BR-J08', 'BR-J09', 'BR-J10',
];

function documentedBusinessRules(): array
{
    preg_match_all('/^\|\s*(BR-[A-Z]\d{2})\s*\|/m', File::get(base_path('docs/11-business-rules.md')), $matches);

    return array_values(array_unique($matches[1]));
}

function testedBusinessRules(): array
{
    $ids = [];

    foreach (File::allFiles(base_path('tests')) as $file) {
        if (str_ends_with($file->getFilename(), 'BusinessRuleCoverageTest.php')) {
            continue;
        }

        preg_match_all('/BR-[A-Z]\d{2}/', $file->getContents(), $matches);
        array_push($ids, ...$matches[0]);
    }

    return array_values(array_unique($ids));
}

it('finds the business rules document', function () {
    expect(count(documentedBusinessRules()))->toBeGreaterThan(50);
});

it('has a test for every business rule that is not pending', function () {
    $untested = array_diff(documentedBusinessRules(), testedBusinessRules(), PENDING_BUSINESS_RULES);

    expect(array_values($untested))->toBe([]);
});

it('removes rules from the pending list once they are tested', function () {
    expect(array_values(array_intersect(PENDING_BUSINESS_RULES, testedBusinessRules())))->toBe([]);
});

it('only lists documented rules as pending', function () {
    expect(array_values(array_diff(PENDING_BUSINESS_RULES, documentedBusinessRules())))->toBe([]);
});
