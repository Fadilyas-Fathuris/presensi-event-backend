<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastLog extends Model
{
    protected $fillable = [
        'event_id',
        'target',
        'total_targets',
        'status',
        'sender_status',
        'message',
        'blocked_reason',
        'fonnte',
    ];

    protected $casts = [
        'fonnte' => 'array',
    ];
}
