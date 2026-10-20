<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('birthday_notifications') || ! Schema::hasColumn('birthday_notifications', 'data_type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        foreach (DB::table('birthday_notifications')->select('id', 'data_type')->get() as $row) {
            $normalized = $this->normalizeToJsonArray($row->data_type);

            DB::table('birthday_notifications')
                ->where('id', $row->id)
                ->update(['data_type' => $normalized]);
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE birthday_notifications MODIFY data_type JSON NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('birthday_notifications') || ! Schema::hasColumn('birthday_notifications', 'data_type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        foreach (DB::table('birthday_notifications')->select('id', 'data_type')->get() as $row) {
            $types = json_decode((string) $row->data_type, true);
            $scalar = is_array($types) ? (string) ($types[0] ?? '') : (string) $row->data_type;

            DB::table('birthday_notifications')
                ->where('id', $row->id)
                ->update(['data_type' => $scalar]);
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE birthday_notifications MODIFY data_type VARCHAR(255) NULL');
        }
    }

    private function normalizeToJsonArray(mixed $value): string
    {
        if ($value === null || $value === '') {
            return json_encode([]);
        }

        if (is_array($value)) {
            return json_encode(array_values(array_filter(
                $value,
                static fn (mixed $item): bool => is_string($item) && $item !== '',
            )));
        }

        $string = trim((string) $value);
        $decoded = json_decode($string, true);

        if (is_array($decoded)) {
            return json_encode(array_values(array_filter(
                $decoded,
                static fn (mixed $item): bool => is_string($item) && $item !== '',
            )));
        }

        return json_encode([$string]);
    }
};
