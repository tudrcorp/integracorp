<?php

declare(strict_types=1);

namespace App\Support\Upgrades;

use App\Models\AffiliateCorporateUpgrade;
use App\Models\AffiliateUpgrade;
use App\Models\Upgrade;
use App\Models\UpgradeCoverage;
use App\Models\UpgradeCoverageAgeRange;
use App\Models\UpgradeService;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Escribe un upgrade del catálogo con sus servicios, coberturas y rangos, todo en
 * una transacción.
 *
 * - Lo que el analista quita del formulario no se borra: pasa a INACTIVO.
 * - Una cobertura o un rango que ya usa algún afiliado no se modifica en el
 *   lugar: se inactiva y se crea uno nuevo con los valores nuevos. Así la
 *   asignación sigue apuntando a lo que realmente se le cobró.
 * - Lo que nadie usa todavía se actualiza en el lugar.
 */
final class UpgradeCatalogPersistence
{
    /**
     * @param  array<string, mixed>  $data  estado del formulario
     */
    public static function save(?Upgrade $upgrade, array $data, ?string $actor = null): Upgrade
    {
        $errors = UpgradeStructureValidator::errors($data);

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $data = UpgradeStructureValidator::normalize($data);
        $actor = self::actorName($actor);

        return DB::transaction(function () use ($upgrade, $data, $actor): Upgrade {
            $isNew = $upgrade === null || ! $upgrade->exists;
            $record = $isNew
                ? self::createHeader($data, $actor)
                : self::updateHeader($upgrade, $data, $actor);

            $changes = [
                'services' => self::syncServices($record, $data['services'], $actor),
                'coverages' => self::syncCoverages($record, $data['coverages'], $actor),
            ];

            SecurityAudit::log(
                $isNew ? 'AUDIT_BUSINESS_UPGRADE_CREATED' : 'AUDIT_BUSINESS_UPGRADE_UPDATED',
                $isNew ? 'business.upgrades.create' : 'business.upgrades.update',
                [
                    'panel' => 'business',
                    'module' => 'upgrades',
                    'upgrade_id' => $record->getKey(),
                    'code' => $record->code,
                    'name' => $record->name,
                    'status' => $record->status,
                    'changes' => $changes,
                ],
            );

            return $record->refresh();
        });
    }

    /**
     * Activa o inactiva el upgrade sin tocar su estructura. Quien ya lo tiene lo conserva.
     */
    public static function changeStatus(Upgrade $upgrade, string $status, ?string $actor = null): Upgrade
    {
        if (! in_array($status, [Upgrade::STATUS_ACTIVE, Upgrade::STATUS_INACTIVE], true)) {
            throw new InvalidArgumentException('Estatus de upgrade no válido.');
        }

        $actor = self::actorName($actor);

        return DB::transaction(function () use ($upgrade, $status, $actor): Upgrade {
            /** @var Upgrade $locked */
            $locked = Upgrade::query()->whereKey($upgrade->getKey())->lockForUpdate()->firstOrFail();
            $previous = $locked->status;

            if ($previous === $status) {
                return $locked;
            }

            $locked->forceFill(['status' => $status, 'updated_by' => $actor])->save();

            SecurityAudit::log('AUDIT_BUSINESS_UPGRADE_STATUS_CHANGED', 'business.upgrades.status', [
                'panel' => 'business',
                'module' => 'upgrades',
                'upgrade_id' => $locked->getKey(),
                'code' => $locked->code,
                'previous_status' => $previous,
                'status' => $status,
            ]);

            return $locked;
        });
    }

    /**
     * Estado del formulario a partir de lo activo en la base.
     *
     * @return array<string, mixed>
     */
    public static function formState(Upgrade $upgrade): array
    {
        $upgrade->loadMissing(['activeServices', 'activeCoverages.activeAgeRanges']);

        return [
            'name' => $upgrade->name,
            'description' => $upgrade->description,
            'image_path' => $upgrade->image_path,
            'status' => $upgrade->status,
            'services' => $upgrade->activeServices
                ->mapWithKeys(fn (UpgradeService $service): array => ['service-'.$service->getKey() => [
                    'id' => (int) $service->getKey(),
                    'name' => $service->name,
                    'description' => $service->description,
                ]])
                ->all(),
            'coverages' => $upgrade->activeCoverages
                ->mapWithKeys(fn (UpgradeCoverage $coverage): array => ['coverage-'.$coverage->getKey() => [
                    'id' => (int) $coverage->getKey(),
                    'amount' => $coverage->amount !== null ? number_format((float) $coverage->amount, 2, '.', '') : null,
                    'ranges' => $coverage->activeAgeRanges
                        ->mapWithKeys(fn (UpgradeCoverageAgeRange $range): array => ['range-'.$range->getKey() => [
                            'id' => (int) $range->getKey(),
                            'age_from' => $range->age_from,
                            'age_to' => $range->age_to,
                            'price' => number_format((float) $range->price, 2, '.', ''),
                        ]])
                        ->all(),
                ]])
                ->all(),
        ];
    }

