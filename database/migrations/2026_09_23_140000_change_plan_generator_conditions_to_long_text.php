<?php

declare(strict_types=1);

use App\Support\PlanGenerators\PlanGeneratorConditions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El analista pega las condiciones como un solo texto, con sus saltos de línea.
 * La columna pasa de JSON (lista de filas, tope corto) a LONGTEXT.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_generators')) {
            return;
        }

        if (! Schema::hasColumn('plan_generators', 'conditions')) {
            Schema::table('plan_generators', function (Blueprint $table): void {
                $table->longText('conditions')->nullable();
            });

            return;
        }

        if ($this->columnType() === 'longtext') {
            return;
        }

        $stored = DB::table('plan_generators')
            ->whereNotNull('conditions')
            ->pluck('conditions', 'id');

        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->longText('conditions')->nullable()->change();
        });

        foreach ($stored as $id => $value) {
            $text = PlanGeneratorConditions::fromLegacyStorage($value);

            DB::table('plan_generators')
                ->where('id', $id)
                ->update([
                    'conditions' => $text === '' ? null : $text,
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('plan_generators') || ! Schema::hasColumn('plan_generators', 'conditions')) {
            return;
        }

        if ($this->columnType() === 'json') {
            return;
        }

        $stored = DB::table('plan_generators')
            ->whereNotNull('conditions')
            ->pluck('conditions', 'id');

        foreach ($stored as $id => $value) {
            DB::table('plan_generators')
                ->where('id', $id)
                ->update([
                    'conditions' => json_encode((string) $value, JSON_UNESCAPED_UNICODE),
                ]);
        }

        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->json('conditions')->nullable()->change();
        });
    }

    private function columnType(): string
    {
        $column = collect(Schema::getColumns('plan_generators'))
            ->firstWhere('name', 'conditions');

        return strtolower((string) ($column['type_name'] ?? ''));
    }
};
