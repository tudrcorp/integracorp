<?php

namespace App\Filament\Administration\Resources\RrhhNominas\Pages;

use App\Filament\Administration\Resources\RrhhNominas\RrhhNominaResource;
use App\Models\RrhhNomina;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ViewRrhhNomina extends ViewRecord
{
    protected static string $resource = RrhhNominaResource::class;

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_DANGER_BUTTON_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public function getTitle(): string|Htmlable
    {
        /** @var RrhhNomina $record */
        $record = $this->getRecord();

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:6px;padding:10px 0;">'
            .'<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">'
            .'Detalle de nómina'
            .'</span>'
            .'<span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">'
            .e($record->periodoLabel())
            .'</span>'
            .'<span class="text-sm text-gray-600 dark:text-gray-300">'
            .'Tasa BCV '.e(number_format((float) $record->tasa_bcv, 4, '.', ',')).' VES/USD'
            .' · Neto USD$ '.e(number_format((float) $record->total_neto, 2, '.', ','))
            .' / VES '.e(number_format((float) $record->total_neto_ves, 2, '.', ','))
            .'</span>'
            .'</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al listado')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(RrhhNominaResource::getUrl('index'))
                ->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ], merge: true),
            DeleteAction::make()
                ->label('Eliminar cálculo')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->extraAttributes([
                    'class' => self::IOS_DANGER_BUTTON_CLASS,
                ], merge: true),
        ];
    }
}
