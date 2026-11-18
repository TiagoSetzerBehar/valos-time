<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VALO's Time: members create their own routines ("Crear rutina"). A routine is a weekly meeting that
     * belongs to the member who created it and only appears in their calendar.
     */
    public function up(): void
    {
        Schema::table('valo_meetings', function (Blueprint $table): void {
            $table->uuid('created_by_member_id')->nullable();
            $table->foreign('created_by_member_id')
                ->references('id')
                ->on('members')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->index('created_by_member_id');
        });
    }

    public function down(): void
    {
        Schema::table('valo_meetings', function (Blueprint $table): void {
            $table->dropForeign(['created_by_member_id']);
            $table->dropColumn('created_by_member_id');
        });
    }
};
