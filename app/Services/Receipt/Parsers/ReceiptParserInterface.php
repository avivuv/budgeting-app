<?php

declare(strict_types=1);

namespace App\Services\Receipt\Parsers;

use App\DTOs\Receipt\ParsedReceiptData;
use App\DTOs\Receipt\ReceiptParseContextData;
use App\Exceptions\Receipt\ReceiptParserException;

/**
 * Reads a receipt / proof of payment. Every provider returns the same shape
 * (docs/06 §4, §5.4) and passes ReceiptParserContractTest.
 */
interface ReceiptParserInterface
{
    /**
     * @param  string  $filePath  local image (jpg/png/webp/heic) or PDF
     *
     * @throws ReceiptParserException when the provider fails (retried by the job)
     */
    public function parse(string $filePath, string $mimeType, ReceiptParseContextData $context): ParsedReceiptData;
}
