<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Resort;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Convert the description JSON field to text, extracting English content
     */
    public function up(): void
    {
        // First, add a temporary text column
        Schema::table('resorts', function (Blueprint $table) {
            $table->text('description_text')->nullable()->after('description');
        });

        // Extract English description from JSON and store in the new column
        $resorts = Resort::all();
        foreach ($resorts as $resort) {
            if (!empty($resort->description) && is_array($resort->description)) {
                // Use English description if available, otherwise use the first available language
                $text = $resort->description['en'] ?? current($resort->description) ?? '';
                DB::table('resorts')
                    ->where('id', $resort->id)
                    ->update(['description_text' => $text]);
            }
        }

        // Drop the JSON column and rename the new column
        Schema::table('resorts', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('resorts', function (Blueprint $table) {
            $table->renameColumn('description_text', 'description');
        });
    }

    /**
     * Reverse the migrations.
     * Convert the description text field back to JSON
     */
    public function down(): void
    {
        // First, add a temporary JSON column
        Schema::table('resorts', function (Blueprint $table) {
            $table->json('description_json')->nullable()->after('description');
        });

        // Convert text to JSON format
        $resorts = DB::table('resorts')->get();
        foreach ($resorts as $resort) {
            if (!empty($resort->description)) {
                DB::table('resorts')
                    ->where('id', $resort->id)
                    ->update(['description_json' => json_encode(['en' => $resort->description])]);
            }
        }

        // Drop the text column and rename the new column
        Schema::table('resorts', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('resorts', function (Blueprint $table) {
            $table->renameColumn('description_json', 'description');
        });
    }
};
