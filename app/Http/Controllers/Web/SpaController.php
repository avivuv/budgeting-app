<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the web SPA (frontend/) for every path under /app, so deep links
 * and reloads work; the SPA router takes it from there. The build lives in
 * public/spa (hashed assets under /spa/assets are plain files); the PWA
 * files are answered here so the service worker's scope covers /app.
 * Moves to / when the Inertia pages are gone (docs/18 §4, M5).
 */
class SpaController extends Controller
{
    /** @var array<string, string> PWA files under /app => content type */
    private const array FILES = [
        'sw.js' => 'text/javascript; charset=UTF-8',
        'manifest.webmanifest' => 'application/manifest+json',
        'offline.html' => 'text/html; charset=UTF-8',
    ];

    public function __invoke(?string $path = null): BinaryFileResponse
    {
        $name = array_key_exists((string) $path, self::FILES) ? (string) $path : 'index.html';
        $file = public_path('spa/'.$name);

        abort_unless(is_file($file), 404, 'Aplikasi web belum di-build. Jalankan: pnpm --dir frontend build');

        return response()->file($file, [
            'Content-Type' => self::FILES[$name] ?? 'text/html; charset=UTF-8',
            // The shell names the hashed assets of the latest build: always fetch it fresh.
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
