<?php

namespace App\Http\Controllers;

use App\Models\Resort;
use App\Models\SiteSetting;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Exception;

class ContentManagementController extends Controller
{
    /**
     * Get all site settings
     */
    public function getSettings(Request $request)
    {
        try {
            $category = $request->query('category');
            $cached = $request->query('cached', true);

            $cacheKey = "site_settings_" . ($category ?? 'all');
            
            if ($cached) {
                $settings = Cache::remember($cacheKey, now()->addHours(1), function () use ($category) {
                    return $this->fetchSettings($category);
                });
            } else {
                $settings = $this->fetchSettings($category);
            }

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update site setting
     */
    public function updateSetting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|max:255',
            'value' => 'required',
            'type' => 'nullable|in:string,integer,boolean,json,text',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $key = $request->input('key');
            $value = $request->input('value');
            $type = $request->input('type', 'string');
            $category = $request->input('category', 'general');
            $description = $request->input('description');

            // Process value based on type
            $processedValue = $this->processSettingValue($value, $type);

            // Update or create setting
            $setting = SiteSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $processedValue,
                    'type' => $type,
                    'category' => $category,
                    'description' => $description,
                    'updated_by' => auth()->id(),
                ]
            );

            // Clear relevant caches
            $this->clearSettingsCache($category);

