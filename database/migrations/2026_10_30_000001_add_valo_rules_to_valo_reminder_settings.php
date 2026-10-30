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
        Schema::table('valo_reminder_settings', function (Blueprint $table): void {
            $table->boolean('member_weekly_enabled')->default(false);
            $table->boolean('require_project')->default(false);
            $table->boolean('require_description')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('valo_reminder_settings', function (Blueprint $table): void {
            $table->dropColumn(['member_weekly_enabled', 'require_project', 'require_description']);
        });
    }
};
