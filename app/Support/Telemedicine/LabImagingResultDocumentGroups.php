<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use Illuminate\Support\Carbon;

/**
 * Agrupa los resultados que carga Operaciones en laboratorio, imagenología y
 * otros, conservando el orden de llegada (más reciente primero).
 */
final class LabImagingResultDocumentGroups
{
    public const LAB = 'Laboratorio';

    public const IMAGING = 'Imagenología';

    public const OTHER = 'Otros documentos';

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return list<array{title: string, documents: list<array<string, mixed>>}>
     */
    public static function group(array $documents): array
    {
        $groups = [self::LAB => [], self::IMAGING => [], self::OTHER => []];

        foreach ($documents as $document) {
            $document['uploaded_label'] = self::uploadedLabel($document['uploaded_at'] ?? null);
            $groups[self::category($document)][] = $document;
        }

        $out = [];

        foreach ($groups as $title => $items) {
            if ($items !== []) {
                $out[] = ['title' => $title, 'documents' => $items];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public static function category(array $document): string
    {
        $haystack = mb_strtoupper(implode(' ', [
            ...array_map('strval', is_array($document['document_types'] ?? null) ? $document['document_types'] : []),
            ...array_map('strval', is_array($document['services'] ?? null) ? $document['services'] : []),
            (string) ($document['document_name'] ?? ''),
        ]));

        return match (true) {
            str_contains($haystack, 'IMAGEN') || str_contains($haystack, 'ESTUDIO') => self::IMAGING,
            str_contains($haystack, 'LABORATORIO') => self::LAB,
            default => self::OTHER,
        };
    }

    public static function uploadedLabel(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return null;
        }

        try {
            return Carbon::parse($text)->format('d/m/Y h:i A');
        } catch (\Throwable) {
            return null;
        }
    }
}
