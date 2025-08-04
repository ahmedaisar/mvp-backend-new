<?php

namespace App\Policies;

use App\Models\Resort;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ResortPolicy extends GlobalPolicy
{
    /**
     * Determine if a resort manager can access this resort.
     */
    protected function resortManagerCanAccess(User $user, Model $model): bool
    {
        return $user->canManageResort($model->id);
    }
}
