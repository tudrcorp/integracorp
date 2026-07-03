<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Pages\Concerns;

use App\Models\PlanGeneratorImage;
use App\Support\PlanGenerators\PlanGeneratorQuotationState;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

trait InteractsWithPlanGeneratorQuotationGallery
{
    public ?int $quotationGalleryPickerPageNumber = null;

    public function openQuotationGalleryPicker(int $pageNumber): void
    {
        $this->quotationGalleryPickerPageNumber = $pageNumber;
    }

    public function closeQuotationGalleryPicker(): void
    {
        $this->quotationGalleryPickerPageNumber = null;
    }

    public function selectQuotationGalleryImage(int $galleryImageId): void
    {
        $pageNumber = $this->quotationGalleryPickerPageNumber;

        if ($pageNumber === null) {
            return;
        }

        $image = PlanGeneratorImage::query()->find($galleryImageId);

        if ($image === null) {
            return;
        }

        $pages = (array) ($this->data['quotation_pages'] ?? []);

        foreach ($pages as $key => $page) {
            if (! is_array($page)) {
                continue;
            }

            if ((int) ($page['page_number'] ?? 0) === $pageNumber) {
                $this->data['quotation_pages'][$key]['image'] = PlanGeneratorQuotationState::toFileUploadState($image->image_path);
                break;
            }
        }

        $this->closeQuotationGalleryPicker();

        Notification::make()
            ->title('Imagen aplicada')
            ->body("Se asignó «{$image->name}» a la página {$pageNumber}.")
            ->success()
            ->send();
    }

    /**
     * @return Collection<int, PlanGeneratorImage>
     */
    public function getQuotationGalleryImagesProperty(): Collection
    {
        return PlanGeneratorImage::query()
            ->latest()
            ->limit(60)
            ->get();
    }
}
