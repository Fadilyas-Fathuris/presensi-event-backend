<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'gender',
        'email',
        'password',
        'password_changed_at',
        'phone',
        'graduation_year',
        'birth_date',
        'avatar_url',
        'role',
        'admin_level',
        'status',
        'status_reason',
        'google_id',
        'auth_provider',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'password'          => 'hashed',
    ];

    public function alumniNotifications(): HasMany
    {
        return $this->hasMany(AlumniNotification::class);
    }

    public function presensis(): HasMany
    {
        return $this->hasMany(Presensi::class);
    }

    public function eventRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function domicile(): HasOne
    {
        return $this->hasOne(UserDomicile::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'admin' && $this->admin_level === 'super_admin';
    }

    public function isRegularAdmin(): bool
    {
        return $this->role === 'admin' && $this->admin_level === 'admin';
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Check if user has Google account linked
     *
     * @return bool
     */
    public function hasGoogleLinked(): bool
    {
        return !is_null($this->google_id);
    }

    /**
     * Check if user can safely unlink Google account
     * User can unlink only if they have a password set
     *
     * @return bool
     */
    public function canUnlinkGoogle(): bool
    {
        return $this->hasGoogleLinked() && !empty($this->password);
    }

    /**
     * Check if user is Google-only (no password set)
     *
     * @return bool
     */
    public function isGoogleOnly(): bool
    {
        return $this->hasGoogleLinked() && empty($this->password);
    }
}