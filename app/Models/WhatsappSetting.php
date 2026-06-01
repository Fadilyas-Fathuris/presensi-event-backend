<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSetting extends Model
{
    protected $fillable = [
        'provider',
        'api_url',
        'api_token',
        'sender_number',
        'sender_status',
        'blocked_reason',
        'last_tested_at',
    ];

    protected $casts = [
        'api_token' => 'encrypted',
        'last_tested_at' => 'datetime',
    ];

    public static function current(): ?self
    {
        return self::query()->latest('id')->first();
    }
}
