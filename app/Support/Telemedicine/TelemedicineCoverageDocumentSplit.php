<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

final class TelemedicineCoverageDocumentSplit
{
    public const GROUP_COVERED = 'cubiertos';

    public const GROUP_UNCOVERED = 'no-cubiertos';

    public static function label(string $group): string
    {
        return $group === self::GROUP_COVERED ? 'Cubiertos' : 'No cubiertos';
    }

    public static function fileKey(string $baseType, string $group): string
    {
        return $baseType.'-'.$group;
    }

    /**
     * @return list<string>
     */
    public static function familyFileKeys(string $baseType): array
    {
        return [
            $baseType,
            self::fileKey($baseType, self::GROUP_COVERED),
            self::fileKey($baseType, self::GROUP_UNCOVERED),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function filename(array $data, string $fileKey): string
    {
        $ci = trim((string) ($data['ci_patiente'] ?? ($data['ci_patient'] ?? '')));
        $reference = trim((string) ($data['code_reference'] ?? ''));

        return $ci.'-'.$reference.'-'.$fileKey.'.pdf';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function familyFilenames(array $data, string $baseType): array
    {
        $names = [];

        foreach (self::familyFileKeys($baseType) as $fileKey) {
            $names[] = self::filename($data, $fileKey);
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{group: string, payload: array<string, mixed>}>
     */
    public static function medicationGroups(array $data): array
    {
        $normalized = TelemedicineMedicationsPdfRows::normalize(
            is_array($data['medicationsArr'] ?? null) ? $data['medicationsArr'] : []
        );

        $covered = [];
        $uncovered = [];

        foreach ($normalized as $row) {
            if (self::rowIsCovered($row)) {
                $covered[] = $row;
            } else {
                $uncovered[] = $row;
            }
        }

        return self::groupsFromPartition($data, $covered, $uncovered, static function (array $payload, array $rows, string $group): array {
            $payload['medicationsArr'] = $rows;

            return $payload;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{group: string, payload: array<string, mixed>}>
     */
    public static function orderGroups(string $docType, array $data): array
    {
        $covered = [];
        $uncovered = [];

        foreach (TelemedicineDocumentOrderItems::forDocument($docType, $data) as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            if (($item['coverage'] ?? '') === 'Cubierto') {
                $covered[] = $label;
            } else {
                $uncovered[] = $label;
            }
        }

        return self::groupsFromPartition($data, $covered, $uncovered, static function (array $payload, array $labels, string $group) use ($docType): array {
            return self::orderPayloadForGroup($docType, $payload, $labels, $group === self::GROUP_COVERED);
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function rowIsCovered(array $row): bool
    {
        return TelemedicineMedicationCoverage::shortPdfCoverageLabel(
            TelemedicineMedicationCoverage::pdfCoverageLabelFromRow($row)
        ) === 'Cubierto';
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<mixed>  $covered
     * @param  list<mixed>  $uncovered
     * @param  callable(array<string, mixed>, list<mixed>, string): array<string, mixed>  $applyRows
     * @return list<array{group: string, payload: array<string, mixed>}>
     */
    private static function groupsFromPartition(array $data, array $covered, array $uncovered, callable $applyRows): array
    {
        $groups = [];

        foreach ([
            self::GROUP_COVERED => $covered,
            self::GROUP_UNCOVERED => $uncovered,
        ] as $group => $rows) {
            if ($rows === []) {
                continue;
            }

            $payload = $applyRows($data, $rows, $group);
            $payload['coverage_group'] = self::label($group);
            $groups[] = [
                'group' => $group,
                'payload' => $payload,
            ];
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $labels
     * @return array<string, mixed>
     */
    private static function orderPayloadForGroup(string $docType, array $data, array $labels, bool $isCovered): array
    {
        $covered = $isCovered ? $labels : [];
        $other = $isCovered ? [] : $labels;

        return match ($docType) {
            'imagenologia' => array_merge($data, [
                'studies' => $covered,
                'studiesArr' => $covered,
                'other_studies' => $other,
                'otherStudiesArr' => $other,
            ]),
            'especialista' => array_merge($data, [
                'consultSpecialistArr' => $covered,
                'consult_specialist' => $covered,
                'other_specialist' => $other,
                'otherSpecialistArr' => $other,
            ]),
            default => array_merge($data, [
                'labs' => $covered,
                'labsArr' => $covered,
                'other_labs' => $other,
                'otherLabsArr' => $other,
            ]),
        };
    }
}
