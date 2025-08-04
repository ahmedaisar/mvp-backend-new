<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Amenity extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'icon',
        'category',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function resorts()
    {
        return $this->belongsToMany(Resort::class, 'resort_amenities');
    }

    public function roomTypes()
    {
        return $this->belongsToMany(RoomType::class, 'room_type_amenities');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Methods
     */
    public function getDisplayNameAttribute()
    {
        return $this->name;
    }

    public function getIconClassAttribute()
    {
        return $this->icon ?: 'fas fa-star';
    }
}
