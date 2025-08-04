<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class GlobalPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin and resort managers can view resources
        return $user->is_admin || $user->is_resort_manager;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Model $model): bool
    {
        // Admin can view any resource
        if ($user->is_admin) {
            return true;
        }

        // Resort managers can view resources
        if ($user->is_resort_manager) {
            return $this->resortManagerCanAccess($user, $model);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only admins can create resources
        return $user->is_admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Model $model): bool
    {
        // Admin can update any resource
        if ($user->is_admin) {
            return true;
        }

        // Resort managers can update resources they manage
        if ($user->is_resort_manager) {
            return $this->resortManagerCanAccess($user, $model);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Model $model): bool
    {
        // Only admins can delete resources
        return $user->is_admin;
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        // Only admins can bulk delete resources
        return $user->is_admin;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Model $model): bool
    {
        // Only admins can restore resources
        return $user->is_admin;
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        // Only admins can bulk restore resources
        return $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Model $model): bool
    {
        // Only admins can force delete resources
        return $user->is_admin;
    }

    /**
     * Determine whether the user can bulk force delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        // Only admins can bulk force delete resources
        return $user->is_admin;
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        // Only admins can reorder resources
        return $user->is_admin;
    }
    
    /**
     * Determine if a resort manager can access this model.
     * Override this method in model-specific policies to implement specific access logic.
     */
    protected function resortManagerCanAccess(User $user, Model $model): bool
    {
        // Default implementation - to be overridden by specific policies
        if (method_exists($model, 'resort') && $model->resort) {
            return $user->canManageResort($model->resort->id);
        }
        
        if (property_exists($model, 'resort_id') && $model->resort_id) {
            return $user->canManageResort($model->resort_id);
        }
        
        return false;
    }
}
