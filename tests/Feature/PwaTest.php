<?php

use App\Models\User;

/*
| Installable PWA (docs/07 §1, docs/15 §11): manifest, icons, service worker
| and offline page are static files in public/.
*/

it('ships a valid web app manifest with existing icons', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)->toHaveKeys(['name', 'short_name', 'start_url', 'display', 'icons', 'theme_color'])
        ->and($manifest['display'])->toBe('standalone')
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192', '512x512')
        ->and(collect($manifest['icons'])->pluck('purpose')->filter()->all())->toContain('maskable');

    foreach ([...$manifest['icons'], ...collect($manifest['shortcuts'])->flatMap(fn ($s) => $s['icons'])] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});

it('has a root-scoped service worker that never caches pages or data', function () {
    $worker = (string) file_get_contents(public_path('sw.js'));

    expect($worker)->toContain("'/offline.html'")
        ->and($worker)->toContain("request.mode === 'navigate'")
        ->and(public_path('offline.html'))->toBeFile();
});

it('links the manifest from every page', function () {
    $user = User::factory()->create();
    createFamily($user);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
        ->assertSee('name="theme-color"', false);
});
