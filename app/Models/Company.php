<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'tin',
        'irb_token',
        'irb_expiration',
    ];

    protected $casts = [
        'irb_expiration' => 'datetime',
    ];
}
