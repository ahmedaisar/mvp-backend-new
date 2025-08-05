<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        '2fa_secret',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        '2fa_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Relationships
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function guestProfile()
    {
        return $this->hasOne(GuestProfile::class, 'email', 'email');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Scopes
     */
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeResortManagers($query)
    {
        return $query->where('role', 'resort_manager');
    }

    public function scopeAgencyOperators($query)
    {
        return $query->where('role', 'agency_operator');
    }

    /**
     * Accessors
     */
    public function getIsAdminAttribute()
    {
        return $this->role === 'admin';
    }

    public function getIsResortManagerAttribute()
    {
        return $this->role === 'resort_manager';
    }

    public function getIsAgencyOperatorAttribute()
    {
        return $this->role === 'agency_operator';
    }

    public function getHas2faAttribute()
    {
        return !empty($this->{'2fa_secret'});
    }

    /**
     * Methods
     */
    public function canManageResort($resortId = null)
    {
        if ($this->is_admin) {
            return true;
        }

        if ($this->is_resort_manager) {
            // If no specific resort ID is provided, and the user is a resort manager,
            // they potentially have access to some resort
            if ($resortId === null) {
                return true;
            }
            
            // Check if this user is assigned to manage this specific resort
            return $this->managedResorts()->where('resorts.id', $resortId)->exists();
        }

        return false;
    }

    /**
     * Get the resorts this user manages
     */
    public function managedResorts()
    {
        return $this->belongsToMany(Resort::class, 'resort_managers', 'user_id', 'resort_id');
    }

    public function canAccessBooking(Booking $booking)
    {
        if ($this->is_admin) {
            return true;
        }

        if ($this->is_resort_manager && $this->canManageResort($booking->resort_id)) {
            return true;
        }

        // Users can access their own bookings
        return $this->id === $booking->user_id;
    }

    public function enable2fa($secret)
    {
        $this->{'2fa_secret'} = encrypt($secret);
        $this->save();
    }

    public function disable2fa()
    {
        $this->{'2fa_secret'} = null;
        $this->save();
    }

    public function get2faSecret()
    {
        return $this->{'2fa_secret'} ? decrypt($this->{'2fa_secret'}) : null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return str_ends_with($this->email, '@atolldiscovery.com');
    }
}
