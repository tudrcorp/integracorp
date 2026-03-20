<?php

namespace App\Filament\Operations\Resources\Suppliers\Pages;

use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Ficha Técnica del Proveedor';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::PRIMARY_BUTTON_CLASS,
                ]),
            Action::make('print_pdf')
                ->label('Imprimir PDF')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->action(function (Supplier $record) {

                    try {

                        // Obtenemos el HTML renderizado primero para debug o procesamiento
                        $html = View::make('documents.supplier-ficha', [
                            'supplier' => $record->load([
                                'SupplierClasificacion',
                                'state',
                                'city',
                                'supplierContactPrincipals',
                                'supplierRedGlobals.state',
                                'supplierRedGlobals.city',
                                'SupplierZonaCoberturas.supplierClasificacion',
                                'SupplierZonaCoberturas.state',
                                'SupplierZonaCoberturas.city',
                                'supplierObservacions',
                            ]),
                            'isPreview' => false,
                        ])->render();

                        $pdf = Pdf::loadHTML($html)
                            ->setPaper('a4', 'portrait')
                            ->setWarnings(false)
                            ->setOptions([
                                'isHtml5ParserEnabled' => true,
                                'isRemoteEnabled' => true,
                                'defaultFont' => 'sans-serif',
                            ]);

                        // Retornamos el stream download para que Filament no rompa la codificación UTF-8
                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            'Ficha-Proveedor-'.$record->id.'.pdf'
                        );

                        // $pdf->setPaper('A4', 'portrait');

                        // return $pdf->streamDownload('ficha-proveedor-'.$record->id.'.pdf');

                    } catch (\Throwable $th) {
                        dd($th);
                        Notification::make()
                            ->title('ERROR')
                            ->body($th->getMessage())
                            ->icon('heroicon-s-x-circle')
                            ->iconColor('danger')
                            ->danger()
                            ->send();
                    }
                })->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),

            Action::make('add_carta_acceptance')
                ->label('Agregar Carta de Aceptación')
                ->icon('heroicon-s-document-text')
                ->color('warning')
                ->form([
                    FileUpload::make('carta_acceptance')
                        ->directory('suppliers/carta-acceptance')
                        ->label('Carta de Aceptación')
                        ->required()
                        ->maxFiles(1)
                        ->maxSize(1024),
                ])
                ->action(function (Supplier $record, array $data) {
                    $record->carta_acceptance = $data['carta_acceptance'];
                    $record->save();
                    Notification::make()
                        ->title('Carta de Aceptación agregada correctamente')
                        ->icon('heroicon-s-check-circle')
                        ->iconColor('success')
                        ->success()
                        ->send();
                })
                ->hidden(fn (Supplier $record) => $record->carta_acceptance != null)
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ]),

            Action::make('view_carta_acceptance')
                ->label('Ver Carta de Aceptación')
                ->icon('heroicon-s-document-text')
                ->color('success')
                ->modalHeading('Carta de aceptación')
                ->modalDescription('Vista previa del documento cargado. Use «Abrir en pestaña» o «Descargar» desde el pie del visor si lo necesita.')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalIcon('heroicon-o-document-magnifying-glass')
                ->modalContent(function (Supplier $record): ViewContract {
                    $path = $record->carta_acceptance;

                    if (! $path || ! Storage::disk('public')->exists($path)) {
                        return View::make('filament.operations.suppliers.carta-acceptance-preview', [
                            'exists' => false,
                            'supplier' => $record,
                        ]);
                    }

                    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                    return View::make('filament.operations.suppliers.carta-acceptance-preview', [
                        'exists' => true,
                        'url' => asset('storage/'.Str::ltrim($path, '/')),
                        'extension' => $extension,
                        'supplier' => $record,
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->action(fn () => null)
                ->hidden(fn (Supplier $record) => $record->carta_acceptance == null)
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }

    public function getRelationManagers(): array
    {
        return [

        ];
    }
}
