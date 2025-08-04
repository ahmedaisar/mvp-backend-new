<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'resort_id',
        'name',
        'type',
        'route',
        'price',
        'capacity',
        'description',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'capacity' => 'integer',
        'active' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function resort()
    {
        return $this->belongsTo(Resort::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByResort($query, $resortId)
    {
        return $query->where('resort_id', $resortId);
    }

    /**
     * Accessors
     */
    public function getFormattedPriceAttribute()
    {
        return number_format($this->price, 2) . ' USD';
    }

    public function getDisplayNameAttribute()
    {
        return $this->name;
    }
}
