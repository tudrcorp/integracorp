<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pasa el catálogo viejo (`upgrade_benefits`) al nuevo y liga a él los upgrades
 * corporativos ya asignados, sin tocar ningún monto cobrado.
 *
 * Cada upgrade viejo queda con una cobertura única (sin monto) y un rango de 0 a
 * 120 años a su precio de siempre: así una renovación lo vuelve a cotizar al
 * mismo precio. El registro «prueba» pasa como INACTIVO.
 *
 * Idempotente: `upgrades.legacy_upgrade_benefit_id` marca lo ya migrado y solo se
 * ligan las asignaciones que aún no tienen `upgrade_id`.
 */
return new class extends Migration
{
    private const ACTOR = 'MIGRACIÓN CATÁLOGO UPGRADES';

    private const INACTIVE_LEGACY_NAMES = ['PRUEBA'];

    public function up(): void
    {
        if (! Schema::hasTable('upgrades') || ! Schema::hasTable('upgrade_benefits')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            foreach (DB::table('upgrade_benefits')->orderBy('id')->get() as $legacy) {
                if (DB::table('upgrades')->where('legacy_upgrade_benefit_id', $legacy->id)->exists()) {
                    continue;
                }

                $name = $this->normalizeName((string) $legacy->description);
                $active = strtoupper((string) $legacy->status) === 'ACTIVO' && ! in_array($name, self::INACTIVE_LEGACY_NAMES, true);

                $this->createCatalogEntry($name !== '' ? $name : 'UPGRADE '.$legacy->id, round((float) $legacy->price, 2), $active, (int) $legacy->id, $now);
            }

            if (! Schema::hasTable('affiliate_corporate_upgrades') || ! Schema::hasColumn('affiliate_corporate_upgrades', 'upgrade_id')) {
                return;
            }

            $pending = DB::table('affiliate_corporate_upgrades')->whereNull('upgrade_id')->orderBy('id')->get();

            foreach ($pending as $assignment) {
                $upgradeId = $assignment->upgrade_benefit_id !== null
                    ? DB::table('upgrades')->where('legacy_upgrade_benefit_id', $assignment->upgrade_benefit_id)->value('id')
                    : null;

                $upgradeId ??= DB::table('upgrades')->where('name', $this->normalizeName((string) $assignment->name))->orderBy('id')->value('id');

                /** Un upgrade escrito a mano que nunca llegó al catálogo: entra como histórico inactivo. */
                $upgradeId ??= $this->createCatalogEntry($this->normalizeName((string) $assignment->name), round((float) $assignment->amount, 2), false, null, $now);

                $coverageId = DB::table('upgrade_coverages')->where('upgrade_id', $upgradeId)->orderBy('id')->value('id');
                $rangeId = $coverageId !== null
                    ? DB::table('upgrade_coverage_age_ranges')->where('upgrade_coverage_id', $coverageId)->orderBy('id')->value('id')
                    : null;

                DB::table('affiliate_corporate_upgrades')->where('id', $assignment->id)->update([
                    'upgrade_id' => $upgradeId,
                    'upgrade_coverage_id' => $coverageId,
                    'upgrade_coverage_age_range_id' => $rangeId,
                ]);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('upgrades')) {
            return;
        }

        if (Schema::hasTable('affiliate_corporate_upgrades') && Schema::hasColumn('affiliate_corporate_upgrades', 'upgrade_id')) {
            DB::table('affiliate_corporate_upgrades')->update([
                'upgrade_id' => null,
                'upgrade_coverage_id' => null,
                'upgrade_coverage_age_range_id' => null,
            ]);
        }
    }

    private function createCatalogEntry(string $name, float $price, bool $active, ?int $legacyId, mixed $now): int
    {
        $status = $active ? 'ACTIVO' : 'INACTIVO';

        $upgradeId = (int) DB::table('upgrades')->insertGetId([
            'code' => 'UPG-TMP-'.bin2hex(random_bytes(8)),
            'name' => mb_substr($name, 0, 120),
            'description' => 'Migrado del catálogo anterior de upgrades.',
            'status' => $status,
            'legacy_upgrade_benefit_id' => $legacyId,
            'created_by' => self::ACTOR,
            'updated_by' => self::ACTOR,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('upgrades')->where('id', $upgradeId)->update(['code' => 'UPG-'.str_pad((string) $upgradeId, 4, '0', STR_PAD_LEFT)]);

        $coverageId = (int) DB::table('upgrade_coverages')->insertGetId([
            'upgrade_id' => $upgradeId,
            'amount' => null,
            'sort' => 0,
            'status' => $status,
            'created_by' => self::ACTOR,
            'updated_by' => self::ACTOR,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('upgrade_coverage_age_ranges')->insert([
            'upgrade_coverage_id' => $coverageId,
            'age_from' => 0,
            'age_to' => 120,
            'price' => $price,
            'status' => $status,
            'created_by' => self::ACTOR,
            'updated_by' => self::ACTOR,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $upgradeId;
    }

    private function normalizeName(string $name): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', ' ', trim($name)) ?? '', 'UTF-8');
    }
};
