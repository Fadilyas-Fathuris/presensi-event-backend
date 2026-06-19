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

    protected $appends = ['qr_payload', 'qr_code_url', 'poster_url'];

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

        $totalRegistered = $this->registrations()->count();
        return $totalRegistered < $this->quota;
    }

    // Sisa kuota
    public function remainingQuota(): ?int
    {
        if (is_null($this->quota)) return null;
        $totalRegistered = $this->registrations()->count();
        return max(0, $this->quota - $totalRegistered);
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
