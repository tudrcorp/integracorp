<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use Illuminate\Validation\ValidationException;

final class PlanGeneratorQuotationValidator
{
    public static function validationMessage(?int $pageCount, ?int $planPageNumber, array $pages): ?string
    {
        if ($pageCount === null || $pageCount < 1) {
            return null;
        }

        if ($planPageNumber === null || $planPageNumber < 1 || $planPageNumber > $pageCount) {
            return "Indique en qué página (entre 1 y {$pageCount}) debe mostrarse el plan generado.";
        }

        $normalizedPages = PlanGeneratorQuotationState::normalizePages($pages);
        $expectedImagePages = PlanGeneratorQuotationState::expectedImagePageCount($pageCount, $planPageNumber);

        if (count($normalizedPages) !== $expectedImagePages) {
            return "Debe cargar imágenes para las páginas 1 a {$pageCount}, excepto la página {$planPageNumber} reservada para el plan.";
        }

        foreach ($normalizedPages as $page) {
            $pageNumber = (int) ($page['page_number'] ?? 0);

            if (PlanGeneratorQuotationState::isPlanPage($pageNumber, $planPageNumber)) {
                return "La página {$pageNumber} está reservada para el plan generado y no debe incluir imagen.";
            }

            if (! PlanGeneratorQuotationState::hasUploadedImage($page['image'] ?? null)) {
                return "La página {$pageNumber} debe incluir una imagen.";
            }
        }

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            if (PlanGeneratorQuotationState::isPlanPage($pageNumber, $planPageNumber)) {
                continue;
            }

            $hasPage = collect($normalizedPages)->contains(
                fn (array $page): bool => (int) ($page['page_number'] ?? 0) === $pageNumber,
            );

            if (! $hasPage) {
                return "Falta cargar la imagen de la página {$pageNumber}.";
            }
        }

        return null;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $pages
     */
    public static function helperText(?int $pageCount, ?int $planPageNumber, array $pages): string
    {
        if ($pageCount === null || $pageCount < 1) {
            return 'Indique cuántas páginas tendrá la cotización y cargue una imagen en cada página excepto la del plan.';
        }

        $normalizedPages = PlanGeneratorQuotationState::normalizePages($pages);
        $imagesRequired = PlanGeneratorQuotationState::expectedImagePageCount($pageCount, $planPageNumber);
        $imagesLoaded = count(array_filter(
            $normalizedPages,
            fn (array $page): bool => PlanGeneratorQuotationState::hasUploadedImage($page['image'] ?? null),
        ));

        $planLabel = filled($planPageNumber)
            ? "El plan generado irá en la página {$planPageNumber}; solo cargue imágenes para las demás."
            : 'Seleccione la página donde debe aparecer el plan generado.';

        return "{$imagesLoaded} de {$imagesRequired} imágenes cargadas. {$planLabel}";
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $pages
     */
    public static function assertMatchesOrFail(?int $pageCount, ?int $planPageNumber, array $pages): void
    {
        $message = self::validationMessage($pageCount, $planPageNumber, $pages);

        if ($message === null) {
            return;
        }

        throw ValidationException::withMessages([
            'quotation_page_count' => $message,
        ]);
    }
}
