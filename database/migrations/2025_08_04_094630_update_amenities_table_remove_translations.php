<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Amenity;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Convert the name JSON field to text, extracting English content
     */
    public function up(): void
    {
        // First, add a temporary text column
        Schema::table('amenities', function (Blueprint $table) {
            $table->string('name_text')->nullable()->after('name');
        });

        // Extract English name from JSON and store in the new column
        $amenities = Amenity::all();
        foreach ($amenities as $amenity) {
            if (!empty($amenity->name) && is_array($amenity->name)) {
                // Use English name if available, otherwise use the first available language
                $text = $amenity->name['en'] ?? current($amenity->name) ?? '';
                DB::table('amenities')
                    ->where('id', $amenity->id)
                    ->update(['name_text' => $text]);
            }
        }

        // Drop the JSON column and rename the new column
        Schema::table('amenities', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('amenities', function (Blueprint $table) {
            $table->renameColumn('name_text', 'name');
        });
    }

    /**
     * Reverse the migrations.
     * Convert the name text field back to JSON
     */
    public function down(): void
    {
        // First, add a temporary JSON column
        Schema::table('amenities', function (Blueprint $table) {
            $table->json('name_json')->nullable()->after('name');
        });

        // Convert text to JSON format
        $amenities = DB::table('amenities')->get();
        foreach ($amenities as $amenity) {
            if (!empty($amenity->name)) {
                DB::table('amenities')
                    ->where('id', $amenity->id)
                    ->update(['name_json' => json_encode(['en' => $amenity->name])]);
            }
        }

        // Drop the text column and rename the new column
        Schema::table('amenities', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('amenities', function (Blueprint $table) {
            $table->renameColumn('name_json', 'name');
        });
    }
};
