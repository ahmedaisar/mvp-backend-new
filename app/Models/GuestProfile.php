<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GuestProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'email',
        'full_name',
        'phone',
        'country',
        'preferences',
        'date_of_birth',
        'gender',
    ];

    protected $casts = [
        'preferences' => 'array',
        'date_of_birth' => 'date',
    ];

    /**
     * Relationships
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    /**
     * Scopes
     */
    public function scopeByCountry($query, $country)
    {
        return $query->where('country', $country);
    }

    public function scopeRecentGuests($query, $days = 30)
    {
        return $query->whereHas('bookings', function ($q) use ($days) {
            $q->where('created_at', '>=', now()->subDays($days));
        });
    }

    /**
     * Accessors
     */
    public function getAgeAttribute()
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    public function getFirstNameAttribute()
    {
        return explode(' ', $this->full_name)[0];
    }

    public function getLastNameAttribute()
    {
        $parts = explode(' ', $this->full_name);
        return count($parts) > 1 ? end($parts) : '';
    }

    public function getTotalBookingsAttribute()
    {
        return $this->bookings()->count();
    }

    public function getTotalSpentAttribute()
    {
        return $this->bookings()
                   ->where('status', 'completed')
                   ->sum('total_price_usd');
    }

    /**
     * Methods
     */
    public function getPreference($key, $default = null)
    {
        return $this->preferences[$key] ?? $default;
    }

    public function setPreference($key, $value)
    {
        $preferences = $this->preferences ?? [];
        $preferences[$key] = $value;
        $this->preferences = $preferences;
        $this->save();
    }

    public function isVipGuest()
    {
        return $this->total_bookings >= 3 || $this->total_spent >= 10000;
    }
}
