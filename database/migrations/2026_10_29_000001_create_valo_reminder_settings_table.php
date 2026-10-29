<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('valo_reminder_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->unique();
            $table->boolean('daily_enabled')->default(false);
            $table->string('daily_time', 5)->default('18:00');
            $table->boolean('weekly_enabled')->default(false);
            $table->unsignedSmallInteger('expected_weekly_hours')->default(20);
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('valo_reminder_settings');
    }
};
