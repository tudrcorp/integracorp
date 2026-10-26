<?php

declare(strict_types=1);

namespace App\Support\Affiliations\PersonIdentity;

use App\Enums\PersonIdentitySource;
use App\Models\AffiliateCorporate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas de identidad comparable (`identity_document_key`, `identity_name_key`,
 * `identity_birth_date`) de las tres poblaciones.
 *
 * El observer las llena en cada guardado. Las escrituras que no pasan por Eloquent (`update()` de
 * query builder, `insert()` por lotes) las dejan viejas: la revisión diaria las recalcula todas
 * antes de buscar duplicados, así que un desfase dura como mucho un día y nunca esconde un caso.
 */
final class PersonIdentityIndex
{
    public const DOCUMENT_COLUMN = 'identity_document_key';

    public const NAME_COLUMN = 'identity_name_key';

    public const BIRTH_COLUMN = 'identity_birth_date';

    /**
     * @return array{identity_document_key: ?string, identity_name_key: ?string, identity_birth_date: ?string}
     */
    public static function keysFor(Model $model): array
    {
        $source = PersonIdentitySource::fromModel($model);

        return self::keysForValues(
            $model instanceof AffiliateCorporate
                ? [(string) $model->first_name, (string) $model->last_name]
                : [(string) $model->getAttribute('full_name')],
            $model->getAttribute($source->documentColumn()),
            $model->getAttribute('birth_date'),
        );
    }

    /**
     * @param  list<string>  $nameParts
     * @return array{identity_document_key: ?string, identity_name_key: ?string, identity_birth_date: ?string}
     */
    public static function keysForValues(array $nameParts, mixed $document, mixed $birthDate): array
    {
        return [
            self::DOCUMENT_COLUMN => PersonIdentityNormalizer::documentKey($document),
            self::NAME_COLUMN => PersonIdentityNormalizer::nameKey(...$nameParts),
            self::BIRTH_COLUMN => PersonIdentityNormalizer::birthDateKey($birthDate),
        ];
    }

    /**
     * @param  array<string, mixed>  $row  una fila de `affiliate_corporates` lista para `insert()`
     * @return array<string, mixed>
     */
    public static function withCorporateKeys(array $row): array
    {
        return [
            ...$row,
            ...self::keysForValues(
                [(string) ($row['first_name'] ?? ''), (string) ($row['last_name'] ?? '')],
                $row['nro_identificacion'] ?? null,
                $row['birth_date'] ?? null,
            ),
        ];
    }

    /**
     * Si la tabla del modelo ya tiene las columnas de identidad, en la conexión del propio modelo
     * (antes de aplicar la migración, o en una base de pruebas con su propio esquema, no las tiene).
     */
    public static function isAvailable(?Model $model = null): bool
    {
        static $available = [];

        $model ??= new AffiliateCorporate;
        $key = ($model->getConnectionName() ?? 'default').'|'.$model->getTable();

        if (($available[$key] ?? false) === true) {
            return true;
        }

        return $available[$key] = $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), self::DOCUMENT_COLUMN);
    }

    /**
     * Recalcula las claves de toda la población. Solo escribe las filas cuyo valor cambió, por query
     * builder (no toca `updated_at` ni dispara observers).
     *
     * @return array<string, int> filas actualizadas por población
     */
    public static function rebuildAll(): array
    {
        $updated = [];

        foreach (PersonIdentitySource::cases() as $source) {
            $updated[$source->value] = self::rebuild($source);
        }

        return $updated;
    }

    public static function rebuild(PersonIdentitySource $source): int
    {
        if (! Schema::hasTable($source->table()) || ! Schema::hasColumn($source->table(), self::DOCUMENT_COLUMN)) {
            return 0;
        }

        $nameColumns = $source === PersonIdentitySource::Corporate ? ['first_name', 'last_name'] : ['full_name'];
        $updated = 0;

        DB::table($source->table())
            ->select(['id', ...$nameColumns, $source->documentColumn(), 'birth_date', self::DOCUMENT_COLUMN, self::NAME_COLUMN, self::BIRTH_COLUMN])
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use ($source, $nameColumns, &$updated): void {
                foreach ($rows as $row) {
                    $keys = self::keysForValues(
                        array_map(static fn (string $column): string => (string) ($row->{$column} ?? ''), $nameColumns),
                        $row->{$source->documentColumn()},
                        $row->birth_date,
                    );

                    $current = [
                        self::DOCUMENT_COLUMN => $row->{self::DOCUMENT_COLUMN},
                        self::NAME_COLUMN => $row->{self::NAME_COLUMN},
                        self::BIRTH_COLUMN => $row->{self::BIRTH_COLUMN} !== null ? substr((string) $row->{self::BIRTH_COLUMN}, 0, 10) : null,
                    ];

                    if ($keys === $current) {
                        continue;
                    }

                    DB::table($source->table())->where('id', $row->id)->update($keys);
                    $updated++;
                }
            });

        return $updated;
    }
}
