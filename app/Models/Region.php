<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'parent_code',
        'postal_code',
    ];
}
