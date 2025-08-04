<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'resort_id',
        'code',
        'name',
        'capacity_adults',
        'capacity_children',
        'default_price',
        'images',
        'active',
    ];

    protected $casts = [
        'images' => 'array',
        'active' => 'boolean',
        'capacity_adults' => 'integer',
        'capacity_children' => 'integer',
        'default_price' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function resort()
    {
        return $this->belongsTo(Resort::class);
    }

    public function ratePlans()
    {
        return $this->hasMany(RatePlan::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class, 'room_type_amenities');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByCapacity($query, $adults, $children = 0)
    {
        return $query->where('capacity_adults', '>=', $adults)
                    ->where('capacity_children', '>=', $children);
    }

    /**
     * Accessors
     */
    public function getTotalCapacityAttribute()
    {
        return $this->capacity_adults + $this->capacity_children;
    }

    public function getFormattedPriceAttribute()
    {
        return number_format($this->default_price, 2) . ' ' . $this->resort->currency;
    }
}
