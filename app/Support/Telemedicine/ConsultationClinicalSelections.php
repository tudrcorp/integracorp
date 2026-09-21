<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

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
     * @param  array<string, mixed>  $data
     * @return array<int, mixed>
     */
    private static function rows(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? array_values($value) : [];
    }
}
