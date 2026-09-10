<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_service_orders', 'administrative_status')) {
                $table->string('administrative_status')->default('PENDIENTE')->after('status');
            }
        });

        if (Schema::hasColumn('operation_service_orders', 'administrative_status')) {
            DB::table('operation_service_orders')
                ->where(function (Builder $query): void {
                    $query->whereNull('administrative_status')
                        ->orWhere('administrative_status', '');
                })
                ->update(['administrative_status' => 'PENDIENTE']);
        }
    }

    public function down(): void
    {
        Schema::table('operation_service_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('operation_service_orders', 'administrative_status')) {
                $table->dropColumn('administrative_status');
            }
        });
    }
};
