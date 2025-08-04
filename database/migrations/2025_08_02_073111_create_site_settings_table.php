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
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value'); // flexible value storage
            $table->string('type', 50)->default('string'); // string, number, boolean, array, object
            $table->text('description')->nullable();
            $table->boolean('public')->default(false); // whether setting can be exposed to frontend
            $table->timestamps();
            
            $table->index(['key', 'public']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
