<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Transfer;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Convert the name and description JSON fields to text, extracting English content
     */
    public function up(): void
    {
        // First, add temporary text columns
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('name_text')->nullable()->after('name');
            $table->text('description_text')->nullable()->after('description');
        });

        // Extract English content from JSON and store in the new columns
        $transfers = Transfer::all();
        foreach ($transfers as $transfer) {
            $updates = [];
            
            if (!empty($transfer->name) && is_array($transfer->name)) {
                // Use English name if available, otherwise use the first available language
                $nameText = $transfer->name['en'] ?? current($transfer->name) ?? '';
                $updates['name_text'] = $nameText;
            }
            
            if (!empty($transfer->description) && is_array($transfer->description)) {
                // Use English description if available, otherwise use the first available language
                $descText = $transfer->description['en'] ?? current($transfer->description) ?? '';
                $updates['description_text'] = $descText;
            }
            
            if (!empty($updates)) {
                DB::table('transfers')
                    ->where('id', $transfer->id)
                    ->update($updates);
            }
        }

        // Drop the JSON columns
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn(['name', 'description']);
        });

        // Rename the new columns
        Schema::table('transfers', function (Blueprint $table) {
            $table->renameColumn('name_text', 'name');
            $table->renameColumn('description_text', 'description');
        });
    }

    /**
     * Reverse the migrations.
     * Convert the name and description text fields back to JSON
     */
    public function down(): void
    {
        // First, add temporary JSON columns
        Schema::table('transfers', function (Blueprint $table) {
            $table->json('name_json')->nullable()->after('name');
            $table->json('description_json')->nullable()->after('description');
        });

        // Convert text to JSON format
        $transfers = DB::table('transfers')->get();
        foreach ($transfers as $transfer) {
            $updates = [];
            
            if (!empty($transfer->name)) {
                $updates['name_json'] = json_encode(['en' => $transfer->name]);
            }
            
            if (!empty($transfer->description)) {
                $updates['description_json'] = json_encode(['en' => $transfer->description]);
            }
            
            if (!empty($updates)) {
                DB::table('transfers')
                    ->where('id', $transfer->id)
                    ->update($updates);
            }
        }

        // Drop the text columns
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn(['name', 'description']);
        });

        // Rename the JSON columns
        Schema::table('transfers', function (Blueprint $table) {
            $table->renameColumn('name_json', 'name');
            $table->renameColumn('description_json', 'description');
        });
    }
};
