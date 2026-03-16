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
        Schema::create('doc_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doc_id')->constrained()->onDelete('cascade');

            // Item details
            $table->string('classification');
            $table->text('description');
            $table->string('country')->nullable();
            $table->string('product_tariff_code')->nullable();

            // Quantity and measurement
            $table->decimal('qty', 10, 2);
            $table->string('measurement')->nullable();

            // Pricing
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total_excl_tax', 15, 2);

            // Tax information
            $table->string('tax_type');
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('tax_exemption_amt', 15, 2)->default(0);
            $table->string('tax_exemption_details')->nullable();

            // Discount
            $table->decimal('discount_amt', 15, 2)->nullable();
            $table->decimal('discount_rate', 5, 2)->nullable();
            $table->string('discount_reason')->nullable();

            // Charges
            $table->decimal('charge_amt', 15, 2)->nullable();
            $table->decimal('charge_rate', 5, 2)->nullable();
            $table->string('charge_reason')->nullable();

            $table->timestamps();

            $table->index('doc_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doc_details');
    }
};
