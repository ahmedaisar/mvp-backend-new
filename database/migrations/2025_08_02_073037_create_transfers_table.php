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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resort_id')->constrained()->onDelete('cascade');
            $table->json('name'); // translatable field
            $table->enum('type', ['shared', 'private']);
            $table->string('route'); // e.g., 'Male Airport - Resort'
            $table->decimal('price', 8, 2);
            $table->tinyInteger('capacity')->unsigned();
            $table->json('description')->nullable(); // translatable field
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['resort_id', 'type', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
