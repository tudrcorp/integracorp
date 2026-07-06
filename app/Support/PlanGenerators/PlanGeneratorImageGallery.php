<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\PlanGeneratorImage;
use Illuminate\Support\Str;

final class PlanGeneratorImageGallery
{
    public static function registerFromUpload(mixed $uploadState, ?string $createdBy = null): ?PlanGeneratorImage
    {
        $imagePath = PlanGeneratorQuotationState::extractImagePath($uploadState);

        if ($imagePath === null) {
            return null;
        }

        $existing = PlanGeneratorImage::query()
            ->where('image_path', $imagePath)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return PlanGeneratorImage::query()->create([
            'name' => self::nameFromPath($imagePath),
            'image_path' => $imagePath,
            'created_by' => $createdBy,
        ]);
    }

    public static function nameFromPath(string $imagePath): string
    {
        $basename = basename($imagePath);
        $name = pathinfo($basename, PATHINFO_FILENAME);

        return Str::of($name)
            ->replace(['-', '_'], ' ')
            ->title()
            ->toString();
    }
}
