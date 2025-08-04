<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Promotion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'resort_id',
        'name',
        'code',
        'description',
        'type',
        'discount_value',
        'valid_from',
        'valid_until',
        'max_uses',
        'current_uses',
        'max_uses_per_customer',
        'applicable_rate_plans',
        'applicable_room_types',
        'min_booking_amount',
        'min_nights',
        'max_discount_amount',
        'blackout_dates',
        'valid_days',
        'customer_segments',
        'active',
        'is_active',
        'is_public',
        'combinable_with_other_promotions',
        'priority',
        'auto_apply',
        'terms_conditions',
        'send_time_preference',
        'preferred_send_time',
        'requires_approval',
        'metadata',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'max_uses' => 'integer',
        'current_uses' => 'integer',
        'max_uses_per_customer' => 'integer',
        'applicable_rate_plans' => 'array',
        'applicable_room_types' => 'array',
        'min_booking_amount' => 'decimal:2',
        'min_nights' => 'integer',
        'max_discount_amount' => 'decimal:2',
        'blackout_dates' => 'array',
        'valid_days' => 'array',
        'customer_segments' => 'array',
        'active' => 'boolean',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'combinable_with_other_promotions' => 'boolean',
        'priority' => 'integer',
        'requires_approval' => 'boolean',
        'preferred_send_time' => 'datetime:H:i',
        'metadata' => 'array',
    ];

    /**
     * Relationships
     */
    public function resort()
    {
        return $this->belongsTo(Resort::class);
    }

    public function resorts()
    {
        return $this->belongsToMany(Resort::class);
    }

    public function roomTypes()
    {
        return $this->belongsToMany(RoomType::class);
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

    public function scopeValid($query, $date = null)
    {
        $date = $date ?: now();
        return $query->where('valid_from', '<=', $date)
                    ->where('valid_until', '>=', $date)
                    ->where(function ($q) {
                        $q->whereNull('max_uses')
                          ->orWhereRaw('current_uses < max_uses');
                    });
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Methods
     */
    public function isValid($bookingAmount = null, $ratePlanId = null)
    {
        // Check if promotion is active
        if (!$this->active) {
            return false;
        }

        // Check date validity
        $now = now();
        if ($this->valid_from > $now || $this->valid_until < $now) {
            return false;
        }

        // Check usage limit
        if ($this->max_uses && $this->current_uses >= $this->max_uses) {
            return false;
        }

        // Check minimum booking amount
        if ($this->min_booking_amount && $bookingAmount < $this->min_booking_amount) {
            return false;
        }

        // Check applicable rate plans
        if ($ratePlanId && $this->applicable_rate_plans) {
            if (!in_array($ratePlanId, $this->applicable_rate_plans)) {
                return false;
            }
        }

        return true;
    }

    public function calculateDiscount($amount)
    {
        if ($this->type === 'percentage') {
            return min($amount * ($this->discount_value / 100), $amount);
        }

        return min($this->discount_value, $amount);
    }

    public function use()
    {
        $this->increment('current_uses');
    }

    public function getDisplayDescriptionAttribute()
    {
        return $this->description;
    }

    public function getFormattedDiscountAttribute()
    {
        if ($this->type === 'percentage') {
            return $this->discount_value . '%';
        }

        return number_format($this->discount_value, 2) . ' USD';
    }
}
