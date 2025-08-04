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
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->json('name'); // translatable field
            $table->string('icon')->nullable(); // FontAwesome or similar icon class
            $table->string('category')->nullable(); // e.g., 'facilities', 'services', 'dining'
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index(['category', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amenities');
    }
};
