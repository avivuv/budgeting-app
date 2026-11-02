<?php

namespace Database\Factories;

use App\Enums\ReceiptDocumentType;
use App\Enums\ReceiptStatus;
use App\Models\Family;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'file_path' => null,
            'file_hash' => hash('sha256', fake()->uuid()),
            'mime_type' => 'image/jpeg',
            'status' => ReceiptStatus::Pending,
        ];
    }

    /**
     * A draft waiting for the user's confirmation.
     */
    public function parsed(int $total = 87_500, string $merchant = 'Indomaret'): static
    {
        return $this->state(fn (): array => [
            'status' => ReceiptStatus::Parsed,
            'document_type' => ReceiptDocumentType::Receipt,
            'merchant' => $merchant,
            'receipt_date' => now()->toDateString(),
            'total' => $total,
            'confidence' => 0.9,
            'flags' => [],
            'expires_at' => now()->addDay(),
        ]);
    }
}
