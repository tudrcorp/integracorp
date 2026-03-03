<?php

namespace App\Filament\Operations\Resources\Suppliers\Pages;

use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\View;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Ficha Técnica del Proveedor';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
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
                }),
        ];
    }

    public function getRelationManagers(): array
    {
        return [

        ];
    }
}
