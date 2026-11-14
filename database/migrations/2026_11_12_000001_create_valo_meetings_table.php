<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VALO's Time: weekly meetings that appear prepared in everyone's calendar and only count after "OK".
     */
    public function up(): void
    {
        Schema::create('valo_meetings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name', 255);
            // ISO weekday: 1 = Monday ... 7 = Sunday
            $table->unsignedTinyInteger('weekday');
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->jsonb('tag_ids')->default('[]');
            // null = every member of the organization
            $table->jsonb('member_ids')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valo_meetings');
    }
};
