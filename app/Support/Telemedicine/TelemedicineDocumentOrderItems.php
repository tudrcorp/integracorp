<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

final class TelemedicineDocumentOrderItems
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<array{label: string, coverage: string}>
     */
    public static function forDocument(string $docType, array $data): array
    {
        return match ($docType) {
            'imagenologia' => self::items(
                $data,
                ['studies', 'studiesArr'],
                ['other_studies', 'otherStudiesArr'],
                static fn (string $name): bool => TelemedicineCoverageCatalog::studyIsCovered($name),
            ),
            'especialista' => self::items(
                $data,
                ['consultSpecialistArr', 'consult_specialist'],
                ['other_specialist', 'otherSpecialistArr'],
                static fn (string $name): bool => TelemedicineCoverageCatalog::specialistIsCovered($name),
            ),
            default => self::items(
                $data,
                ['labs', 'labsArr'],
                ['other_labs', 'otherLabsArr'],
                static fn (string $name): bool => TelemedicineCoverageCatalog::laboratoryIsCovered($name),
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $coveredKeys
     * @param  list<string>  $otherKeys
     * @param  callable(string): bool  $catalogIsCovered
     * @return list<array{label: string, coverage: string}>
     */
    private static function items(array $data, array $coveredKeys, array $otherKeys, callable $catalogIsCovered): array
    {
        $covered = self::labelsFromKeys($data, $coveredKeys);
        $other = self::labelsFromKeys($data, $otherKeys);
        $hasExplicitOther = false;

        foreach ($otherKeys as $key) {
            if (array_key_exists($key, $data)) {
                $hasExplicitOther = true;
                break;
            }
        }

        $rows = [];

        if ($hasExplicitOther) {
            foreach ($covered as $label) {
                $rows[] = [
                    'label' => $label,
                    'coverage' => 'Cubierto',
                ];
            }

            foreach ($other as $label) {
                $rows[] = [
                    'label' => $label,
                    'coverage' => 'No cubierto',
                ];
            }

            return $rows;
        }

        foreach ($covered as $label) {
            $rows[] = [
                'label' => $label,
                'coverage' => $catalogIsCovered($label) ? 'Cubierto' : 'No cubierto',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return list<string>
     */
    private static function labelsFromKeys(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data) || ! is_array($data[$key])) {
                continue;
            }

            $labels = [];

            foreach ($data[$key] as $item) {
                $label = self::labelFromItem($item);
                if ($label !== '') {
                    $labels[] = $label;
                }
            }

            return $labels;
        }

        return [];
    }

    private static function labelFromItem(mixed $item): string
    {
        if (is_array($item)) {
            return trim((string) ($item['name'] ?? $item['specialty'] ?? $item['study'] ?? $item['laboratory'] ?? $item['label'] ?? ''));
        }

        return trim((string) $item);
    }
}
