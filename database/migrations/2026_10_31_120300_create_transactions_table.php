<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->unsignedBigInteger('amount');
            $table->date('date');
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('to_wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('note')->nullable();
            $table->string('merchant')->nullable();
            $table->string('source', 20)->default('web');
            $table->unsignedBigInteger('receipt_id')->nullable();
            $table->unsignedBigInteger('recurring_rule_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['family_id', 'date']);
            $table->index(['family_id', 'category_id', 'date']);
            $table->index(['family_id', 'wallet_id']);
            $table->index(['family_id', 'to_wallet_id']);
            $table->index(['family_id', 'created_by']);
        });

        Schema::create('transaction_tag', function (Blueprint $table) {
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['transaction_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_tag');
        Schema::dropIfExists('transactions');
    }
};
