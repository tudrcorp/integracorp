<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

final class TelemedicineCoverageDocumentSplit
{
    public const GROUP_COVERED = 'cubiertos';

    public const GROUP_UNCOVERED = 'no-cubiertos';

    public const COVERED_SUFFIX = 'Cubierto';

    /**
     * Lo que el plan no cubre se refiere al seguro del paciente.
     */
    public const REFERRED_SUFFIX = 'Referido-Seguro';

    /**
     * Nombre con el que cada documento aparece en el archivo, sin tildes: el
     * PDF viaja por WhatsApp y por URL.
     *
     * @var array<string, string>
     */
    private const FILE_LABELS = [
        'medicamentos' => 'Medicamentos',
        'laboratorios' => 'Laboratorios',
        'imagenologia' => 'Imagenologia',
        'especialista' => 'Especialista',
    ];

    public static function label(string $group): string
    {
        return $group === self::GROUP_COVERED ? 'Cubiertos' : 'No cubiertos';
    }

    public static function fileKey(string $baseType, string $group): string
    {
        $label = self::FILE_LABELS[$baseType] ?? $baseType;

        return $label.'-'.($group === self::GROUP_COVERED ? self::COVERED_SUFFIX : self::REFERRED_SUFFIX);
    }

    /**
     * Incluye los nombres en desuso: al regenerar hay que borrar también los
     * archivos emitidos con los esquemas anteriores, o el caso terminaría con
     * varios documentos del mismo tipo en el hub. En producción el sistema de
     * archivos distingue mayúsculas, así que las grafías viejas en minúscula
     * son archivos distintos de los nuevos y hay que nombrarlas aparte.
     *
     * @return list<string>
     */
    public static function familyFileKeys(string $baseType): array
    {
        return [
            $baseType,
            $baseType.'-'.self::GROUP_COVERED,
            $baseType.'-'.self::GROUP_UNCOVERED,
            $baseType.'-referido-seguro',
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
