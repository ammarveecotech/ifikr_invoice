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
        Schema::create('doc_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doc_id')->constrained()->onDelete('cascade');

            // Basic information
            $table->string('name');
            $table->string('tin');
            $table->string('registration_type');
            $table->string('registration_num');
            $table->string('msic');
            $table->string('business_desc')->nullable();

            // Contact information
            $table->string('contact_num');
            $table->string('email')->nullable();

            // Address information
            $table->string('address1');
            $table->string('address2')->nullable();
            $table->string('address3')->nullable();
            $table->string('city');
            $table->string('postcode');
            $table->string('state');
            $table->string('country');

            $table->timestamps();

            $table->index('doc_id');
            $table->index('tin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doc_suppliers');
    }
};
