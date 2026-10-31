<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug')->unique();
            $table->char('currency', 3)->default('IDR');
            $table->string('timezone')->default('Asia/Jakarta');
            $table->unsignedTinyInteger('period_start_day')->default(1);
            $table->unsignedInteger('receipt_quota_monthly')->default(50);
            $table->json('settings')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('family_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['family_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_family_id')->nullable()->after('appearance')
                ->constrained('families')->nullOnDelete();
            $table->boolean('is_super_admin')->default(false)->after('current_family_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_family_id');
            $table->dropColumn('is_super_admin');
        });

        Schema::dropIfExists('family_user');
        Schema::dropIfExists('families');
    }
};
