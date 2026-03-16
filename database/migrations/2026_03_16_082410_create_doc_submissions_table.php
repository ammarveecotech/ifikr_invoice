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
        Schema::create('doc_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doc_id')->constrained()->onDelete('cascade');

            $table->string('submission_UID')->unique();
            $table->string('status');
            $table->timestamp('submitted_at');
            $table->json('response')->nullable();

            $table->timestamps();

            $table->index('doc_id');
            $table->index('submission_UID');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doc_submissions');
    }
};
