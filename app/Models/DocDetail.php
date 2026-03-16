<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocDetail extends Model
{
    protected $fillable = [
        'doc_id',
        'classification',
        'description',
        'country',
        'product_tariff_code',
        'qty',
        'measurement',
        'unit_price',
        'subtotal',
        'total_excl_tax',
        'tax_type',
        'tax_rate',
        'tax_amount',
        'tax_exemption_amt',
        'tax_exemption_details',
        'discount_amt',
        'discount_rate',
        'discount_reason',
        'charge_amt',
        'charge_rate',
        'charge_reason',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total_excl_tax' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_exemption_amt' => 'decimal:2',
        'discount_amt' => 'decimal:2',
        'discount_rate' => 'decimal:2',
        'charge_amt' => 'decimal:2',
        'charge_rate' => 'decimal:2',
    ];

    /**
     * Get the document that owns the detail.
     */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }
}
