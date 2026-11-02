<?php

use Illuminate\Support\Facades\File;

/*
| Every consistency invariant in docs/12-testing-strategy.md §2.4 needs at
| least one test that mentions its id, e.g. it('... [INV-04]'). Invariants
| of later roadmap phases are pending; the list must shrink as they ship.
*/

const PENDING_INVARIANTS = [];

function documentedInvariants(): array
{
    preg_match_all('/^\|\s*(INV-\d{2})\s*\|/m', File::get(base_path('docs/12-testing-strategy.md')), $matches);

    return array_values(array_unique($matches[1]));
}

function testedInvariants(): array
{
    $ids = [];

    foreach (File::allFiles(base_path('tests')) as $file) {
        if (str_ends_with($file->getFilename(), 'InvariantCoverageTest.php')) {
            continue;
        }

        preg_match_all('/INV-\d{2}/', $file->getContents(), $matches);
        array_push($ids, ...$matches[0]);
    }

    return array_values(array_unique($ids));
}

it('finds the invariants in the testing strategy', function () {
    expect(documentedInvariants())->toHaveCount(13);
});

it('has a test for every invariant that is not pending', function () {
    expect(array_values(array_diff(documentedInvariants(), testedInvariants(), PENDING_INVARIANTS)))->toBe([]);
});

it('removes invariants from the pending list once they are tested', function () {
    expect(array_values(array_intersect(PENDING_INVARIANTS, testedInvariants())))->toBe([]);
});
