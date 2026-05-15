<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use App\Models\TelemedicineListStudy;

/**
 * Resuelve si un ítem de laboratorio, estudio o especialista está cubierto según el catálogo maestro (type).
 */
final class TelemedicineCoverageCatalog
{
    /** @var array<string, string>|null name trimmed => type */
    private static ?array $laboratoryTypeByName = null;

    /** @var array<string, string>|null */
    private static ?array $studyTypeByName = null;

    /** @var array<string, string>|null */
    private static ?array $specialistTypeByName = null;

    public static function laboratoryIsCovered(string $name): bool
    {
        self::ensureLaboratoryMap();

        return self::itemIsCoveredFromCatalogType(self::$laboratoryTypeByName[self::normalizeKey($name)] ?? null);
    }

    public static function studyIsCovered(string $name): bool
    {
        self::ensureStudyMap();

        return self::itemIsCoveredFromCatalogType(self::$studyTypeByName[self::normalizeKey($name)] ?? null);
    }

    public static function specialistIsCovered(string $name): bool
    {
        self::ensureSpecialistMap();

        return self::itemIsCoveredFromCatalogType(self::$specialistTypeByName[self::normalizeKey($name)] ?? null);
    }

    /**
     * Sin coincidencia en catálogo: se asume cubierto (datos provienen del bloque «incluidos»).
     * Con tipo explícito «NO CUBIERTO»: no cubierto.
     */
    public static function itemIsCoveredFromCatalogType(?string $catalogType): bool
    {
        if ($catalogType === null || $catalogType === '') {
            return true;
        }

        return strtoupper(trim($catalogType)) === 'CUBIERTO';
    }

    private static function normalizeKey(string $name): string
    {
        return trim($name);
    }

    private static function ensureLaboratoryMap(): void
    {
        if (self::$laboratoryTypeByName !== null) {
            return;
        }

        self::$laboratoryTypeByName = TelemedicineListLaboratory::query()
            ->get(['name', 'type'])
            ->mapWithKeys(fn ($row): array => [self::normalizeKey((string) $row->name) => (string) $row->type])
            ->all();
    }

    private static function ensureStudyMap(): void
    {
        if (self::$studyTypeByName !== null) {
            return;
        }

        self::$studyTypeByName = TelemedicineListStudy::query()
            ->get(['name', 'type'])
            ->mapWithKeys(fn ($row): array => [self::normalizeKey((string) $row->name) => (string) $row->type])
            ->all();
    }

    private static function ensureSpecialistMap(): void
    {
        if (self::$specialistTypeByName !== null) {
            return;
        }

        self::$specialistTypeByName = TelemedicineListSpecialist::query()
            ->get(['name', 'type'])
            ->mapWithKeys(fn ($row): array => [self::normalizeKey((string) $row->name) => (string) $row->type])
            ->all();
    }
}
