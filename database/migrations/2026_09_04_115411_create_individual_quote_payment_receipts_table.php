<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('individual_quote_payment_receipts')) {
            return;
        }

        Schema::create('individual_quote_payment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('individual_quote_id');
            $table->unsignedBigInteger('storefront_user_id')->nullable();
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->string('channel', 32)->default('pwa');
            $table->timestamps();

            $table->index('individual_quote_id', 'iqpr_quote_id_index');
            $table->index('storefront_user_id', 'iqpr_storefront_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('individual_quote_payment_receipts');
    }
};
