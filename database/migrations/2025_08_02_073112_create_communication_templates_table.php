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
        Schema::create('communication_templates', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['email', 'sms']);
            $table->string('name', 100);
            $table->string('event', 100); // booking_confirmed, payment_received, etc.
            $table->json('subject')->nullable(); // translatable field (for emails)
            $table->json('content'); // translatable field
            $table->json('placeholders')->nullable(); // available variables like {guest_name}, {booking_id}
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index(['type', 'event', 'active']);
            $table->unique(['type', 'event']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_templates');
    }
};
