<?php

namespace App\Policies;

use App\Models\ResortManager;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ResortManagerPolicy extends GlobalPolicy
{
    /**
     * Determine if a resort manager can access this resort manager assignment.
     */
    protected function resortManagerCanAccess(User $user, Model $model): bool
    {
        // Resort managers can only see their own assignments
        return $user->id === $model->user_id;
    }
    
    /**
     * Override viewAny to allow resort managers to only see their own assignments
     */
    public function viewAny(User $user): bool
    {
        // Only admin can view any resort manager assignment
        return $user->is_admin;
    }
    
    /**
     * Override create to allow only admins to create resort manager assignments
     */
    public function create(User $user): bool
    {
        return $user->is_admin;
    }
}
