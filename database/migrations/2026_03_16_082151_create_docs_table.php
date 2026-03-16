<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('docs', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_num');
            $table->string('document_UID')->nullable()->unique();
            $table->string('submission_UID')->nullable();
            $table->string('status')->default('Draft');
            $table->timestamp('submitted_at')->nullable();
            $table->string('invoice_type');
            $table->string('einvoice_ver')->default('1.0');
            $table->timestamp('invoice_datetime');
            $table->string('currency', 3)->default('MYR');
            $table->decimal('exchange_rate', 10, 6)->nullable();

            // Invoice period fields
            $table->timestamp('billing_startdate')->nullable();
            $table->timestamp('billing_enddate')->nullable();
            $table->string('frequency')->nullable();

            // Reference fields
            $table->string('original_invoice_num')->nullable();

            // FTA and Incoterms
            $table->string('fta')->nullable();
            $table->string('incoterms')->nullable();

            // Exporter authorization
            $table->string('exporter_authorisation_num')->nullable();

            // Payment information
            $table->string('payment_mode')->nullable();
            $table->string('supplier_bank_account_num')->nullable();
            $table->string('payment_terms')->nullable();

            // Prepayment
            $table->timestamp('prepayment_datetime')->nullable();
            $table->decimal('prepayment_amount', 15, 2)->nullable();

            // Additional discount/fee
            $table->decimal('additional_disc', 15, 2)->nullable();
            $table->string('additional_disc_reason')->nullable();
            $table->decimal('additional_fee', 15, 2)->nullable();
            $table->string('additional_fee_reason')->nullable();

            // Tax information
            $table->string('tax_type')->default('06');
            $table->decimal('total_tax_amt', 15, 2)->default(0);
            $table->decimal('total_taxable_amt', 15, 2)->default(0);
            $table->decimal('total_tax_amt_per_tax_type', 15, 2)->default(0);

            // Financial totals
            $table->decimal('total_nett_amt', 15, 2)->default(0);
            $table->decimal('total_excl_tax', 15, 2)->default(0);
            $table->decimal('total_incl_tax', 15, 2)->default(0);
            $table->decimal('total_dsc_val', 15, 2)->default(0);
            $table->decimal('total_charge_amt', 15, 2)->default(0);
            $table->decimal('rounding_amt', 15, 2)->default(0);
            $table->decimal('total_payable', 15, 2)->default(0);

            // Shipping information
            $table->string('shipping_recipient_name')->nullable();
            $table->string('shipping_recipient_tin')->nullable();
            $table->string('shipping_recipient_registration_type')->nullable();
            $table->string('shipping_recipient_registration_num')->nullable();
            $table->string('shipping_recipient_address1')->nullable();
            $table->string('shipping_recipient_address2')->nullable();
            $table->string('shipping_recipient_address3')->nullable();
            $table->string('shipping_recipient_city')->nullable();
            $table->string('shipping_recipient_postcode')->nullable();
            $table->string('shipping_recipient_state')->nullable();
            $table->string('shipping_recipient_country')->nullable();
            $table->string('other_charges_detail')->nullable();
            $table->decimal('other_charges_amount', 15, 2)->nullable();

            // Foreign keys and indexes
            $table->foreignId('company_id')->default(1)->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->index('invoice_num');
            $table->index('document_UID');
            $table->index('submission_UID');
            $table->index('status');
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('docs');
    }
};
