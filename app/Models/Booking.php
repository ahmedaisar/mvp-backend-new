<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_reference',
        'user_id',
        'guest_profile_id',
        'resort_id',
        'room_type_id',
        'rate_plan_id',
        'check_in',
        'check_out',
        'nights',
        'adults',
        'children',
        'subtotal_usd',
        'total_price_usd',
        'currency_rate_usd',
        'promotion_id',
        'discount_amount',
        'transfer_id',
        'commission_id',
        'status',
        'special_requests',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'nights' => 'integer',
        'adults' => 'integer',
        'children' => 'integer',
        'subtotal_usd' => 'decimal:2',
        'total_price_usd' => 'decimal:2',
        'currency_rate_usd' => 'decimal:4',
        'discount_amount' => 'decimal:2',
        'special_requests' => 'array',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($booking) {
            if (empty($booking->booking_reference)) {
                $booking->booking_reference = static::generateBookingReference();
            }
            
            if (empty($booking->nights)) {
                $booking->nights = Carbon::parse($booking->check_in)
                    ->diffInDays(Carbon::parse($booking->check_out));
            }
        });
    }

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function guestProfile()
    {
        return $this->belongsTo(GuestProfile::class);
    }

    public function resort()
    {
        return $this->belongsTo(Resort::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function ratePlan()
    {
        return $this->belongsTo(RatePlan::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    public function commission()
    {
        return $this->belongsTo(Commission::class);
    }

    public function bookingItems()
    {
        return $this->hasMany(BookingItem::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['cancelled']);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('check_in', '>', now())
                    ->whereIn('status', ['confirmed', 'pending']);
    }

    public function scopeInHouse($query)
    {
        return $query->where('check_in', '<=', now())
                    ->where('check_out', '>', now())
                    ->where('status', 'confirmed');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('check_in', [$startDate, $endDate])
              ->orWhereBetween('check_out', [$startDate, $endDate])
              ->orWhere(function ($qq) use ($startDate, $endDate) {
                  $qq->where('check_in', '<=', $startDate)
                     ->where('check_out', '>=', $endDate);
              });
        });
    }

    /**
     * Accessors
     */
    public function getTotalUsdAttribute()
    {
        return $this->total_price_usd;
    }

    public function getSubtotalUsdAttribute()
    {
        return $this->subtotal_usd;
    }

    public function getStatusBadgeAttribute()
    {
        $colors = [
            'pending' => 'warning',
            'confirmed' => 'success',
            'cancelled' => 'danger',
            'completed' => 'info',
            'no_show' => 'secondary',
        ];

        return $colors[$this->status] ?? 'primary';
    }

    public function getCanCancelAttribute()
    {
        if ($this->status !== 'confirmed') {
            return false;
        }

        $cancellationPolicy = $this->ratePlan->cancellation_policy ?? [];
        $hoursBeforeCheckIn = $cancellationPolicy['hours_before'] ?? 24;
        
        return $this->check_in->subHours($hoursBeforeCheckIn) > now();
    }

    /**
     * Methods
     */
    public static function generateBookingReference()
    {
        do {
            $reference = 'BK' . strtoupper(Str::random(8));
        } while (static::where('booking_reference', $reference)->exists());

        return $reference;
    }

    public function calculateTotalPrice()
    {
        // Start with room cost
        $total = SeasonalRate::calculateTotalForPeriod(
            $this->rate_plan_id,
            $this->check_in,
            $this->check_out
        );

        // Apply promotion discount
        if ($this->promotion_id && $this->promotion) {
            $discount = $this->promotion->calculateDiscount($total);
            $this->discount_amount = $discount;
            $total -= $discount;
        }

        $this->subtotal_usd = $total;

        // Add taxes from resort
        $taxRules = $this->resort->tax_rules ?? [];
        foreach ($taxRules as $taxName => $taxRate) {
            $taxAmount = $total * ($taxRate / 100);
            $total += $taxAmount;
            
            $this->bookingItems()->create([
                'item_type' => 'tax',
                'item_name' => ucfirst($taxName),
                'unit_price' => $taxAmount,
                'total_price' => $taxAmount,
                'quantity' => 1,
            ]);
        }

        // Add service fee from site settings
        $serviceRate = SiteSetting::getValue('tourist_service_fee', 12);
        $serviceFee = $total * ($serviceRate / 100);
        $total += $serviceFee;

        $this->bookingItems()->create([
            'item_type' => 'service_fee',
            'item_name' => 'Tourist Service Fee',
            'unit_price' => $serviceFee,
            'total_price' => $serviceFee,
            'quantity' => 1,
        ]);

        // Add transfer if selected
        if ($this->transfer_id && $this->transfer) {
            $transferCost = $this->transfer->price * $this->adults;
            $total += $transferCost;

            $this->bookingItems()->create([
                'item_type' => 'transfer',
                'item_name' => $this->transfer->getTranslation('name', 'en'),
                'unit_price' => $this->transfer->price,
                'total_price' => $transferCost,
                'quantity' => $this->adults,
            ]);
        }

        $this->total_price_usd = $total;
        return $total;
    }

    public function confirm()
    {
        $this->status = 'confirmed';
        $this->save();

        // Block inventory
        Inventory::blockInventory(
            $this->rate_plan_id,
            $this->check_in,
            $this->check_out,
            1
        );

        // Use promotion if applicable
        if ($this->promotion) {
            $this->promotion->use();
        }
    }

    public function cancel($reason = null)
    {
        $this->status = 'cancelled';
        $this->cancelled_at = now();
        $this->cancellation_reason = $reason;
        $this->save();

        // Release inventory
        Inventory::releaseInventory(
            $this->rate_plan_id,
            $this->check_in,
            $this->check_out,
            1
        );
    }

    public function complete()
    {
        $this->status = 'completed';
        $this->save();
    }
}
