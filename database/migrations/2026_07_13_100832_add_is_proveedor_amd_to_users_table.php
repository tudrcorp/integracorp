<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_DEPARTAMENT = 'PROVEEDOR AMD';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'is_proveedor_amd')) {
                $table->boolean('is_proveedor_amd')->default(false)->after('supplier_id');
            }
        });

        $this->backfillFromLegacyDepartament();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_proveedor_amd')) {
            DB::table('users')
                ->where('is_proveedor_amd', true)
                ->orderBy('id')
                ->chunkById(100, function ($users): void {
                    foreach ($users as $user) {
                        $departaments = $this->decodeDepartaments($user->departament);

                        if (! in_array(self::LEGACY_DEPARTAMENT, $departaments, true)) {
                            $departaments[] = self::LEGACY_DEPARTAMENT;
                        }

                        DB::table('users')
                            ->where('id', $user->id)
                            ->update([
                                'departament' => json_encode(array_values($departaments)),
                            ]);
                    }
                });
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'is_proveedor_amd')) {
                $table->dropColumn('is_proveedor_amd');
            }
        });
    }

    private function backfillFromLegacyDepartament(): void
    {
        DB::table('users')
            ->whereNotNull('departament')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $departaments = $this->decodeDepartaments($user->departament);

                    if (! in_array(self::LEGACY_DEPARTAMENT, $departaments, true)) {
                        continue;
                    }

                    $cleaned = array_values(array_filter(
                        $departaments,
                        fn (mixed $departament): bool => $departament !== self::LEGACY_DEPARTAMENT,
                    ));

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'is_proveedor_amd' => true,
                            'departament' => json_encode($cleaned),
                        ]);
                }
            });
    }

    /**
     * @return list<mixed>
     */
    private function decodeDepartaments(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values($raw);
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
};
