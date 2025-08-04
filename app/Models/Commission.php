<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Commission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'agent_code',
        'contact_email',
        'contact_name',
        'contact_phone',
        'commission_type',
        'commission_rate',
        'fixed_amount',
        'applicable_resorts',
        'applicable_room_types',
        'valid_from',
        'valid_until',
        'minimum_booking_value',
        'minimum_nights',
        'payment_frequency',
        'terms_and_conditions',
        'active',
    ];

    protected $casts = [
        'applicable_resorts' => 'array',
        'applicable_room_types' => 'array',
        'terms_and_conditions' => 'array',
        'commission_rate' => 'decimal:2',
        'fixed_amount' => 'decimal:2',
        'minimum_booking_value' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'active' => 'boolean',
        'minimum_nights' => 'integer',
    ];

    /**
     * Calculate commission for a booking
     */
    public function calculateCommission(float $bookingValue, int $nights = 1): float
    {
        if (!$this->active) {
            return 0;
        }

        if ($bookingValue < $this->minimum_booking_value) {
            return 0;
        }

        if ($nights < $this->minimum_nights) {
            return 0;
        }

        if ($this->commission_type === 'percentage') {
            return $bookingValue * ($this->commission_rate / 100);
        }

        if ($this->commission_type === 'fixed_amount' && $this->fixed_amount) {
            return $this->fixed_amount;
        }

        return 0;
    }

    /**
     * Check if commission is valid for the given date
     */
    public function isValidForDate(\Carbon\Carbon $date): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($date < $this->valid_from) {
            return false;
        }

        if ($this->valid_until && $date > $this->valid_until) {
            return false;
        }

        return true;
    }

    /**
     * Check if commission applies to specific resort
     */
    public function appliesTo(int $resortId, int $roomTypeId = null): bool
    {
        if (!$this->active) {
            return false;
        }

        // If no specific resorts set, applies to all
        if (empty($this->applicable_resorts)) {
            return true;
        }

        // Check resort applicability
        if (!in_array($resortId, $this->applicable_resorts)) {
            return false;
        }

        // If room type specified, check room type applicability
        if ($roomTypeId && !empty($this->applicable_room_types)) {
            return in_array($roomTypeId, $this->applicable_room_types);
        }

        return true;
    }

    /**
     * Get applicable resorts
     */
    public function resorts()
    {
        if (empty($this->applicable_resorts)) {
            return Resort::all();
        }

        return Resort::whereIn('id', $this->applicable_resorts)->get();
    }

    /**
     * Get applicable room types
     */
    public function roomTypes()
    {
        if (empty($this->applicable_room_types)) {
            return RoomType::all();
        }

        return RoomType::whereIn('id', $this->applicable_room_types)->get();
    }

    /**
     * Scope for active commissions
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for commissions valid on a specific date
     */
    public function scopeValidOn($query, \Carbon\Carbon $date)
    {
        return $query->where('valid_from', '<=', $date)
                    ->where(function ($q) use ($date) {
                        $q->whereNull('valid_until')
                          ->orWhere('valid_until', '>=', $date);
                    });
    }

    /**
     * Find commission by agent code
     */
    public static function findByAgentCode(string $agentCode): ?self
    {
        return static::where('agent_code', $agentCode)
                    ->active()
                    ->first();
    }
}
