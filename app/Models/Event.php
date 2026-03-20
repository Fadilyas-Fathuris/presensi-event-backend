<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'status_event',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    protected $appends = ['qr_code_url'];

    public function getQrCodeUrlAttribute(): ?string
    {
        if (! $this->qr_code_image) return null;

        return Storage::disk('public')->url($this->qr_code_image);
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

    // Helper: check if event is currently within attendance window
    public function isWithinAttendanceWindow(): bool
    {
        $now  = now();
        $date = $this->event_date->format('Y-m-d');

        $start = \Carbon\Carbon::parse("{$date} {$this->start_time}");
        $end   = \Carbon\Carbon::parse("{$date} {$this->end_time}");

        return $now->between($start, $end);
    }
}