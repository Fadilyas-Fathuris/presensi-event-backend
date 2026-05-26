<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventQrCode extends Model
{
    protected $fillable = [
        'event_id',
        'qr_token',
        'qr_code_image',
        'qr_code_url',
        'valid_from',
        'timeout_minutes',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'is_active' => 'boolean',
        'timeout_minutes' => 'integer',
    ];

    protected $appends = [
        'expired_at',
        'is_valid_now',
        'is_expired',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getExpiredAtAttribute()
    {
        return $this->valid_from?->copy()->addMinutes($this->timeout_minutes);
    }

    public function getIsValidNowAttribute(): bool
    {
        if (! $this->valid_from || ! $this->expired_at) {
            return false;
        }

        return now()->between($this->valid_from, $this->expired_at);
    }

    public function getIsExpiredAttribute(): bool
    {
        if (! $this->expired_at) {
            return true;
        }

        return now()->greaterThan($this->expired_at);
    }
}