    /**
     * @param  array{name: string, description: ?string, image_path?: ?string, status: string}  $data
     */
    /**
     * La imagen solo se escribe si la columna existe: sin la migración, crear y editar upgrades
     * siguen funcionando.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private static function imageAttributes(array $data): array
    {
        return Upgrade::hasImageColumn() ? ['image_path' => $data['image_path'] ?? null] : [];
    }

    private static function createHeader(array $data, string $actor): Upgrade
    {
        $upgrade = Upgrade::query()->create([
            'code' => 'UPG-TMP-'.Str::lower(Str::random(16)),
            'name' => $data['name'],
            'description' => $data['description'],
            'status' => $data['status'],
            'created_by' => $actor,
            'updated_by' => $actor,
            ...self::imageAttributes($data),
        ]);

        $upgrade->forceFill(['code' => 'UPG-'.str_pad((string) $upgrade->getKey(), 4, '0', STR_PAD_LEFT)])->save();

        return $upgrade;
    }

    /**
     * @param  array{name: string, description: ?string, image_path?: ?string, status: string}  $data
     */
    private static function updateHeader(Upgrade $upgrade, array $data, string $actor): Upgrade
    {
        /** @var Upgrade $locked */
        $locked = Upgrade::query()->whereKey($upgrade->getKey())->lockForUpdate()->firstOrFail();

        $locked->fill([
            'name' => $data['name'],
            'description' => $data['description'],
            'status' => $data['status'],
            'updated_by' => $actor,
            ...self::imageAttributes($data),
        ])->save();

        return $locked;
    }

