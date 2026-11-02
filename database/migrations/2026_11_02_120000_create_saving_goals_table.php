<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saving_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->bigInteger('target_amount');
            // Linked wallet: its balance is the progress (BR-K01). Otherwise saved_amount.
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('saved_amount')->default(0);
            $table->date('deadline')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 20)->nullable();
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();

            $table->index('family_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saving_goals');
    }
};
