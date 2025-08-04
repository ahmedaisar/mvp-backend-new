<?php

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
        Schema::table('communication_templates', function (Blueprint $table) {
            // Remove the unique constraint that prevents having both email and SMS for same event
            $table->dropUnique(['type', 'event']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('communication_templates', function (Blueprint $table) {
            // Add back the original unique constraint
            $table->unique(['type', 'event']);
        });
    }
};
