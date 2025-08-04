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
            // Add new fields for the updated FilamentPHP resource
            $table->string('code')->nullable()->unique()->after('name');
            $table->string('category')->nullable()->after('type');
            $table->string('trigger_event')->nullable()->after('event');
            $table->text('description')->nullable()->after('trigger_event');
            $table->string('from_email')->nullable()->after('subject');
            $table->string('push_title')->nullable()->after('content');
            $table->string('push_icon')->nullable()->after('push_title');
            $table->json('available_variables')->nullable()->after('placeholders');
            $table->json('custom_variables')->nullable()->after('available_variables');
            $table->integer('send_delay_minutes')->default(0)->after('custom_variables');
            $table->enum('send_time_preference', ['immediate', 'business_hours', 'specific_time'])->default('immediate')->after('send_delay_minutes');
            $table->time('preferred_send_time')->nullable()->after('send_time_preference');
            $table->boolean('is_active')->default(true)->after('active');
            $table->boolean('requires_approval')->default(false)->after('is_active');
            $table->string('language', 5)->default('en')->after('requires_approval');
            $table->integer('priority')->default(5)->after('language');
            $table->text('fallback_content')->nullable()->after('priority');
            $table->json('metadata')->nullable()->after('fallback_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('communication_templates', function (Blueprint $table) {
            // Remove the new fields
            $table->dropColumn([
                'code',
                'category',
                'trigger_event',
                'description',
                'from_email',
                'push_title',
                'push_icon',
                'available_variables',
                'custom_variables',
                'send_delay_minutes',
                'send_time_preference',
                'preferred_send_time',
                'is_active',
                'requires_approval',
                'language',
                'priority',
                'fallback_content',
                'metadata',
            ]);
        });
    }
};
