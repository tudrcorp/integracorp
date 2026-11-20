<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\QuoteDesigns\Pages;

use App\Filament\Business\Resources\QuoteDesigns\Concerns\InteractsWithQuoteDesignActions;
use App\Filament\Business\Resources\QuoteDesigns\QuoteDesignResource;
use App\Models\QuoteDesign;
use App\Support\Quotes\Design\QuoteDesignPersistence;
use App\Support\Quotes\Design\QuoteDesignTheme;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditQuoteDesign extends EditRecord
{
    use InteractsWithQuoteDesignActions;

    protected static string $resource = QuoteDesignResource::class;

    public function getSubheading(): ?string
    {
        /** @var QuoteDesign $record */
        $record = $this->getRecord();

        return 'Versión '.max(1, (int) $record->version).'. Guardar un cambio crea una versión nueva para las cotizaciones que se emitan desde ahora; las propuestas ya emitidas conservan la suya.';
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
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var QuoteDesign $record */
        $record = $this->getRecord();

        $data['theme'] = QuoteDesignTheme::sanitizeThemeData((array) ($record->theme ?? []));
        $data['plans'] = $record->plans()->pluck('plans.id')->map(fn ($id): int => (int) $id)->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var QuoteDesign $record */
        return QuoteDesignPersistence::save($record, $data);
    }

    /**
     * Las imágenes recién subidas se guardaron en su versión A4 y la original se descartó: el
     * formulario se recarga para que un segundo guardado no apunte a archivos que ya no existen.
     */
    protected function afterSave(): void
    {
        $this->fillForm();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Diseño de cotización guardado';
    }
}
