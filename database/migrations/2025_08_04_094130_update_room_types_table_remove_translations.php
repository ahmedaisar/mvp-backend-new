<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get data from the room_types table
        $roomTypes = DB::table('room_types')->get();
        
        // Create temporary column
        Schema::table('room_types', function (Blueprint $table) {
            $table->string('name_temp')->nullable();
        });
        
        // Migrate data from JSON to string
        foreach ($roomTypes as $roomType) {
            $nameData = json_decode($roomType->name, true);
            $name = is_array($nameData) ? ($nameData['en'] ?? '') : $roomType->name;
            
            DB::table('room_types')
                ->where('id', $roomType->id)
                ->update([
                    'name_temp' => $name,
                ]);
        }
        
        // Drop original column and rename temp column
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn('name');
        });
        
        Schema::table('room_types', function (Blueprint $table) {
            $table->renameColumn('name_temp', 'name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a destructive migration (data conversion), 
        // so we don't provide a reliable way to roll back
        Schema::table('room_types', function (Blueprint $table) {
            // Convert string column back to JSON format (default structure)
            $table->json('name')->change();
        });
    }
};