    /**
     * @param  list<array{id: ?int, name: string, description: ?string}>  $services
     * @return array{created: int, updated: int, deactivated: int}
     */
    private static function syncServices(Upgrade $upgrade, array $services, string $actor): array
    {
        $existing = $upgrade->services()->where('status', Upgrade::STATUS_ACTIVE)->lockForUpdate()->get()->keyBy('id');
        $kept = [];
        $stats = ['created' => 0, 'updated' => 0, 'deactivated' => 0];

        foreach ($services as $sort => $service) {
            $current = $service['id'] !== null ? $existing->get($service['id']) : null;

            if ($current !== null) {
                $current->fill(['name' => $service['name'], 'description' => $service['description'], 'sort' => $sort]);

                if ($current->isDirty()) {
                    $current->updated_by = $actor;
                    $current->save();
                    $stats['updated']++;
                }

                $kept[] = (int) $current->getKey();

                continue;
            }

            $upgrade->services()->create([
                'name' => $service['name'],
                'description' => $service['description'],
                'sort' => $sort,
                'status' => Upgrade::STATUS_ACTIVE,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);
            $stats['created']++;
        }

        $stats['deactivated'] = self::deactivate(UpgradeService::query(), $existing->keys()->diff($kept)->all(), $actor);

        return $stats;
    }

    /**
     * @param  list<array{id: ?int, amount: ?float, ranges: list<array{id: ?int, age_from: ?int, age_to: ?int, price: ?float}>}>  $coverages
     * @return array{created: int, updated: int, versioned: int, deactivated: int, ranges: array{created: int, updated: int, versioned: int, deactivated: int}}
     */
    private static function syncCoverages(Upgrade $upgrade, array $coverages, string $actor): array
    {
        $existing = $upgrade->coverages()->where('status', Upgrade::STATUS_ACTIVE)->lockForUpdate()->get()->keyBy('id');
        $kept = [];
        $stats = ['created' => 0, 'updated' => 0, 'versioned' => 0, 'deactivated' => 0];
        $rangeStats = ['created' => 0, 'updated' => 0, 'versioned' => 0, 'deactivated' => 0];

        foreach ($coverages as $sort => $coverage) {
            /** @var UpgradeCoverage|null $current */
            $current = $coverage['id'] !== null ? $existing->get($coverage['id']) : null;
            $amountChanged = $current !== null && self::amountKey($current->amount) !== self::amountKey($coverage['amount']);

            if ($current !== null && $amountChanged && self::coverageIsReferenced($current)) {
                /** Cambió el monto de una cobertura en uso: la vieja sale de $kept y queda como historia. */
                $stats['versioned']++;
                $current = null;
                $coverage['ranges'] = array_map(fn (array $range): array => ['id' => null] + $range, $coverage['ranges']);
            }

            if ($current === null) {
                $current = $upgrade->coverages()->create([
                    'amount' => $coverage['amount'],
                    'sort' => $sort,
                    'status' => Upgrade::STATUS_ACTIVE,
                    'created_by' => $actor,
                    'updated_by' => $actor,
                ]);

                if ($coverage['id'] === null) {
                    $stats['created']++;
                }
            } else {
                $current->fill(['amount' => $coverage['amount'], 'sort' => $sort]);

                if ($current->isDirty()) {
                    $current->updated_by = $actor;
                    $current->save();
                    $stats['updated']++;
                }

                $kept[] = (int) $current->getKey();
            }

            foreach (self::syncRanges($current, $coverage['ranges'], $actor) as $key => $count) {
                $rangeStats[$key] += $count;
            }
        }

        $removed = $existing->keys()->diff($kept)->all();

        foreach ($existing->only($removed) as $coverage) {
            $rangeStats['deactivated'] += self::deactivate(
                UpgradeCoverageAgeRange::query(),
                $coverage->ageRanges()->where('status', Upgrade::STATUS_ACTIVE)->pluck('id')->all(),
                $actor,
            );
        }

        $stats['deactivated'] = self::deactivate(UpgradeCoverage::query(), $removed, $actor);

        return $stats + ['ranges' => $rangeStats];
    }

    /**
     * @param  list<array{id: ?int, age_from: ?int, age_to: ?int, price: ?float}>  $ranges
     * @return array{created: int, updated: int, versioned: int, deactivated: int}
     */
    private static function syncRanges(UpgradeCoverage $coverage, array $ranges, string $actor): array
    {
        $existing = $coverage->ageRanges()->where('status', Upgrade::STATUS_ACTIVE)->lockForUpdate()->get()->keyBy('id');
        $kept = [];
        $stats = ['created' => 0, 'updated' => 0, 'versioned' => 0, 'deactivated' => 0];

        foreach ($ranges as $range) {
            /** @var UpgradeCoverageAgeRange|null $current */
            $current = $range['id'] !== null ? $existing->get($range['id']) : null;

            if ($current !== null) {
                $changed = (int) $current->age_from !== $range['age_from']
                    || (int) $current->age_to !== $range['age_to']
                    || self::amountKey($current->price) !== self::amountKey($range['price']);

                if (! $changed) {
                    $kept[] = (int) $current->getKey();

                    continue;
                }

                if (! self::rangeIsReferenced($current)) {
                    $current->fill([
                        'age_from' => $range['age_from'],
                        'age_to' => $range['age_to'],
                        'price' => $range['price'],
                        'updated_by' => $actor,
                    ])->save();
                    $kept[] = (int) $current->getKey();
                    $stats['updated']++;

                    continue;
                }

                /** Rango en uso: se deja como historia (sale de $kept) y se crea el nuevo. */
                $stats['versioned']++;
            } else {
                $stats['created']++;
            }

            $coverage->ageRanges()->create([
                'age_from' => $range['age_from'],
                'age_to' => $range['age_to'],
                'price' => $range['price'],
                'status' => Upgrade::STATUS_ACTIVE,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);
        }

        $stats['deactivated'] = self::deactivate(UpgradeCoverageAgeRange::query(), $existing->keys()->diff($kept)->all(), $actor);

        return $stats;
    }

    private static function coverageIsReferenced(UpgradeCoverage $coverage): bool
    {
        return AffiliateCorporateUpgrade::query()->where('upgrade_coverage_id', $coverage->getKey())->exists()
            || AffiliateUpgrade::query()->where('upgrade_coverage_id', $coverage->getKey())->exists();
    }

    private static function rangeIsReferenced(UpgradeCoverageAgeRange $range): bool
    {
        return AffiliateCorporateUpgrade::query()->where('upgrade_coverage_age_range_id', $range->getKey())->exists()
            || AffiliateUpgrade::query()->where('upgrade_coverage_age_range_id', $range->getKey())->exists();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, int|string>  $ids
     */
    private static function deactivate(\Illuminate\Database\Eloquent\Builder $query, array $ids, string $actor): int
    {
        $ids = array_values(array_map('intval', $ids));

        if ($ids === []) {
            return 0;
        }

        return $query->whereIn('id', $ids)
            ->where('status', Upgrade::STATUS_ACTIVE)
            ->update(['status' => Upgrade::STATUS_INACTIVE, 'updated_by' => $actor, 'updated_at' => now()]);
    }

    private static function amountKey(mixed $amount): string
    {
        return $amount === null || $amount === '' ? 'null' : number_format((float) $amount, 2, '.', '');
    }

    private static function actorName(?string $actor): string
    {
        $actor = trim((string) ($actor ?? Auth::user()?->name ?? ''));

        return $actor !== '' ? $actor : 'system';
    }
}
