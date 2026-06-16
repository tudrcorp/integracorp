<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\PlanGeneratorQuotationPage;

final class PlanGeneratorQuotationState
{
    /**
     * @param  array<int|string, array<string, mixed>>  $pages
     * @return array<int, array<string, mixed>>
     */
    public static function syncPagesToCount(array $pages, int $count): array
    {
        if ($count < 1) {
            return [];
        }

        $existingByNumber = [];
        $orderedWithoutNumber = [];

        foreach (array_values($pages) as $index => $page) {
            if (! is_array($page)) {
                continue;
            }

            $pageNumber = (int) ($page['page_number'] ?? 0);

            if ($pageNumber >= 1) {
                $existingByNumber[$pageNumber] = $page;

                continue;
            }

            $orderedWithoutNumber[$index + 1] = $page;
        }

        $synced = [];

        for ($pageNumber = 1; $pageNumber <= $count; $pageNumber++) {
            $existing = (array) ($existingByNumber[$pageNumber] ?? $orderedWithoutNumber[$pageNumber] ?? []);

            $synced[] = [
                'page_number' => $pageNumber,
                'image' => $existing['image'] ?? $existing['image_path'] ?? null,
            ];
        }

        return $synced;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $pages
     * @return array<int, array<string, mixed>>
     */
    public static function syncImagePagesForQuotation(array $pages, int $count, ?int $planPageNumber): array
    {
        if ($count < 1) {
            return [];
        }

        $existingByNumber = [];

        foreach (array_values($pages) as $index => $page) {
            if (! is_array($page)) {
                continue;
            }

            $pageNumber = (int) ($page['page_number'] ?? 0);

            if ($pageNumber >= 1) {
                $existingByNumber[$pageNumber] = $page;

                continue;
            }

            $fallbackPageNumber = $index + 1;

            if ($fallbackPageNumber <= $count && ! self::isPlanPage($fallbackPageNumber, $planPageNumber)) {
                $existingByNumber[$fallbackPageNumber] = $page;
            }
        }

        $synced = [];

        for ($pageNumber = 1; $pageNumber <= $count; $pageNumber++) {
            if (self::isPlanPage($pageNumber, $planPageNumber)) {
                continue;
            }

            $existing = (array) ($existingByNumber[$pageNumber] ?? []);

            $synced[] = [
                'page_number' => $pageNumber,
                'image' => $existing['image'] ?? $existing['image_path'] ?? null,
            ];
        }

        return $synced;
    }

    public static function expectedImagePageCount(int $pageCount, ?int $planPageNumber): int
    {
        if ($pageCount < 1 || $planPageNumber === null || $planPageNumber < 1 || $planPageNumber > $pageCount) {
            return max(0, $pageCount);
        }

        return $pageCount - 1;
    }

    public static function isPlanPage(int $pageNumber, ?int $planPageNumber): bool
    {
        return $planPageNumber !== null
            && $planPageNumber > 0
            && $pageNumber === $planPageNumber;
    }

    public static function hasUploadedImage(mixed $image): bool
    {
        return self::extractImagePath($image) !== null;
    }

    public static function extractImagePath(mixed $image): ?string
    {
        if ($image === null || $image === '') {
            return null;
        }

        if (is_string($image)) {
            return filled($image) ? $image : null;
        }

        if (! is_array($image)) {
            return null;
        }

        foreach ($image as $key => $value) {
            if (is_string($value) && filled($value)) {
                return $value;
            }

            if (is_string($key) && filled($key) && ! is_numeric($key)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $pages
     * @return array<int, array<string, mixed>>
     */
    public static function normalizePages(array $pages): array
    {
        $normalized = [];

        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            $pageNumber = (int) ($page['page_number'] ?? 0);

            if ($pageNumber < 1) {
                continue;
            }

            $imagePath = self::extractImagePath($page['image'] ?? $page['image_path'] ?? null);

            $normalized[] = [
                'page_number' => $pageNumber,
                'image' => $imagePath,
            ];
        }

        usort(
            $normalized,
            fn (array $left, array $right): int => $left['page_number'] <=> $right['page_number'],
        );

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<int, PlanGeneratorQuotationPage>
     */
    public static function orderedPagesFromForm(array $pages): array
    {
        return self::normalizePages($pages);
    }

    /**
     * @param  list<PlanGeneratorQuotationPage>  $pages
     * @return array<int, array<string, mixed>>
     */
    public static function formPagesFromModels(iterable $pages): array
    {
        $formPages = [];

        foreach ($pages as $page) {
            $formPages[] = [
                'page_number' => (int) $page->page_number,
                'image' => $page->image_path,
            ];
        }

        usort(
            $formPages,
            fn (array $left, array $right): int => $left['page_number'] <=> $right['page_number'],
        );

        return $formPages;
    }
}
