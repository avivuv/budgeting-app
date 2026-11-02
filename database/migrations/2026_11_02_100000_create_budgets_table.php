<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            // First day of the label month (e.g. 2026-11-01 for "Nov 2026"), so a budget
            // keeps its period when the family changes period_start_day (BR-D02).
            $table->date('period');
            $table->bigInteger('amount');
            $table->timestamp('alert_80_sent_at')->nullable();
            $table->timestamp('alert_100_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['family_id', 'category_id', 'period']);
            $table->index(['family_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
