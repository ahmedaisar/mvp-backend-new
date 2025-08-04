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
        Schema::table('audit_logs', function (Blueprint $table) {
            // Add missing fields that exist in AuditLogResource but not in database
            $table->string('user_type', 50)->nullable()->after('user_id');
            $table->renameColumn('auditable_type', 'model_type');
            $table->renameColumn('auditable_id', 'model_id');
            $table->enum('event_type', [
                'authentication', 'authorization', 'data_access', 'data_modification',
                'system_configuration', 'user_management', 'booking_management',
                'payment_processing', 'report_generation', 'api_access',
                'security_event', 'error'
            ])->nullable()->after('action');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium')->after('event_type');
            $table->text('description')->nullable()->after('severity');
            $table->string('url', 500)->nullable()->after('user_agent');
            $table->string('method', 10)->nullable()->after('url');
            $table->string('session_id', 100)->nullable()->after('method');
            $table->json('metadata')->nullable()->after('new_values');
            
            // Update existing columns to match resource expectations
            $table->string('ip_address', 45)->nullable()->change();
            $table->text('user_agent')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Remove added fields
            $table->dropColumn([
                'user_type', 'event_type', 'severity', 'description',
                'url', 'method', 'session_id', 'metadata'
            ]);
            
            // Revert column renames
            $table->renameColumn('model_type', 'auditable_type');
            $table->renameColumn('model_id', 'auditable_id');
        });
    }
};
