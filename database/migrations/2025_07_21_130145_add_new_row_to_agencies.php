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
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('is_accepted_conditions')->nullable();
            $table->string('doc_digital_signature')->nullable();
            $table->string('doc_document_identity')->nullable();
            $table->string('doc_w8_w9')->nullable();
            $table->string('doc_bank_data_ves')->nullable();
            $table->string('doc_bank_data_usd')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            //
        });
    }
};