<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Private uploads disk; null once the file was cleaned up (BR-H10).
            $table->string('file_path')->nullable();
            $table->char('file_hash', 64);
            $table->string('mime_type', 50);
            $table->string('status', 20)->default('pending');
            $table->string('document_type', 20)->nullable();
            // Draft values: the AI reading, then the user's corrections (BR-H09).
            $table->string('merchant', 120)->nullable();
            $table->date('receipt_date')->nullable();
            $table->bigInteger('total')->nullable();
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('tax')->default(0);
            $table->string('payment_method', 50)->nullable();
            $table->foreignId('suggested_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('suggested_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->decimal('confidence', 3, 2)->nullable();
            // Warnings for the review: total_mismatch, date_replaced, possible_duplicate.
            $table->json('flags')->nullable();
            // Untouched AI output for audit (BR-H09).
            $table->json('raw_response')->nullable();
            $table->string('model', 80)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->bigInteger('telegram_chat_id')->nullable();
            $table->bigInteger('telegram_message_id')->nullable();
            $table->timestamps();

            $table->index(['family_id', 'file_hash']);
            $table->index(['family_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('qty', 8, 2)->default(1);
            $table->bigInteger('unit_price')->nullable();
            $table->bigInteger('total');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 30);
            $table->date('period');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['family_id', 'feature', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_logs');
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('receipts');
    }
};
