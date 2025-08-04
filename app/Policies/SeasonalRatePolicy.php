<?php

namespace App\Policies;

use App\Models\SeasonalRate;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SeasonalRatePolicy extends GlobalPolicy
{
    /**
     * Determine if a resort manager can access this seasonal rate.
     */
    protected function resortManagerCanAccess(User $user, Model $model): bool
    {
        // Resort managers can access seasonal rates of rate plans they manage
        return $user->canManageResort($model->ratePlan->roomType->resort_id);
    }
    
    /**
     * Override the create method to allow resort managers to create seasonal rates
     */
    public function create(User $user): bool
    {
        return $user->is_admin || $user->is_resort_manager;
    }
}
