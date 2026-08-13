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
        Schema::table('credit_reconciliations', function (Blueprint $table): void {
            $table->foreignId('paid_membership_id')->nullable()->after('agent_id')->constrained('paid_memberships')->nullOnDelete();
            $table->foreignId('paid_membership_corporate_id')->nullable()->after('paid_membership_id')->constrained('paid_membership_corporates')->nullOnDelete();
            $table->foreignId('collection_id')->nullable()->after('paid_membership_corporate_id')->constrained('collections')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_reconciliations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('collection_id');
            $table->dropConstrainedForeignId('paid_membership_corporate_id');
            $table->dropConstrainedForeignId('paid_membership_id');
        });
    }
};
