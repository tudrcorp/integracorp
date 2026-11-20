<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una cotización pendiente de un servicio médico puede anularse desde «Gestionar
 * cotizaciones» para volver a cotizar: la fila queda con estatus ANULADA y aquí se
 * guarda quién, cuándo y por qué.
 *
 * Aditiva e idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    private const TABLE = 'operation_quote_generators';

    /** @var list<string> */
    private const COLUMNS = ['annulled_at', 'annulled_by_user_id', 'annulled_by_name', 'annulment_reason'];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (! Schema::hasColumn(self::TABLE, 'annulled_at')) {
                $table->timestamp('annulled_at')->nullable();
            }

            if (! Schema::hasColumn(self::TABLE, 'annulled_by_user_id')) {
                $table->unsignedBigInteger('annulled_by_user_id')->nullable();
            }

            if (! Schema::hasColumn(self::TABLE, 'annulled_by_name')) {
                $table->string('annulled_by_name', 255)->nullable();
            }

            if (! Schema::hasColumn(self::TABLE, 'annulment_reason')) {
                $table->text('annulment_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $columns = array_values(array_filter(
            self::COLUMNS,
            static fn (string $column): bool => Schema::hasColumn(self::TABLE, $column),
        ));

        if ($columns !== []) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
