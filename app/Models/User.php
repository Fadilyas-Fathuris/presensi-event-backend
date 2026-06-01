<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

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
        'role',
        'status',
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
}
