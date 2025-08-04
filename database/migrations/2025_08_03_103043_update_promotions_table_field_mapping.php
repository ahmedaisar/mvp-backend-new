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
        Schema::table('promotions', function (Blueprint $table) {
            // Add missing fields that exist in PromotionResource but not in database
            $table->string('name', 100)->after('resort_id');
            $table->renameColumn('discount_type', 'type');
            $table->renameColumn('start_date', 'valid_from');
            $table->renameColumn('end_date', 'valid_until');
            $table->boolean('is_active')->default(true)->after('active');
            $table->boolean('is_public')->default(false)->after('is_active');
            $table->boolean('combinable_with_other_promotions')->default(false)->after('is_public');
            $table->integer('priority')->default(0)->after('combinable_with_other_promotions');
            $table->enum('auto_apply', ['none', 'best_discount', 'always'])->default('none')->after('priority');
            $table->text('terms_conditions')->nullable()->after('auto_apply');
            $table->json('metadata')->nullable()->after('terms_conditions');
            
            // Add missing fields for comprehensive promotion management
            $table->integer('min_nights')->default(1)->after('min_booking_amount');
            $table->integer('max_uses_per_customer')->default(1)->after('max_uses');
            $table->decimal('max_discount_amount', 10, 2)->nullable()->after('min_nights');
            $table->json('blackout_dates')->nullable()->after('max_discount_amount');
            $table->json('valid_days')->nullable()->after('blackout_dates'); // days of week
            $table->json('customer_segments')->nullable()->after('valid_days');
            $table->json('applicable_room_types')->nullable()->after('applicable_rate_plans');
            $table->enum('send_time_preference', ['immediate', 'business_hours', 'specific_time'])->default('immediate')->after('customer_segments');
            $table->time('preferred_send_time')->nullable()->after('send_time_preference');
            $table->boolean('requires_approval')->default(false)->after('preferred_send_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            // Remove added fields
            $table->dropColumn([
                'name', 'is_active', 'is_public', 'combinable_with_other_promotions',
                'priority', 'auto_apply', 'terms_conditions', 'metadata',
                'min_nights', 'max_uses_per_customer', 'max_discount_amount',
                'blackout_dates', 'valid_days', 'customer_segments',
                'applicable_room_types', 'send_time_preference', 'preferred_send_time',
                'requires_approval'
            ]);
            
            // Revert column renames
            $table->renameColumn('type', 'discount_type');
            $table->renameColumn('valid_from', 'start_date');
            $table->renameColumn('valid_until', 'end_date');
        });
    }
};
