<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocSupplier extends Model
{
    protected $fillable = [
        'doc_id',
        'name',
        'tin',
        'registration_type',
        'registration_num',
        'msic',
        'business_desc',
        'contact_num',
        'email',
        'address1',
        'address2',
        'address3',
        'city',
        'postcode',
        'state',
        'country',
    ];

    /**
     * Get the document that owns the supplier.
     */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }
}
