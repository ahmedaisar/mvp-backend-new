<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BookingPolicy extends GlobalPolicy
{
    /**
     * Determine if a resort manager can access this booking.
     */
    protected function resortManagerCanAccess(User $user, Model $model): bool
    {
        // Resort managers can access bookings for resorts they manage
        return $user->canManageResort($model->resort_id);
    }
    
    /**
     * Override viewAny to allow resort managers to see bookings for their resorts
     */
    public function viewAny(User $user): bool
    {
        return $user->is_admin || $user->is_resort_manager;
    }
    
    /**
     * Override create to allow resort managers to create bookings for their resorts
     */
    public function create(User $user): bool
    {
        return $user->is_admin || $user->is_resort_manager;
    }
}
