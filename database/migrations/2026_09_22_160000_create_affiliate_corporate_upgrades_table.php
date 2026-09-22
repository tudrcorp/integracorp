<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliate_corporate_upgrades')) {
            return;
        }

        Schema::create('affiliate_corporate_upgrades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_corporate_id')
                ->constrained('affiliate_corporates')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('affiliation_corporate_id')->index();
            $table->foreignId('upgrade_benefit_id')
                ->nullable()
                ->constrained('upgrade_benefits')
                ->nullOnDelete();
            $table->string('name', 120);
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('ACTIVO');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivated_by')->nullable();
            $table->timestamps();

            $table->index(['affiliate_corporate_id', 'status'], 'aff_corp_upgrades_affiliate_status_idx');
            $table->index(['affiliation_corporate_id', 'status'], 'aff_corp_upgrades_affiliation_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_corporate_upgrades');
    }
};
