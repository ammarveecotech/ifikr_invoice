<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doc extends Model
{
    protected $fillable = [
        'invoice_num',
        'document_UID',
        'submission_UID',
        'status',
        'submitted_at',
        'invoice_type',
        'einvoice_ver',
        'invoice_datetime',
        'currency',
        'exchange_rate',
        'billing_startdate',
        'billing_enddate',
        'frequency',
        'original_invoice_num',
        'fta',
        'incoterms',
        'exporter_authorisation_num',
        'payment_mode',
        'supplier_bank_account_num',
        'payment_terms',
        'prepayment_datetime',
        'prepayment_amount',
        'additional_disc',
        'additional_disc_reason',
        'additional_fee',
        'additional_fee_reason',
        'tax_type',
        'total_tax_amt',
        'total_taxable_amt',
        'total_tax_amt_per_tax_type',
        'total_nett_amt',
        'total_excl_tax',
        'total_incl_tax',
        'total_dsc_val',
        'total_charge_amt',
        'rounding_amt',
        'total_payable',
        'shipping_recipient_name',
        'shipping_recipient_tin',
        'shipping_recipient_registration_type',
        'shipping_recipient_registration_num',
        'shipping_recipient_address1',
        'shipping_recipient_address2',
        'shipping_recipient_address3',
        'shipping_recipient_city',
        'shipping_recipient_postcode',
        'shipping_recipient_state',
        'shipping_recipient_country',
        'other_charges_detail',
        'other_charges_amount',
        'company_id',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'invoice_datetime' => 'datetime',
        'billing_startdate' => 'datetime',
        'billing_enddate' => 'datetime',
        'prepayment_datetime' => 'datetime',
        'total_tax_amt' => 'decimal:2',
        'total_taxable_amt' => 'decimal:2',
        'total_tax_amt_per_tax_type' => 'decimal:2',
        'total_nett_amt' => 'decimal:2',
        'total_excl_tax' => 'decimal:2',
        'total_incl_tax' => 'decimal:2',
        'total_dsc_val' => 'decimal:2',
        'total_charge_amt' => 'decimal:2',
        'rounding_amt' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'prepayment_amount' => 'decimal:2',
        'additional_disc' => 'decimal:2',
        'additional_fee' => 'decimal:2',
        'other_charges_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    /**
     * Get the supplier for the document.
     */
    public function supplier(): HasOne
    {
        return $this->hasOne(DocSupplier::class);
    }

    /**
     * Get the buyer for the document.
     */
    public function buyer(): HasOne
    {
        return $this->hasOne(DocBuyer::class);
    }

    /**
     * Get the details for the document.
     */
    public function details(): HasMany
    {
        return $this->hasMany(DocDetail::class);
    }

    /**
     * Get the submissions for the document.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(DocSubmission::class);
    }

    /**
     * Get the company that owns the document.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Check if document is submitted or successful.
     */
    public function isSubmittedOrSuccess(): bool
    {
        return in_array($this->status, ['Submitted', 'Valid', 'Invalid']);
    }

    /**
     * Get original invoice by TIN.
     */
    public function getOriginalInvoice($tin)
    {
        return self::whereHas('supplier', function ($query) use ($tin) {
            $query->where('tin', $tin);
        })->where('invoice_num', $this->original_invoice_num)->first();
    }

    /**
     * Update submissions from LHDN response.
     */
    public function updateSubmissions($submissionData)
    {
        // Implementation would update submission records based on LHDN response
        // This would typically be called when getting submission status
    }

    /**
     * Generate invoice number.
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');
        $lastInvoice = self::where('invoice_num', 'like', "{$prefix}{$date}%")
            ->orderBy('invoice_num', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_num, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "{$prefix}{$date}{$newNumber}";
    }
}
