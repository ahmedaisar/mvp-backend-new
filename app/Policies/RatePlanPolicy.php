<?php

namespace App\Policies;

use App\Models\RatePlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RatePlanPolicy extends GlobalPolicy
{
    /**
     * Determine if a resort manager can access this rate plan.
     */
    protected function resortManagerCanAccess(User $user, Model $model): bool
    {
        // Resort managers can access rate plans of room types of resorts they manage
        return $user->canManageResort($model->roomType->resort_id);
    }
    
    /**
     * Override the create method to allow resort managers to create rate plans
     */
    public function create(User $user): bool
    {
        return $user->is_admin || $user->is_resort_manager;
    }
}
