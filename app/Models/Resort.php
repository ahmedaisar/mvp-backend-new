<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Resort extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'location',
        'island',
        'atoll',
        'coordinates',
        'contact_email',
        'contact_phone',
        'description',
        'star_rating',
        'resort_type',
        'check_in_time',
        'check_out_time',
        'tax_rules',
        'currency',
        'featured_image',
        'gallery',
        'active',
    ];

    protected $casts = [
        'tax_rules' => 'array',
        'gallery' => 'array',
        'active' => 'boolean',
        'star_rating' => 'integer',
        'check_in_time' => 'datetime:H:i',
        'check_out_time' => 'datetime:H:i',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($resort) {
            if (empty($resort->slug)) {
                $resort->slug = Str::slug($resort->name);
            }
        });
    }

        /**
     * Relationships
     */
    public function roomTypes()
    {
        return $this->hasMany(RoomType::class);
    }

    public function transfers()
    {
        return $this->hasMany(Transfer::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function promotions()
    {
        return $this->hasMany(Promotion::class);
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class, 'resort_amenities');
    }
    
    public function managers()
    {
        return $this->belongsToMany(User::class, 'resort_managers', 'resort_id', 'user_id');
    }
    
    public function resortManagers()
    {
        return $this->hasMany(ResortManager::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByStarRating($query, $rating)
    {
        return $query->where('star_rating', $rating);
    }

    /**
     * Accessors
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function getFeaturedImageUrlAttribute()
    {
        if ($this->featured_image) {
            return asset('storage/' . $this->featured_image);
        }
        
        return null;
    }

    public function getGalleryImagesAttribute()
    {
        if (!$this->gallery || !is_array($this->gallery)) {
            return [];
        }
        
        return collect($this->gallery)->map(function ($imagePath) {
            return [
                'url' => asset('storage/' . $imagePath),
                'path' => $imagePath,
            ];
        })->toArray();
    }
}
