<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Resort extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

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
     * Media Library Collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(200)
            ->performOnCollections('gallery');

        $this->addMediaConversion('large')
            ->width(1200)
            ->height(800)
            ->performOnCollections('gallery');
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
        
        $media = $this->getFirstMedia('gallery');
        return $media ? $media->getUrl('large') : null;
    }

    public function getGalleryImagesAttribute()
    {
        return $this->getMedia('gallery')->map(function ($media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumb' => $media->getUrl('thumb'),
                'large' => $media->getUrl('large'),
                'alt' => $media->name,
            ];
        });
    }
}
