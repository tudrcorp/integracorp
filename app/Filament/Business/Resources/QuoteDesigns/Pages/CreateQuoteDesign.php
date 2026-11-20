<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\QuoteDesigns\Pages;

use App\Filament\Business\Resources\QuoteDesigns\Concerns\InteractsWithQuoteDesignActions;
use App\Filament\Business\Resources\QuoteDesigns\QuoteDesignResource;
use App\Support\Quotes\Design\QuoteDesignPersistence;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateQuoteDesign extends CreateRecord
{
    use InteractsWithQuoteDesignActions;

    protected static string $resource = QuoteDesignResource::class;

    public function getSubheading(): ?string
    {
        return 'Cargue lo que envió diseño: importe el HTML para tomar colores y textos, suba el logo y las imágenes de las páginas fijas, revise la vista previa y active.';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->importHtmlAction(),
            $this->previewAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return QuoteDesignPersistence::save(null, $data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Diseño de cotización creado';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
