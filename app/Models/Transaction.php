<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'user_id',
        'transaction_id',
        'payment_gateway',
        'payment_method',
        'amount',
        'fee_amount',
        'currency',
        'type',
        'description',
        'status',
        'gateway_response',
        'gateway_transaction_id',
        'reference_number',
        'processed_at',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'gateway_response' => 'array',
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($transaction) {
            if (empty($transaction->transaction_id)) {
                $transaction->transaction_id = static::generateTransactionId();
            }
        });
    }

    /**
     * Relationships
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByGateway($query, $gateway)
    {
        return $query->where('payment_gateway', $gateway);
    }

    public function scopePayments($query)
    {
        return $query->where('type', 'payment');
    }

    public function scopeRefunds($query)
    {
        return $query->whereIn('type', ['refund', 'partial_refund']);
    }

    /**
     * Accessors
     */
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2) . ' ' . strtoupper($this->currency);
    }

    public function getStatusBadgeAttribute()
    {
        $colors = [
            'pending' => 'warning',
            'processing' => 'info',
            'success' => 'success',
            'failed' => 'danger',
            'cancelled' => 'secondary',
        ];

        return $colors[$this->status] ?? 'primary';
    }

    public function getIsSuccessfulAttribute()
    {
        return $this->status === 'success';
    }

    public function getIsFailedAttribute()
    {
        return $this->status === 'failed';
    }

    public function getIsPendingAttribute()
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function getIsRefundAttribute()
    {
        return in_array($this->type, ['refund', 'partial_refund']);
    }

    /**
     * Methods
     */
    public static function generateTransactionId()
    {
        do {
            $id = 'TXN' . strtoupper(Str::random(12));
        } while (static::where('transaction_id', $id)->exists());

        return $id;
    }

    public function markAsSuccessful($gatewayTransactionId = null, $gatewayResponse = null)
    {
        $this->status = 'success';
        $this->processed_at = now();
        
        if ($gatewayTransactionId) {
            $this->gateway_transaction_id = $gatewayTransactionId;
        }
        
        if ($gatewayResponse) {
            $this->gateway_response = $gatewayResponse;
        }
        
        $this->save();

        // If this is a successful payment, confirm the booking
        if ($this->type === 'payment' && $this->booking) {
            $this->booking->confirm();
        }
    }

    public function markAsFailed($reason = null, $gatewayResponse = null)
    {
        $this->status = 'failed';
        $this->processed_at = now();
        $this->failure_reason = $reason;
        
        if ($gatewayResponse) {
            $this->gateway_response = $gatewayResponse;
        }
        
        $this->save();
    }

    public function markAsProcessing()
    {
        $this->status = 'processing';
        $this->save();
    }
}
