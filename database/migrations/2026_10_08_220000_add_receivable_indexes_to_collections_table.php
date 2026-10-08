<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de `collections` para el reporte de cuentas por cobrar (Cobranza Por
 * Mes) y las consultas por venta. La tabla solo tenía la llave primaria: la
 * «próxima cuota pendiente» de cada afiliación recorría la tabla entera y
 * ordenaba en memoria una vez por fila (crecimiento cuadrático).
 *
 * - `collections_affiliation_status_due_index`: próxima cuota por afiliación
 *   (`affiliation_code` + `status`, ordenada por vencimiento; InnoDB agrega `id`
 *   al final, que es el desempate del ORDER BY). También sirve a las relaciones
 *   `collections` de Affiliation y AffiliationCorporate.
 * - `collections_status_due_index`: cuotas POR PAGAR y filtros por rango de
 *   vencimiento.
 * - `collections_sale_id_index`: cuotas de una venta (relaciones, borrado de
 *   ventas, cobranza de pagos).
 *
 * Aditiva e idempotente: se aplica con `migrate --path`.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private array $indexes = [
        'collections_affiliation_status_due_index' => ['affiliation_code', 'status', 'filter_next_payment_date'],
        'collections_status_due_index' => ['status', 'filter_next_payment_date'],
        'collections_sale_id_index' => ['sale_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $name => $columns) {
            if (Schema::hasIndex('collections', $name)) {
                continue;
            }

            Schema::table('collections', function (Blueprint $table) use ($name, $columns): void {
                $table->index($columns, $name);
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->indexes) as $name) {
            if (! Schema::hasIndex('collections', $name)) {
                continue;
            }

            Schema::table('collections', function (Blueprint $table) use ($name): void {
                $table->dropIndex($name);
            });
        }
    }
};
