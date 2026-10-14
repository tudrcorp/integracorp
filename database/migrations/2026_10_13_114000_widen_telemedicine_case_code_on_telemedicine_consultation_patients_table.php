<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El código de caso (cinco dígitos, guion, 0 y el id) mide 11 caracteres
 * desde el id 1000. La consulta copia ese valor y MySQL rechaza el insert
 * porque la columna era varchar(10). Se iguala al varchar(255) de
 * telemedicine_cases.code.
 */
return new class extends Migration
{
    private const TABLE = 'telemedicine_consultation_patients';

    private const COLUMN = 'telemedicine_case_code';

    private const LENGTH = 255;

    public function up(): void
    {
        if (! $this->shouldWiden()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->string(self::COLUMN, self::LENGTH)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        $length = $this->characterLength();

        if ($length !== null && $length <= 10) {
            return;
        }

        $longest = DB::table(self::TABLE)
            ->selectRaw('MAX(CHAR_LENGTH(`'.self::COLUMN.'`)) as longest')
            ->value('longest');

        if ((int) $longest > 10) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->string(self::COLUMN, 10)->nullable(false)->change();
        });
    }

    private function shouldWiden(): bool
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return false;
        }

        $type = $this->columnType();

        if (in_array($type, ['text', 'mediumtext', 'longtext'], true)) {
            return false;
        }

        $length = $this->characterLength();

        if ($length === null) {
            return true;
        }

        return $length < self::LENGTH;
    }

    private function characterLength(): ?int
    {
        $type = $this->columnType();

        if (preg_match('/(?:var)?char\((\d+)\)/', $type, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function columnType(): string
    {
        $column = collect(Schema::getColumns(self::TABLE))->firstWhere('name', self::COLUMN);

        if (! is_array($column)) {
            return '';
        }

        return strtolower((string) ($column['type'] ?? ''));
    }
};
