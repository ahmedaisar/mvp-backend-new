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
        Schema::table('rate_plans', function (Blueprint $table) {
            $table->json('applicable_countries')->nullable()->after('active');
            $table->json('excluded_countries')->nullable()->after('applicable_countries');
            $table->enum('country_restriction_type', ['none', 'include_only', 'exclude_only'])->default('none')->after('excluded_countries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rate_plans', function (Blueprint $table) {
            $table->dropColumn(['applicable_countries', 'excluded_countries', 'country_restriction_type']);
        });
    }
};
