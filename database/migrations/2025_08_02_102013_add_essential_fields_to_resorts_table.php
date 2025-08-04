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
        Schema::table('resorts', function (Blueprint $table) {
            // Location details - essential for Maldives resorts
            $table->string('island')->after('location');
            $table->string('atoll')->after('island');
            $table->string('coordinates')->nullable()->after('atoll');
            
            // Contact information
            $table->string('contact_email')->after('coordinates');
            $table->string('contact_phone')->after('contact_email');
            
            // Resort classification
            $table->enum('resort_type', ['resort', 'hotel', 'villa', 'guesthouse'])
                  ->default('resort')
                  ->after('star_rating');
            
            // Check-in/Check-out times
            $table->time('check_in_time')->default('14:00')->after('resort_type');
            $table->time('check_out_time')->default('12:00')->after('check_in_time');
            
            // Media gallery (JSON field for multiple images)
            $table->json('gallery')->nullable()->after('featured_image');
            
            // Additional indexes for better performance
            $table->index(['island', 'atoll']);
            $table->index('resort_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resorts', function (Blueprint $table) {
            $table->dropIndex(['island', 'atoll']);
            $table->dropIndex(['resort_type']);
            
            $table->dropColumn([
                'island',
                'atoll', 
                'coordinates',
                'contact_email',
                'contact_phone',
                'resort_type',
                'check_in_time',
                'check_out_time',
                'gallery'
            ]);
        });
    }
};
