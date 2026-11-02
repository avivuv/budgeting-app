<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every access to uploaded files goes through here, so moving from the local
 * disk to MinIO/S3 is a config change only (docs/06 §7). The uploads disk is
 * always private.
 */
class FileStorageService
{
    /**
     * Store a file under receipts/{family}/{yyyy}/{mm}/{uuid}.{ext}.
     */
    public function storeReceipt(int $familyId, string $localPath, string $extension): string
    {
        $path = sprintf('receipts/%d/%s/%s.%s', $familyId, now()->format('Y/m'), Str::uuid()->toString(), $extension);

        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException("Cannot read upload {$localPath}");
        }

        try {
            $this->disk()->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            $this->disk()->delete($path);
        }
    }

    public function exists(?string $path): bool
    {
        return $path !== null && $this->disk()->exists($path);
    }

    public function response(string $path, string $mimeType): StreamedResponse
    {
        return $this->disk()->response($path, null, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Run a callback with a local copy of the file (remote disks are downloaded
     * to a temporary file first).
     *
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    public function withLocalFile(string $path, callable $callback): mixed
    {
        $temp = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($temp, $this->disk()->get($path));

        try {
            return $callback($temp);
        } finally {
            @unlink($temp);
        }
    }

    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk(config('filesystems.uploads'));
    }
}
