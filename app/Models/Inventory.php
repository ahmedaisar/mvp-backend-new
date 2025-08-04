<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'rate_plan_id',
        'start_date',
        'end_date',
        'available_rooms',
        'blocked',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'available_rooms' => 'integer',
        'blocked' => 'boolean',
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
        return $query->where(function($query) use ($startDate, $endDate) {
            // Find records where the inventory range overlaps with the requested range
            $query->where(function($q) use ($startDate, $endDate) {
                // Start date falls within the inventory range
                $q->where('start_date', '<=', $startDate)
                  ->where('end_date', '>=', $startDate);
            })->orWhere(function($q) use ($startDate, $endDate) {
                // End date falls within the inventory range
                $q->where('start_date', '<=', $endDate)
                  ->where('end_date', '>=', $endDate);
            })->orWhere(function($q) use ($startDate, $endDate) {
                // Inventory range falls completely within the requested range
                $q->where('start_date', '>=', $startDate)
                  ->where('end_date', '<=', $endDate);
            });
        });
    }

    public function scopeAvailable($query)
    {
        return $query->where('available_rooms', '>', 0)
                    ->where('blocked', false);
    }

    public function scopeBlocked($query)
    {
        return $query->where('blocked', true);
    }

    /**
     * Methods
     */
    public function isAvailable($roomCount = 1)
    {
        return !$this->blocked && $this->available_rooms >= $roomCount;
    }

    public function reduceAvailability($roomCount = 1)
    {
        if ($this->available_rooms >= $roomCount) {
            $this->decrement('available_rooms', $roomCount);
            return true;
        }
        return false;
    }

    public function increaseAvailability($roomCount = 1)
    {
        $this->increment('available_rooms', $roomCount);
        return true;
    }

    /**
     * Static methods for inventory management
     */
    public static function checkAvailability($ratePlanId, $startDate, $endDate, $roomCount = 1)
    {
        $inventories = static::where('rate_plan_id', $ratePlanId)
            ->forDateRange($startDate, $endDate)
            ->get();
        
        if ($inventories->isEmpty()) {
            return false;
        }
        
        // Ensure continuous coverage of the requested date range
        $period = \Carbon\CarbonPeriod::create($startDate, $endDate)->excludeEndDate();
        
        foreach ($period as $date) {
            $dateString = $date->toDateString();
            $hasAvailability = false;
            
            foreach ($inventories as $inventory) {
                if ($inventory->start_date <= $dateString && $inventory->end_date >= $dateString) {
                    if ($inventory->isAvailable($roomCount)) {
                        $hasAvailability = true;
                        break;
                    }
                }
            }
            
            if (!$hasAvailability) {
                return false;
            }
        }
        
        return true;
    }

    public static function blockInventory($ratePlanId, $startDate, $endDate, $roomCount = 1)
    {
        // Check if there's an existing inventory entry that covers the entire date range
        $existingInventory = static::where('rate_plan_id', $ratePlanId)
            ->where('start_date', '<=', $startDate)
            ->where('end_date', '>=', $endDate)
            ->first();
        
        if ($existingInventory) {
            if (!$existingInventory->reduceAvailability($roomCount)) {
                throw new \Exception("Insufficient inventory for date range: {$startDate} to {$endDate}");
            }
            return;
        }
        
        // Create a new inventory entry covering the requested date range
        $inventory = static::updateOrCreate(
            [
                'rate_plan_id' => $ratePlanId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            [
                'available_rooms' => 0,
                'blocked' => false
            ]
        );
        
        if (!$inventory->reduceAvailability($roomCount)) {
            throw new \Exception("Insufficient inventory for date range: {$startDate} to {$endDate}");
        }
    }

    public static function releaseInventory($ratePlanId, $startDate, $endDate, $roomCount = 1)
    {
        $inventories = static::where('rate_plan_id', $ratePlanId)
            ->forDateRange($startDate, $endDate)
            ->get();
        
        foreach ($inventories as $inventory) {
            $inventory->increaseAvailability($roomCount);
        }
    }
}
