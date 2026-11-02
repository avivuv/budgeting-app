<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Wrap both the main and the activity log database in a transaction so
     * RefreshDatabase isolates tests on the two connections.
     *
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'activity'];

    protected function setUp(): void
    {
        parent::setUp();

        // HTTP tests must not depend on a built frontend (public/build manifest).
        $this->withoutVite();
    }

    /**
     * With API_TRACE=1, print every /api request and its response (status +
     * JSON body) to stderr and storage/logs/api-trace.log, so the input and
     * output of API tests can be inspected. Off by default.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        if (filter_var(getenv('API_TRACE'), FILTER_VALIDATE_BOOL) && str_contains($uri, '/api/')) {
            $this->traceApiCall($method, $uri, $parameters, $files, $content, $response);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $files
     */
    private function traceApiCall(string $method, string $uri, array $parameters, array $files, mixed $content, TestResponse $response): void
    {
        $pretty = fn (mixed $value): string => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';

        $input = is_string($content) && $content !== '' ? json_decode($content, true) ?? $content : $parameters;

        if (is_array($input)) {
            array_walk_recursive($input, function (mixed &$value): void {
                if ($value instanceof UploadedFile) {
                    $value = "[file: {$value->getClientOriginalName()}, {$value->getSize()} bytes]";
                }
            });
        }
        $body = $response->baseResponse->getContent();
        $output = is_string($body) ? json_decode($body, true) ?? $body : null;

        $test = method_exists(static::class, 'getLatestPrintableTestCaseMethodName')
            ? static::getLatestPrintableTestCaseMethodName()
            : $this->name();

        $lines = [
            str_repeat('─', 72),
            'TEST    '.$test,
            'REQUEST '.strtoupper($method).' '.preg_replace('#^https?://[^/]+#', '', $uri),
        ];

        if ($input !== [] && $input !== null) {
            $lines[] = 'INPUT   '.$pretty($input);
        }

        if ($files !== []) {
            $lines[] = 'FILES   '.implode(', ', array_map(
                fn (mixed $file): string => $file instanceof UploadedFile ? $file->getClientOriginalName() : get_debug_type($file),
                $files,
            ));
        }

        $lines[] = 'STATUS  '.$response->getStatusCode();
        $lines[] = 'OUTPUT  '.(is_string($output) ? $output : $pretty($output));

        $text = implode(PHP_EOL, $lines).PHP_EOL;

        fwrite(STDERR, $text);
        file_put_contents(storage_path('logs/api-trace.log'), $text, FILE_APPEND);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
