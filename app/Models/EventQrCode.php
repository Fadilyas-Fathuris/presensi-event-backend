<?php

namespace App\Models;

use Carbon\Carbon;
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
        'duration_days',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'is_active' => 'boolean',
        'duration_days' => 'integer',
    ];

    protected $appends = [
        'qr_payload',
        'expired_at',
        'is_valid_now',
        'is_expired',
        'valid_from_wib',
        'expired_at_wib',
        'created_at_wib',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getQrPayloadAttribute(): string
    {
        return $this->qr_token;
    }

    /**
     * Expiry = valid_from + duration_days days.
     */
    public function getExpiredAtAttribute(): ?Carbon
    {
        return $this->valid_from?->copy()->addDays($this->duration_days);
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

    // ── WIB (Asia/Jakarta) formatted timestamps ────────────────────────────

    /**
     * valid_from in WIB format: "04 Agustus 2026, 13:18 WIB"
     */
    public function getValidFromWibAttribute(): ?string
    {
        return $this->formatWib($this->valid_from);
    }

    /**
     * expired_at in WIB format: "07 Agustus 2026, 13:18 WIB"
     */
    public function getExpiredAtWibAttribute(): ?string
    {
        return $this->formatWib($this->expired_at);
    }

    /**
     * created_at in WIB format.
     */
    public function getCreatedAtWibAttribute(): ?string
    {
        return $this->formatWib($this->created_at);
    }

    /**
     * Format a Carbon instance to WIB display string.
     */
    private function formatWib(?Carbon $date): ?string
    {
        if (! $date) {
            return null;
        }

        return $date->copy()
            ->timezone('Asia/Jakarta')
            ->locale('id')
            ->translatedFormat('d F Y, H:i') . ' WIB';
    }
}
