<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Logger extends Model
{
    protected $fillable = [
        'company_id',
        'api_name',
        'request',
        'response',
        'status_code',
        'attribute_id',
    ];

    /**
     * Get the company that owns the log.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Encrypt request data before saving.
     */
    public function setRequestAttribute($value)
    {
        $this->attributes['request'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt request data when retrieving.
     */
    public function getRequestAttribute($value)
    {
        return Crypt::decryptString($value);
    }

    /**
     * Encrypt response data before saving.
     */
    public function setResponseAttribute($value)
    {
        $this->attributes['response'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt response data when retrieving.
     */
    public function getResponseAttribute($value)
    {
        return Crypt::decryptString($value);
    }
}
