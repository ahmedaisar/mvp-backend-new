<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends GlobalPolicy
{
    /**
     * Determine if a resort manager can access this user.
     */
    protected function resortManagerCanAccess(User $user, Model $model): bool
    {
        // Resort managers can only access their own user profile
        return $user->id === $model->id;
    }
    
    /**
     * Override viewAny to allow resort managers to see user list
     */
    public function viewAny(User $user): bool
    {
        return $user->is_admin || $user->is_resort_manager;
    }
    
    /**
     * Override create to allow only admins to create users
     */
    public function create(User $user): bool
    {
        return $user->is_admin;
    }
    
    /**
     * Override delete to allow only admins to delete users
     */
    public function delete(User $user, Model $model): bool
    {
        // Admin can delete any user except themselves
        if ($user->is_admin && $user->id !== $model->id) {
            return true;
        }
        
        return false;
    }
}
