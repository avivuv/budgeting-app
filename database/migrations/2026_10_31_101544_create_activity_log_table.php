<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stored on the separate "activity" connection (budgeting_app_log), so there are
     * no foreign keys to the main database. migrate:fresh only drops the default
     * connection, hence the hasTable guard.
     */
    public function up(): void
    {
        $schema = Schema::connection('activity');

        if ($schema->hasTable('activity_log')) {
            return;
        }

        $schema->create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('family_id')->nullable();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['family_id', 'subject_type', 'subject_id']);
            $table->index(['family_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('activity')->dropIfExists('activity_log');
    }
};
