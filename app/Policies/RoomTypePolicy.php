<?php

namespace App\Policies;

use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RoomTypePolicy extends GlobalPolicy
{
    /**
     * Determine if a resort manager can access this room type.
     */
    protected function resortManagerCanAccess(User $user, Model $model): bool
    {
        // Resort managers can access room types of resorts they manage
        return $user->canManageResort($model->resort_id);
    }
    
    /**
     * Override the create method to allow resort managers to create room types
     */
    public function create(User $user): bool
    {
        return $user->is_admin || $user->is_resort_manager;
    }
}
