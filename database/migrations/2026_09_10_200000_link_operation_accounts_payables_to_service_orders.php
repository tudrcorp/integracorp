<?php

declare(strict_types=1);

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationServiceOrder;
use App\Support\Operations\ServiceOrderAccountsPayableRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_accounts_payables', function (Blueprint $table): void {
            if (! Schema::hasColumn('operation_accounts_payables', 'operation_service_order_id')) {
                $table->unsignedBigInteger('operation_service_order_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('operation_accounts_payables', 'invoice_file_path')) {
                $table->string('invoice_file_path')->nullable()->after('invoice_currency');
            }
        });

        if (! Schema::hasIndex('operation_accounts_payables', 'operation_accounts_payables_operation_service_order_id_index')) {
            Schema::table('operation_accounts_payables', function (Blueprint $table): void {
                $table->index('operation_service_order_id');
            });
        }

        // El estatus por defecto pasa a «PENDIENTE POR PAGAR» (rojo en la tabla).
        Schema::table('operation_accounts_payables', function (Blueprint $table): void {
            $table->string('payment_status', 30)
                ->default(StatusCuentaPorPagar::PendientePorPagar->value)
                ->change();
        });

        DB::table('operation_accounts_payables')
            ->where('payment_status', 'PENDIENTE')
            ->update(['payment_status' => StatusCuentaPorPagar::PendientePorPagar->value]);

        $this->backfillInvoicedOrders();
    }

    /**
     * Genera la cuenta por pagar de las órdenes que ya tenían factura cargada
     * antes de existir este enlace.
     */
    private function backfillInvoicedOrders(): void
    {
        OperationServiceOrder::query()
            ->whereNotNull('invoice_file_path')
            ->whereNotNull('invoice_number')
            ->with(['supplier', 'operationCoordinationService'])
            ->each(function (OperationServiceOrder $order): void {
                $exists = DB::table('operation_accounts_payables')
                    ->where('operation_service_order_id', $order->getKey())
                    ->exists();

                if ($exists) {
                    return;
                }

                $supplierName = ServiceOrderAccountsPayableRegistrar::suggestedSupplierName($order);

                if ($supplierName === '') {
                    return;
                }

                ServiceOrderAccountsPayableRegistrar::register($order, [
                    'invoice_number' => (string) $order->invoice_number,
                    'invoice_control_number' => $order->invoice_control_number,
                    'invoice_date' => $order->invoice_date ?? $order->created_at,
                    'invoice_registration_date' => $order->invoice_registration_date ?? $order->invoice_date ?? $order->created_at,
                    'invoice_amount_usd' => $order->invoice_amount_usd,
                    'invoice_amount_ves' => $order->invoice_amount_ves,
                    'invoice_file_path' => $order->invoice_file_path,
                    'supplier_name' => $supplierName,
                    'supplier_rif' => ServiceOrderAccountsPayableRegistrar::suggestedSupplierRif($order),
                    'business_unit_id' => ServiceOrderAccountsPayableRegistrar::suggestedBusinessUnitId($order),
                ], $order->invoice_uploaded_by ?: 'sistema');
            });
    }

    public function down(): void
    {
        Schema::table('operation_accounts_payables', function (Blueprint $table): void {
            $table->string('payment_status', 30)->default('PENDIENTE')->change();
        });

        if (Schema::hasIndex('operation_accounts_payables', 'operation_accounts_payables_operation_service_order_id_index')) {
            Schema::table('operation_accounts_payables', function (Blueprint $table): void {
                $table->dropIndex('operation_accounts_payables_operation_service_order_id_index');
            });
        }

        Schema::table('operation_accounts_payables', function (Blueprint $table): void {
            foreach (['operation_service_order_id', 'invoice_file_path'] as $column) {
                if (Schema::hasColumn('operation_accounts_payables', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
