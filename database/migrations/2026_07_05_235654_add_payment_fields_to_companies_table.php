<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('plan_generator_column_key')->nullable()->after('plan_generator_id');
            $table->string('plan_generator_column_label')->nullable()->after('plan_generator_column_key');
            $table->string('payment_frequency')->default('ANUAL')->after('plan_generator_column_label');
            $table->decimal('fee_anual', 14, 2)->nullable()->after('payment_frequency');
            $table->decimal('total_amount', 14, 2)->nullable()->after('fee_anual');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'plan_generator_column_key',
                'plan_generator_column_label',
                'payment_frequency',
                'fee_anual',
                'total_amount',
            ]);
        });
    }
};