            // Log the change
            AuditLog::log('setting_updated', $setting, auth()->user(), [
                'key' => $key,
                'old_value' => $setting->getOriginal('value'),
                'new_value' => $processedValue,
                'category' => $category,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Setting updated successfully',
                'data' => [
                    'key' => $key,
                    'value' => $this->formatSettingValue($processedValue, $type),
                    'type' => $type,
                    'category' => $category,
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating setting: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update settings
     */
    public function bulkUpdateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|max:255',
            'settings.*.value' => 'required',
            'settings.*.type' => 'nullable|in:string,integer,boolean,json,text',
            'settings.*.category' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $settings = $request->input('settings');
            $updated = [];
            $errors = [];
            $categories = [];

            foreach ($settings as $settingData) {
                try {
                    $key = $settingData['key'];
                    $value = $settingData['value'];
                    $type = $settingData['type'] ?? 'string';
                    $category = $settingData['category'] ?? 'general';

                    $processedValue = $this->processSettingValue($value, $type);

                    $setting = SiteSetting::updateOrCreate(
                        ['key' => $key],
                        [
                            'value' => $processedValue,
                            'type' => $type,
                            'category' => $category,
                            'updated_by' => auth()->id(),
                        ]
                    );

                    $updated[] = [
                        'key' => $key,
                        'value' => $this->formatSettingValue($processedValue, $type),
                        'type' => $type,
                        'category' => $category,
                    ];

                    $categories[] = $category;

                } catch (Exception $e) {
                    $errors[] = [
                        'key' => $settingData['key'],
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Clear caches for affected categories
            foreach (array_unique($categories) as $category) {
                $this->clearSettingsCache($category);
            }

            // Log bulk update
            AuditLog::log('settings_bulk_updated', null, auth()->user(), [
                'updated_count' => count($updated),
                'error_count' => count($errors),
                'categories' => array_unique($categories),
            ]);

            return response()->json([
                'success' => count($updated) > 0,
                'message' => count($updated) . ' settings updated successfully',
                'data' => [
                    'updated' => $updated,
                    'errors' => $errors,
                    'total_updated' => count($updated),
                    'total_errors' => count($errors),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error bulk updating settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete setting
     */
    public function deleteSetting(Request $request, $key)
    {
        try {
            $setting = SiteSetting::where('key', $key)->first();

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting not found',
                ], 404);
            }

            $category = $setting->category;
            $oldValue = $setting->value;

            $setting->delete();

            // Clear relevant caches
            $this->clearSettingsCache($category);

            // Log the deletion
            AuditLog::log('setting_deleted', null, auth()->user(), [
                'key' => $key,
                'old_value' => $oldValue,
                'category' => $category,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Setting deleted successfully',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting setting: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get page content for dynamic pages
     */
    public function getPageContent(Request $request, $slug)
    {
        try {
            $cacheKey = "page_content_{$slug}";
            
            $content = Cache::remember($cacheKey, now()->addHours(2), function () use ($slug) {
                return SiteSetting::where('key', "page.{$slug}")
                    ->orWhere('key', "content.{$slug}")
                    ->first();
            });

            if (!$content) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page content not found',
                ], 404);
            }

            $pageData = [
                'slug' => $slug,
                'content' => $this->formatSettingValue($content->value, $content->type),
                'type' => $content->type,
                'last_updated' => $content->updated_at->toISOString(),
            ];

            // Get additional page metadata if available
            $metaSettings = SiteSetting::where('key', 'LIKE', "page.{$slug}.%")
                ->get()
                ->keyBy(function($item) {
                    return str_replace("page.{$item->slug}.", '', $item->key);
                });

            if ($metaSettings->count() > 0) {
                $pageData['meta'] = [];
                foreach ($metaSettings as $key => $setting) {
                    $pageData['meta'][$key] = $this->formatSettingValue($setting->value, $setting->type);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $pageData,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving page content: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update page content
     */
    public function updatePageContent(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required',
            'type' => 'nullable|in:string,text,json,html',
            'meta' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $content = $request->input('content');
            $type = $request->input('type', 'html');
            $meta = $request->input('meta', []);

            $processedContent = $this->processSettingValue($content, $type);

            // Update main content
            $setting = SiteSetting::updateOrCreate(
                ['key' => "page.{$slug}"],
                [
                    'value' => $processedContent,
                    'type' => $type,
                    'category' => 'pages',
                    'description' => "Content for page: {$slug}",
                    'updated_by' => auth()->id(),
                ]
            );

            // Update meta data
            foreach ($meta as $metaKey => $metaValue) {
                if (is_string($metaKey) && !empty($metaValue)) {
                    SiteSetting::updateOrCreate(
                        ['key' => "page.{$slug}.{$metaKey}"],
                        [
                            'value' => $metaValue,
                            'type' => 'string',
                            'category' => 'pages',
                            'description' => "Meta {$metaKey} for page: {$slug}",
                            'updated_by' => auth()->id(),
                        ]
                    );
                }
            }

            // Clear cache
            Cache::forget("page_content_{$slug}");
            $this->clearSettingsCache('pages');

            // Log the update
            AuditLog::log('page_content_updated', $setting, auth()->user(), [
                'slug' => $slug,
                'type' => $type,
                'has_meta' => !empty($meta),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Page content updated successfully',
                'data' => [
                    'slug' => $slug,
                    'content' => $this->formatSettingValue($processedContent, $type),
                    'type' => $type,
                    'meta' => $meta,
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating page content: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get menu configuration
     */
    public function getMenus(Request $request)
    {
        try {
            $location = $request->query('location', 'main'); // main, footer, mobile
            
            $cacheKey = "menu_{$location}";
            
            $menu = Cache::remember($cacheKey, now()->addHours(1), function () use ($location) {
                $menuSetting = SiteSetting::where('key', "menu.{$location}")->first();
                
                if (!$menuSetting) {
                    return $this->getDefaultMenu($location);
                }

                return json_decode($menuSetting->value, true);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'location' => $location,
                    'menu' => $menu,
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving menu: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update menu configuration
     */
    public function updateMenu(Request $request, $location)
    {
        $validator = Validator::make($request->all(), [
            'menu' => 'required|array',
            'menu.*.label' => 'required|string|max:100',
            'menu.*.url' => 'required|string|max:255',
            'menu.*.order' => 'nullable|integer',
            'menu.*.children' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $menu = $request->input('menu');
            
            // Sort menu items by order
            usort($menu, function($a, $b) {
                return ($a['order'] ?? 0) - ($b['order'] ?? 0);
            });

            // Update menu setting
            SiteSetting::updateOrCreate(
                ['key' => "menu.{$location}"],
                [
                    'value' => json_encode($menu),
                    'type' => 'json',
                    'category' => 'navigation',
                    'description' => "Menu configuration for {$location}",
                    'updated_by' => auth()->id(),
                ]
            );

            // Clear cache
            Cache::forget("menu_{$location}");
            $this->clearSettingsCache('navigation');

            // Log the update
            AuditLog::log('menu_updated', null, auth()->user(), [
                'location' => $location,
                'item_count' => count($menu),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Menu updated successfully',
                'data' => [
                    'location' => $location,
                    'menu' => $menu,
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating menu: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get resort content (descriptions, amenities, etc.)
     */
    public function getResortContent(Request $request, $resortId)
    {
        try {
            $resort = Resort::findOrFail($resortId);
            
            $cacheKey = "resort_content_{$resortId}";
            
            $content = Cache::remember($cacheKey, now()->addHours(1), function () use ($resortId) {
                return SiteSetting::where('key', 'LIKE', "resort.{$resortId}.%")
                    ->get()
                    ->mapWithKeys(function($setting) use ($resortId) {
                        $key = str_replace("resort.{$resortId}.", '', $setting->key);
                        return [$key => $this->formatSettingValue($setting->value, $setting->type)];
                    });
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'resort_id' => $resortId,
                    'resort_name' => $resort->name,
                    'content' => $content,
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving resort content: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear settings cache
     */
    protected function clearSettingsCache($category = null)
    {
        if ($category) {
            Cache::forget("site_settings_{$category}");
        }
        Cache::forget('site_settings_all');
    }

    /**
     * Fetch settings from database
     */
    protected function fetchSettings($category = null)
    {
        $query = SiteSetting::query();
        
        if ($category) {
            $query->where('category', $category);
        }

        return $query->get()->mapWithKeys(function($setting) {
            return [$setting->key => [
                'value' => $this->formatSettingValue($setting->value, $setting->type),
                'type' => $setting->type,
                'category' => $setting->category,
                'description' => $setting->description,
                'updated_at' => $setting->updated_at->toISOString(),
            ]];
        });
    }

    /**
     * Process setting value based on type
     */
    protected function processSettingValue($value, $type)
    {
        return match($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value
        };
    }

    /**
     * Format setting value for output
     */
    protected function formatSettingValue($value, $type)
    {
        return match($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value
        };
    }

    /**
     * Get default menu structure
     */
    protected function getDefaultMenu($location)
    {
        return match($location) {
            'main' => [
                ['label' => 'Home', 'url' => '/', 'order' => 1],
                ['label' => 'Resorts', 'url' => '/resorts', 'order' => 2],
                ['label' => 'About', 'url' => '/about', 'order' => 3],
                ['label' => 'Contact', 'url' => '/contact', 'order' => 4],
            ],
            'footer' => [
                ['label' => 'Privacy Policy', 'url' => '/privacy', 'order' => 1],
                ['label' => 'Terms of Service', 'url' => '/terms', 'order' => 2],
                ['label' => 'Support', 'url' => '/support', 'order' => 3],
            ],
            default => []
        };
    }
}
