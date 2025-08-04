<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\RatePlan;
use App\Models\Resort;
use App\Models\ResortManager;
use App\Models\RoomType;
use App\Models\SeasonalRate;
use App\Models\User;
use App\Policies\AmenityPolicy;
use App\Policies\BookingPolicy;
use App\Policies\RatePlanPolicy;
use App\Policies\ResortManagerPolicy;
use App\Policies\ResortPolicy;
use App\Policies\RoomTypePolicy;
use App\Policies\SeasonalRatePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Resort::class => ResortPolicy::class,
        RoomType::class => RoomTypePolicy::class,
        RatePlan::class => RatePlanPolicy::class,
        SeasonalRate::class => SeasonalRatePolicy::class,
        Amenity::class => AmenityPolicy::class,
        User::class => UserPolicy::class,
        ResortManager::class => ResortManagerPolicy::class,
        Booking::class => BookingPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
