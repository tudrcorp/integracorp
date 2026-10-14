<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use Illuminate\Support\Collection;

/**
 * Lo que el médico indicó en una consulta, tal como lo imprimen el informe
 * médico y el informe de seguimiento: medicamentos (plan terapéutico) y
 * laboratorios, estudios y especialistas (paraclínicos).
 *
 * Las listas llegan al PDF partidas en cubierto / no cubierto
 * (`labsArr` + `otherLabsArr`…) desde la creación de la consulta, y ya
 * fusionadas en `labsArr` desde la regeneración. El informe muestra las dos
 * mitades juntas: lo no cubierto también lo indicó el médico.
 */
final class TelemedicineInformeClinicalOrders
{
    /**
     * Claves del payload del informe que llevan las indicaciones clínicas.
     *
     * @var list<string>
     */
    public const PAYLOAD_KEYS = [
        'medicationsArr',
        'labsArr',
        'otherLabsArr',
        'studiesArr',
        'otherStudiesArr',
        'consultSpecialistArr',
        'otherSpecialistArr',
    ];

    /**
     * Listas listas para imprimir: sin vacíos y sin nombres repetidos.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     medications: list<array{medicines: string, indications: string}>,
     *     labs: list<string>,
     *     studies: list<string>,
     *     specialists: list<string>,
     * }
     */
    public static function forReport(array $data): array
    {
        return [
            'medications' => self::medicationsForReport($data['medicationsArr'] ?? null),
            'labs' => self::uniqueLabels($data['labsArr'] ?? null, $data['otherLabsArr'] ?? null),
            'studies' => self::uniqueLabels($data['studiesArr'] ?? null, $data['otherStudiesArr'] ?? null),
            'specialists' => self::uniqueLabels($data['consultSpecialistArr'] ?? null, $data['otherSpecialistArr'] ?? null),
        ];
    }

    /**
     * Indicaciones de una consulta ya guardada, con las claves del payload.
     *
     * @return array<string, list<mixed>>
     */
    public static function payloadFromConsultation(TelemedicineConsultationPatient $consultation): array
    {
        [$labs, $otherLabs] = self::labsSplitOf($consultation);
        [$studies, $otherStudies] = self::studiesSplitOf($consultation);
        [$specialists, $otherSpecialists] = self::specialistsSplitOf($consultation);

        return [
            'medicationsArr' => self::medicationRows(self::medicationsOf($consultation)),
            'labsArr' => $labs,
            'otherLabsArr' => $otherLabs,
            'studiesArr' => $studies,
            'otherStudiesArr' => $otherStudies,
            'consultSpecialistArr' => $specialists,
            'otherSpecialistArr' => $otherSpecialists,
        ];
    }

    /**
     * Solo las claves clínicas de un payload, con listas vacías por defecto.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, list<mixed>>
     */
    public static function onlyPayloadKeys(array $input): array
    {
        $payload = [];

        foreach (self::PAYLOAD_KEYS as $key) {
            $payload[$key] = is_array($input[$key] ?? null) ? array_values($input[$key]) : [];
        }

        return $payload;
    }

    /**
     * @return Collection<int, TelemedicinePatientMedications>
     */
    public static function medicationsOf(TelemedicineConsultationPatient $consultation): Collection
    {
        return TelemedicinePatientMedications::query()
            ->with('operationInventory')
            ->where('telemedicine_consultation_patient_id', $consultation->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  iterable<int, TelemedicinePatientMedications>  $medications
     * @return list<array{medicines: string, indications: string, duration: string}>
     */
    public static function medicationRows(iterable $medications): array
    {
        $rows = [];

        foreach ($medications as $medication) {
            $rows[] = [
                'medicines' => (string) ($medication->medicine ?? ''),
                'indications' => (string) ($medication->indications ?? ''),
                'duration' => (string) ($medication->duration ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Las filas de la relación mandan; los campos JSON de la consulta quedan
     * como respaldo para consultas anteriores a esas tablas.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    public static function labsSplitOf(TelemedicineConsultationPatient $consultation): array
    {
        $fromRelation = TelemedicinePatientLab::query()
            ->where('telemedicine_consultation_patient_id', $consultation->id)
            ->orderBy('id')
            ->get(['laboratory', 'type']);

        if ($fromRelation->isNotEmpty()) {
            return self::partitionByCoverageType($fromRelation, 'laboratory');
        }

        return [
            self::stringList($consultation->labs),
            self::stringList($consultation->other_labs),
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    public static function studiesSplitOf(TelemedicineConsultationPatient $consultation): array
    {
        $fromRelation = TelemedicinePatientStudy::query()
            ->where('telemedicine_consultation_patient_id', $consultation->id)
            ->orderBy('id')
            ->get(['study', 'type']);

        if ($fromRelation->isNotEmpty()) {
            return self::partitionByCoverageType($fromRelation, 'study');
        }

        return [
            self::stringList($consultation->studies),
            self::stringList($consultation->other_studies),
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    public static function specialistsSplitOf(TelemedicineConsultationPatient $consultation): array
    {
        $fromRelation = TelemedicinePatientSpecialty::query()
            ->where('telemedicine_consultation_patient_id', $consultation->id)
            ->orderBy('id')
            ->get(['specialty', 'type']);

        if ($fromRelation->isNotEmpty()) {
            return self::partitionByCoverageType($fromRelation, 'specialty');
        }

        return [
            self::stringList($consultation->consult_specialist),
            self::stringList($consultation->other_specialist),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{0: list<string>, 1: list<string>}
     */
    public static function partitionByCoverageType(Collection $rows, string $nameAttribute): array
    {
        $covered = [];
        $other = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row->{$nameAttribute} ?? ''));
            if ($name === '') {
                continue;
            }

            $type = isset($row->type) ? (string) $row->type : null;

            if (TelemedicineCoverageCatalog::itemIsCoveredFromCatalogType($type !== '' ? $type : null)) {
                $covered[] = $name;
            } else {
                $other[] = $name;
            }
        }

        return [$covered, $other];
    }

    /**
     * Nombres de una lista que puede traer textos sueltos o filas
     * (`name`, `specialty`, `study`, `laboratory`).
     *
     * @return list<string>
     */
    public static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $labels = [];

        foreach ($value as $item) {
            $label = is_array($item)
                ? trim((string) ($item['name'] ?? $item['specialty'] ?? $item['study'] ?? $item['laboratory'] ?? ''))
                : (is_scalar($item) ? trim((string) $item) : '');

            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * Un mismo nombre puede venir como cubierto y como no cubierto
     * (CREATININA): en el informe se imprime una sola vez.
     *
     * @return list<string>
     */
    private static function uniqueLabels(mixed $covered, mixed $notCovered): array
    {
        $labels = [];

        foreach ([...self::stringList($covered), ...self::stringList($notCovered)] as $label) {
            $labels[mb_strtoupper($label)] ??= $label;
        }

        return array_values($labels);
    }

    /**
     * @return list<array{medicines: string, indications: string}>
     */
    private static function medicationsForReport(mixed $medications): array
    {
        if (! is_array($medications)) {
            return [];
        }

        $rows = [];

        foreach ($medications as $item) {
            $name = is_array($item)
                ? trim((string) ($item['medicines'] ?? ''))
                : (is_scalar($item) ? trim((string) $item) : '');

            $indications = is_array($item) ? trim((string) ($item['indications'] ?? '')) : '';

            if ($name === '' && $indications === '') {
                continue;
            }

            $rows[] = [
                'medicines' => $name,
                'indications' => $indications,
            ];
        }

        return $rows;
    }
}
