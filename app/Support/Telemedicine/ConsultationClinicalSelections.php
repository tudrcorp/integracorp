<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Enums\ClinicalServiceChannel;

/**
 * Lo que el médico recetó y solicitó en la consulta que se está guardando.
 *
 * Viajaba en claves de sesión globales (`medications`, `labs`, `studies`…) que
 * solo se limpiaban al completar el guardado con éxito: un flujo interrumpido
 * dejaba restos que la siguiente consulta —o la de otra pestaña— recogía como
 * propios. El traspaso real ocurre dentro de una sola petición
 * (`mutateFormDataBeforeCreate` → `afterCreate`), así que no necesita sesión.
 */
final class ConsultationClinicalSelections
{
    public const COVERED = 'CUBIERTO';

    public const NOT_COVERED = 'NO CUBIERTO';

    /**
     * @param  array<int, mixed>  $medications
     * @param  array<int, mixed>  $labs
     * @param  array<int, mixed>  $otherLabs
     * @param  array<int, mixed>  $studies
     * @param  array<int, mixed>  $otherStudies
     * @param  array<int, mixed>  $consultSpecialist
     * @param  array<int, mixed>  $otherSpecialist
     */
    private function __construct(
        public readonly array $medications = [],
        public readonly array $labs = [],
        public readonly array $otherLabs = [],
        public readonly array $studies = [],
        public readonly array $otherStudies = [],
        public readonly array $consultSpecialist = [],
        public readonly array $otherSpecialist = [],
        public readonly bool $discharge = false,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * Descarta las listas de servicios cubiertos cuyo canal no está en el uso
     * clínico del plan del paciente.
     *
     * El formulario ya oculta esos campos, pero la regla no puede vivir solo
     * en la pantalla: un estado arrastrado de una consulta anterior o una
     * petición manipulada dejaría al PDF y a la coordinación de servicio
     * diciendo algo distinto de lo que vio el médico. Lo no cubierto no se
     * toca: siempre puede indicarse.
     *
     * @param  callable(ClinicalServiceChannel): bool  $channelIsContemplated
     */
    public function withoutUncontemplatedCovered(callable $channelIsContemplated): self
    {
        return new self(
            medications: $this->medications,
            labs: $channelIsContemplated(ClinicalServiceChannel::Laboratory) ? $this->labs : [],
            otherLabs: $this->otherLabs,
            studies: $channelIsContemplated(ClinicalServiceChannel::Imaging) ? $this->studies : [],
            otherStudies: $this->otherStudies,
            consultSpecialist: $channelIsContemplated(ClinicalServiceChannel::Specialist) ? $this->consultSpecialist : [],
            otherSpecialist: $this->otherSpecialist,
            discharge: $this->discharge,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromFormData(array $data): self
    {
        return new self(
            medications: self::rows($data, 'medications'),
            labs: self::rows($data, 'labs'),
            otherLabs: self::rows($data, 'other_labs'),
            studies: self::rows($data, 'studies'),
            otherStudies: self::rows($data, 'other_studies'),
            consultSpecialist: self::rows($data, 'consult_specialist'),
            otherSpecialist: self::rows($data, 'other_specialist'),
            discharge: (bool) ($data['feedbackOne'] ?? false),
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function mergedLabs(): array
    {
        return array_merge($this->labs, $this->otherLabs);
    }

    /**
     * @return array<int, mixed>
     */
    public function mergedStudies(): array
    {
        return array_merge($this->studies, $this->otherStudies);
    }

    /**
     * @return array<int, mixed>
     */
    public function mergedSpecialists(): array
    {
        return array_merge($this->consultSpecialist, $this->otherSpecialist);
    }

    /**
     * Laboratorios a registrar, con la cobertura que indica el campo donde el
     * médico los eligió.
     *
     * @return list<array{name: string, type: string}>
     */
    public function typedLabs(): array
    {
        return self::typed($this->labs, $this->otherLabs);
    }

    /**
     * @return list<array{name: string, type: string}>
     */
    public function typedStudies(): array
    {
        return self::typed($this->studies, $this->otherStudies);
    }

    /**
     * @return list<array{name: string, type: string}>
     */
    public function typedSpecialists(): array
    {
        return self::typed($this->consultSpecialist, $this->otherSpecialist);
    }

    /**
     * La cobertura sale del campo, no del catálogo: un mismo nombre puede estar
     * como cubierto y como no cubierto (especialistas con `type`/`type_two`,
     * CREATININA repetida), y buscarlo por nombre devolvía siempre el primero.
     * Mismo orden que el merge histórico: primero cubiertos, luego no cubiertos.
     *
     * @param  array<int, mixed>  $covered
     * @param  array<int, mixed>  $notCovered
     * @return list<array{name: string, type: string}>
     */
    private static function typed(array $covered, array $notCovered): array
    {
        $rows = [];

        foreach ([self::COVERED => $covered, self::NOT_COVERED => $notCovered] as $type => $names) {
            foreach ($names as $name) {
                if (! is_scalar($name) || trim((string) $name) === '') {
                    continue;
                }

                $rows[] = ['name' => (string) $name, 'type' => $type];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, mixed>
     */
    private static function rows(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? array_values($value) : [];
    }
}
