<?php

/*
| Receipt scanning (docs/06-receipt-scanning.md). Switching the AI provider
| only changes RECEIPT_PARSER; services and handlers stay the same.
*/

return [

    // fake | gemini
    'driver' => env('RECEIPT_PARSER', 'fake'),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    'model' => env('RECEIPT_MODEL', 'gemini-2.5-flash'),

    // Optional second try with another model when the first one is unsure.
    'fallback_model' => env('RECEIPT_FALLBACK_MODEL'),

    'fallback_below_confidence' => 0.6,

    'timeout' => 30,

    // Free tiers are rate limited: ProcessReceiptJob is throttled to this.
    'rate_per_minute' => (int) env('RECEIPT_RATE_PER_MINUTE', 10),

    // BR-H11
    'max_file_mb' => (int) env('RECEIPT_MAX_FILE_MB', 10),
    'max_pdf_pages' => (int) env('RECEIPT_MAX_PDF_PAGES', 3),
    'mime_types' => [
        'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif', 'application/pdf',
    ],

    // Images are shrunk before storing and sending to the AI.
    'max_image_px' => (int) env('RECEIPT_MAX_IMAGE_PX', 1600),
    'jpeg_quality' => 80,

    // BR-H03: Σ items ± discount/tax may differ this much from the total.
    'mismatch_tolerance' => 0.02,

    // BR-H10
    'draft_hours' => 24,
    'delete_files_after_days' => 7,

    // A dedicated "receipts" queue needs a worker listening to it.
    'queue' => env('RECEIPT_QUEUE', 'default'),

];
