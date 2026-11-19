<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pedidos de medicamentos enviados a Farmadoc desde Operaciones.
 * Sin precios: Farmadoc solo recibe medicamentos y cantidades.
 */
return new class extends Migration
{
    private const PERMISSION_SLUG = 'pedidos-farmadoc';

    private const PERMISSION_MODULE = 'OPERACIONES';

    public function up(): void
    {
        if (! Schema::hasTable('farmadoc_orders')) {
            Schema::create('farmadoc_orders', function (Blueprint $table): void {
                $table->id();
                $table->string('order_number', 100)->unique('fdo_order_number_unique');
                $table->unsignedBigInteger('farmadoc_order_id')->nullable();
                $table->foreignId('operation_coordination_service_id')->nullable()->index('fdo_coordination_idx');
                $table->string('coordination_reference', 100)->nullable();
                $table->string('patient_name')->nullable();
                $table->json('items');
                $table->unsignedInteger('items_count')->default(0);
                $table->unsignedInteger('units_count')->default(0);
                $table->string('status', 30)->index('fdo_status_idx');
                $table->timestamp('farmadoc_updated_at')->nullable();
                $table->timestamp('status_checked_at')->nullable();
                $table->string('status_check_error')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->index('fdo_created_by_idx');
                $table->string('created_by_name')->nullable();
                $table->timestamps();
                $table->index('created_at', 'fdo_created_at_idx');
            });
        }

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $exists = DB::table('permissions')
            ->where('slug', self::PERMISSION_SLUG)
            ->where('module', self::PERMISSION_MODULE)
            ->exists();

        if (! $exists) {
            DB::table('permissions')->insert([
                'name' => 'Pedidos a Farmadoc',
                'slug' => self::PERMISSION_SLUG,
                'module' => self::PERMISSION_MODULE,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('farmadoc_orders');

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('slug', self::PERMISSION_SLUG)
            ->where('module', self::PERMISSION_MODULE)
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('user_permissions')) {
            DB::table('user_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
