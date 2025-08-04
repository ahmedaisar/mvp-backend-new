<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SeasonalRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'rate_plan_id',
        'start_date',
        'end_date',
        'nightly_price',
        'min_stay',
        'max_stay',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'nightly_price' => 'decimal:2',
        'min_stay' => 'integer',
        'max_stay' => 'integer',
    ];

    /**
     * Relationships
     */
    public function ratePlan()
    {
        return $this->belongsTo(RatePlan::class);
    }

    /**
     * Scopes
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function ($qq) use ($startDate, $endDate) {
                  $qq->where('start_date', '<=', $startDate)
                     ->where('end_date', '>=', $endDate);
              });
        });
    }

    public function scopeActive($query)
    {
        return $query->whereDate('end_date', '>=', now());
    }

    /**
     * Methods
     */
    public function isActiveForDate($date)
    {
        $date = Carbon::parse($date);
        return $date->between($this->start_date, $this->end_date);
    }

    public function getDurationAttribute()
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function getFormattedPriceAttribute()
    {
        return number_format($this->nightly_price, 2);
    }

    /**
     * Calculate total price for a stay period
     */
    public static function calculateTotalForPeriod($ratePlanId, $startDate, $endDate)
    {
        $total = 0;
        $period = \Carbon\CarbonPeriod::create($startDate, $endDate)->excludeEndDate();
        
        foreach ($period as $date) {
            $rate = static::where('rate_plan_id', $ratePlanId)
                ->forDate($date->toDateString())
                ->first();
            
            if ($rate) {
                $total += $rate->nightly_price;
            }
        }
        
        return $total;
    }
}
