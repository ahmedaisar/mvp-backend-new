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
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resort_id')->constrained()->onDelete('cascade');
            $table->string('code')->index();
            $table->json('name'); // translatable field
            $table->tinyInteger('capacity_adults')->unsigned()->default(2);
            $table->tinyInteger('capacity_children')->unsigned()->default(2);
            $table->decimal('default_price', 10, 2);
            $table->json('images')->nullable(); // array of image URLs
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            
            $table->unique(['resort_id', 'code']);
            $table->index(['resort_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
