<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'item_type',
        'item_name',
        'item_description',
        'quantity',
        'unit_price',
        'total_price',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Relationships
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Scopes
     */
    public function scopeByType($query, $type)
    {
        return $query->where('item_type', $type);
    }

    public function scopeTaxes($query)
    {
        return $query->where('item_type', 'tax');
    }

    public function scopeFees($query)
    {
        return $query->where('item_type', 'service_fee');
    }

    public function scopeTransfers($query)
    {
        return $query->where('item_type', 'transfer');
    }

    public function scopeDiscounts($query)
    {
        return $query->where('item_type', 'discount');
    }

    /**
     * Accessors
     */
    public function getFormattedUnitPriceAttribute()
    {
        return number_format($this->unit_price, 2) . ' ' . $this->currency;
    }

    public function getFormattedTotalPriceAttribute()
    {
        return number_format($this->total_price, 2) . ' ' . $this->currency;
    }

    public function getIsTaxAttribute()
    {
        return $this->item_type === 'tax';
    }

    public function getIsFeeAttribute()
    {
        return $this->item_type === 'service_fee';
    }

    public function getIsTransferAttribute()
    {
        return $this->item_type === 'transfer';
    }

    public function getIsDiscountAttribute()
    {
        return $this->item_type === 'discount';
    }
}
