<?php

namespace App\Policies;

use App\Models\Amenity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AmenityPolicy extends GlobalPolicy
{
    /**
     * Determine if a resort manager can access this amenity.
     */
    protected function resortManagerCanAccess(User $user, Model $model): bool
    {
        // Resort managers can access amenities of resorts they manage
        // If amenity belongs to a room type, check resort access
        if ($model->amenitable_type === 'App\Models\RoomType') {
            $roomType = $model->amenitable;
            return $user->canManageResort($roomType->resort_id);
        }
        
        // If amenity belongs to a resort directly
        if ($model->amenitable_type === 'App\Models\Resort') {
            return $user->canManageResort($model->amenitable_id);
        }
        
        return false;
    }
    
    /**
     * Override the create method to allow resort managers to create amenities
     */
    public function create(User $user): bool
    {
        return $user->is_admin || $user->is_resort_manager;
    }
}
