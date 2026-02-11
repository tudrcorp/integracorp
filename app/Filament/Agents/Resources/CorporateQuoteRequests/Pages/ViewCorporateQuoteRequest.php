<?php

namespace App\Filament\Agents\Resources\CorporateQuoteRequests\Pages;

use App\Filament\Agents\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCorporateQuoteRequest extends ViewRecord
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'Detalle de Cotización Dress Taylor';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(CorporateQuoteRequestResource::getUrl()),
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->hidden(fn($record) => $record->status == 'APROBADA'),
            Action::make('view_document')
                ->label('Ver Cotización')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->hidden(fn($record) => $record->document_file == null)
                ->action(function ($record) {
                    /**
                     * Descargar el documento asociado a la cotizacion
                     * ruta: storage/
                     */
                    $path = public_path('storage/' . $record->document_file);
                    return response()->download($path);
                }),
        ];
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        $corporateQuoteRequest = $this->getRecord();

        // Definimos el nombre del afiliado de forma segura
        $fullName = $corporateQuoteRequest->full_name ?? 'Sin Nombre';

        $statusConfig = match ($corporateQuoteRequest->status) {
            'APROBADA'   => ['color' => '#28cd41', 'label' => 'APROBADA'],
            'PROCESADA'  => ['color' => '#ffcc00', 'label' => 'PROCESADA'],
            'PRE-APROBADA' => ['color' => '#ffcc00', 'label' => 'PRE-APROBADA'], // 'pendiente' u otros
        };

        return new \Illuminate\Support\HtmlString(
            '<div style="display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; gap: 2px; padding: 12px 0;">' .
                // Título Principal Resaltado
                '<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-gray-100 mb-1 dark:text-white">' .
                'DETALLE DE LA SOLICITUD' .
                '</span>' .

                // Subtítulo (Nombre del Paciente)
                '<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-100 mb-1 dark:text-white">' .
                'Solicitada por: ' . $fullName .
                '</span>' .

                // Estatus Estilo Badge iOS Resaltado
                '<div style="display: flex; align-items: center; margin-top: 8px;">' .
                '<span style="' .
                'background-color: ' . $statusConfig['color'] . '; ' .
                'color: ' . ($statusConfig['color'] === '#ffcc00' ? '#000000' : '#ffffff') . '; ' .
                'padding: 6px 16px; ' .
                'border-radius: 50px; ' .
                'font-size: 0.8rem; ' .
                'font-weight: 700; ' .
                'display: inline-flex; ' .
                'align-items: center; ' .
                'gap: 6px; ' .
                'box-shadow: 0 4px 12px ' . $statusConfig['color'] . '59; ' .
                'border: 1px solid rgba(255, 255, 255, 0.2);' .
                '">' .
                '<span style="font-size: 10px;">●</span>' . $statusConfig['label'] .
                '</span>' .
                '</div>' .
                '</div>'
        );
    }
}