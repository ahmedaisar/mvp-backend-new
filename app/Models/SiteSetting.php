<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'public',
    ];

    protected $casts = [
        'value' => 'array',
        'public' => 'boolean',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        // Clear cache when settings are modified
        static::saved(function () {
            Cache::forget('site_settings');
            Cache::forget('public_site_settings');
        });
        
        static::deleted(function () {
            Cache::forget('site_settings');
            Cache::forget('public_site_settings');
        });
    }

    /**
     * Scopes
     */
    public function scopePublic($query)
    {
        return $query->where('public', true);
    }

    public function scopePrivate($query)
    {
        return $query->where('public', false);
    }

    /**
     * Static methods
     */
    public static function getValue($key, $default = null)
    {
        $settings = Cache::rememberForever('site_settings', function () {
            return static::pluck('value', 'key')->toArray();
        });

        $value = $settings[$key] ?? $default;
        
        // If value is an array with a single 'value' key, return that value
        if (is_array($value) && count($value) === 1 && isset($value['value'])) {
            return $value['value'];
        }
        
        return $value;
    }

    public static function setValue($key, $value, $type = 'string', $description = null, $public = false)
    {
        // Wrap scalar values in array format
        if (!is_array($value)) {
            $value = ['value' => $value];
        }

        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'description' => $description,
                'public' => $public,
            ]
        );
    }

    public static function getPublicSettings()
    {
        return Cache::rememberForever('public_site_settings', function () {
            return static::public()
                ->pluck('value', 'key')
                ->map(function ($value) {
                    // Unwrap single-value arrays
                    if (is_array($value) && count($value) === 1 && isset($value['value'])) {
                        return $value['value'];
                    }
                    return $value;
                })
                ->toArray();
        });
    }

    public static function getAllSettings()
    {
        return Cache::rememberForever('site_settings', function () {
            return static::pluck('value', 'key')
                ->map(function ($value) {
                    // Unwrap single-value arrays
                    if (is_array($value) && count($value) === 1 && isset($value['value'])) {
                        return $value['value'];
                    }
                    return $value;
                })
                ->toArray();
        });
    }

    /**
     * Accessors
     */
    public function getTypedValueAttribute()
    {
        $value = $this->value;
        
        // Unwrap single-value arrays
        if (is_array($value) && count($value) === 1 && isset($value['value'])) {
            $value = $value['value'];
        }

        switch ($this->type) {
            case 'boolean':
                return (bool) $value;
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'array':
            case 'object':
                return is_array($value) ? $value : json_decode($value, true);
            default:
                return $value;
        }
    }

    public function getDisplayValueAttribute()
    {
        $value = $this->typed_value;
        
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        }
        
        return $value;
    }
}
