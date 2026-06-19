<?php

namespace App\Models;

use App\Models\EventQrCode;
use Illuminate\Database\Eloquent\Model;
use App\Models\EventRegistration;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Event extends Model
{
    protected $fillable = [
        'category_id',
        'created_by',
        'event_title',
        'description',
        'location',
        'event_date',
        'start_time',
        'end_time',
        'qr_token',
        'qr_code_image',
        'poster_image',
        'status_event',
        'quota',
    ];

    protected $casts = [
        'event_date' => 'date',
        'quota'      => 'integer',
    ];

    protected $appends = [
        'qr_payload',
        'qr_code_url',
        'poster_url',
        'quota_used',
        'remaining_quota',
        'is_quota_full',
        'quota_status',
        'quota_message',
    ];

    public function getQrPayloadAttribute(): ?string
    {
        return $this->qr_token;
    }

    public function getQrCodeUrlAttribute(): ?string
    {
        if (! $this->qr_code_image) return null;
        return Storage::disk('public')->url($this->qr_code_image);
    }

    public function getPosterUrlAttribute(): ?string
    {
        if (! $this->poster_image) return null;
        return asset('storage/' . $this->poster_image);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function presensis(): HasMany
    {
        return $this->hasMany(Presensi::class, 'event_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_id');
    }

    public function getQuotaUsedAttribute(): int
    {
        return $this->registeredCount();
    }

    public function getRemainingQuotaAttribute(): ?int
    {
        return $this->remainingQuota();
    }

    public function getIsQuotaFullAttribute(): bool
    {
        return ! $this->isQuotaAvailable();
    }

    public function getQuotaStatusAttribute(): string
    {
        if (is_null($this->quota)) {
            return 'unlimited';
        }

        return $this->isQuotaAvailable() ? 'available' : 'full';
    }

    public function getQuotaMessageAttribute(): ?string
    {
        if (is_null($this->quota) || $this->isQuotaAvailable()) {
            return null;
        }

        return 'Kuota penuh, segera hubungi penyelenggara';
    }

    public function isWithinAttendanceWindow(): bool
    {
        $now   = now();
        $date  = $this->event_date->format('Y-m-d');
        $start = \Carbon\Carbon::parse("{$date} {$this->start_time}");
        $end   = \Carbon\Carbon::parse("{$date} {$this->end_time}");

        return $now->between($start, $end);
    }

    // Cek apakah kuota masih tersedia
    public function isQuotaAvailable(): bool
    {
        // Jika quota null = tidak ada batas
        if (is_null($this->quota)) return true;

        $totalRegistered = $this->registeredCount();
        return $totalRegistered < $this->quota;
    }

    // Sisa kuota
    public function remainingQuota(): ?int
    {
        if (is_null($this->quota)) return null;
        $totalRegistered = $this->registeredCount();
        return max(0, $this->quota - $totalRegistered);
    }

    private function registeredCount(): int
    {
        if (array_key_exists('registrations_count', $this->attributes)) {
            return (int) $this->attributes['registrations_count'];
        }

        if ($this->relationLoaded('registrations')) {
            return $this->registrations->count();
        }

        return $this->registrations()->count();
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(EventQrCode::class);
    }

    public function activeQrCode(): HasOne
    {
        return $this->hasOne(EventQrCode::class)
            ->where('is_active', true)
            ->latestOfMany();
    }
}
