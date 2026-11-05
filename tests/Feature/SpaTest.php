<?php

use Illuminate\Support\Facades\File;

/*
 * The web SPA is served under /app (docs/18 §4): every page path answers the
 * built shell, and the PWA files come from the same build. Uses a stand-in
 * build so the test does not depend on running "pnpm --dir frontend build".
 */

beforeEach(function () {
    $this->build = public_path('spa');
    $this->hadBuild = File::isDirectory($this->build);

    if (! $this->hadBuild) {
        File::ensureDirectoryExists($this->build);
        File::put($this->build.'/index.html', '<!doctype html><div id="app"></div>');
        File::put($this->build.'/sw.js', '/* sw */');
        File::put($this->build.'/manifest.webmanifest', '{"start_url":"/app/"}');
    }
});

afterEach(function () {
    if (! $this->hadBuild) {
        File::deleteDirectory($this->build);
    }
});

it('answers every page path under /app with the SPA shell, never cached', function (string $path) {
    $response = $this->get($path)->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/html')
        ->and($response->headers->get('cache-control'))->toContain('no-store')
        ->and($response->getFile()->getPathname())->toBe(public_path('spa/index.html'));
})->with(['/app', '/app/', '/app/login', '/app/register', '/app/transactions/12/edit']);

it('serves the PWA files under /app so the service worker covers the pages', function () {
    $sw = $this->get('/app/sw.js')->assertOk();
    expect($sw->headers->get('content-type'))->toContain('javascript')
        ->and($sw->getFile()->getPathname())->toBe(public_path('spa/sw.js'));

    $this->get('/app/manifest.webmanifest')->assertOk()->assertHeader('content-type', 'application/manifest+json');
});

it('does not touch the old pages and the API', function () {
    $this->get('/register')->assertOk()->assertSee('data-page', false);
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('answers 404 while the SPA is not built', function () {
    File::move(public_path('spa/index.html'), public_path('spa/index.html.bak'));

    try {
        $this->get('/app/login')->assertNotFound();
    } finally {
        File::move(public_path('spa/index.html.bak'), public_path('spa/index.html'));
    }
});
