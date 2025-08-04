<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\CommunicationTemplate;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Convert the subject, content, and fallback_content JSON fields to text, extracting English content
     */
    public function up(): void
    {
        // First, add temporary text columns
        Schema::table('communication_templates', function (Blueprint $table) {
            $table->string('subject_text')->nullable()->after('subject');
            $table->text('content_text')->nullable()->after('content');
            $table->text('fallback_content_text')->nullable()->after('fallback_content');
        });

        // Extract English content from JSON and store in the new columns
        $templates = CommunicationTemplate::all();
        foreach ($templates as $template) {
            $updates = [];
            
            if (!empty($template->subject) && is_array($template->subject)) {
                // Use English subject if available, otherwise use the first available language
                $subjectText = $template->subject['en'] ?? current($template->subject) ?? '';
                $updates['subject_text'] = $subjectText;
            }
            
            if (!empty($template->content) && is_array($template->content)) {
                // Use English content if available, otherwise use the first available language
                $contentText = $template->content['en'] ?? current($template->content) ?? '';
                $updates['content_text'] = $contentText;
            }
            
            if (!empty($template->fallback_content) && is_array($template->fallback_content)) {
                // Use English fallback_content if available, otherwise use the first available language
                $fallbackText = $template->fallback_content['en'] ?? current($template->fallback_content) ?? '';
                $updates['fallback_content_text'] = $fallbackText;
            }
            
            if (!empty($updates)) {
                DB::table('communication_templates')
                    ->where('id', $template->id)
                    ->update($updates);
            }
        }

        // Drop the JSON columns
        Schema::table('communication_templates', function (Blueprint $table) {
            $table->dropColumn(['subject', 'content', 'fallback_content']);
        });

        // Rename the new columns
        Schema::table('communication_templates', function (Blueprint $table) {
            $table->renameColumn('subject_text', 'subject');
            $table->renameColumn('content_text', 'content');
            $table->renameColumn('fallback_content_text', 'fallback_content');
        });
    }

    /**
     * Reverse the migrations.
     * Convert the subject, content, and fallback_content text fields back to JSON
     */
    public function down(): void
    {
        // First, add temporary JSON columns
        Schema::table('communication_templates', function (Blueprint $table) {
            $table->json('subject_json')->nullable()->after('subject');
            $table->json('content_json')->nullable()->after('content');
            $table->json('fallback_content_json')->nullable()->after('fallback_content');
        });

        // Convert text to JSON format
        $templates = DB::table('communication_templates')->get();
        foreach ($templates as $template) {
            $updates = [];
            
            if (!empty($template->subject)) {
                $updates['subject_json'] = json_encode(['en' => $template->subject]);
            }
            
            if (!empty($template->content)) {
                $updates['content_json'] = json_encode(['en' => $template->content]);
            }
            
            if (!empty($template->fallback_content)) {
                $updates['fallback_content_json'] = json_encode(['en' => $template->fallback_content]);
            }
            
            if (!empty($updates)) {
                DB::table('communication_templates')
                    ->where('id', $template->id)
                    ->update($updates);
            }
        }

        // Drop the text columns
        Schema::table('communication_templates', function (Blueprint $table) {
            $table->dropColumn(['subject', 'content', 'fallback_content']);
        });

        // Rename the JSON columns
        Schema::table('communication_templates', function (Blueprint $table) {
            $table->renameColumn('subject_json', 'subject');
            $table->renameColumn('content_json', 'content');
            $table->renameColumn('fallback_content_json', 'fallback_content');
        });
    }
};
