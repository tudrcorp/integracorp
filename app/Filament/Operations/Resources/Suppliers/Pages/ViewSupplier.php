<?php

namespace App\Filament\Operations\Resources\Suppliers\Pages;

use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Collection;
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

    /**
     * @return list<string>
     */
    private function normalizeAffiliationDocumentPaths(mixed $paths): array
    {
        if ($paths === null || $paths === '') {
            return [];
        }

        if (! is_array($paths)) {
            $paths = [$paths];
        }

        return Collection::make($paths)
            ->flatten()
            ->filter(static fn (mixed $p): bool => is_string($p) && filled(trim($p)))
            ->map(static fn (string $p): string => trim($p))
            ->values()
            ->all();
    }

    public function deleteSupplierAffiliationDocument(int $index): void
    {
        /** @var Supplier $supplier */
        $supplier = $this->getRecord();

        $docs = Collection::make($supplier->documents ?? [])->values();
        if (! $docs->has($index)) {
            return;
        }

        $path = $docs->get($index);
        if (is_string($path) && filled(trim($path))) {
            $trimmed = trim($path);
            if (Storage::disk('public')->exists($trimmed)) {
                Storage::disk('public')->delete($trimmed);
            }
        }

        $docs->forget($index);
        $supplier->documents = $docs->values()->all();
        $supplier->save();

        Notification::make()
            ->title('Documento eliminado')
            ->success()
            ->send();

        if ($this->getMountedAction()?->getName() === 'view_documents') {
            $this->replaceMountedAction('view_documents');
        }
    }

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
                ->modalHeading('Ficha del proveedor en PDF')
                ->modalDescription('Vista previa de la ficha técnica. La primera generación puede tardar; las siguientes suelen ser más rápidas mientras los datos no cambien (caché por proveedor).')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalIcon('heroicon-o-document-text')
                ->modalContent(function (Supplier $record): ViewContract {
                    return View::make('filament.operations.suppliers.supplier-ficha-preview-modal', [
                        'pdfPreviewUrl' => route('operations.suppliers.ficha.preview', $record),
                        'pdfDownloadUrl' => route('operations.suppliers.ficha.download', $record),
                        'supplierLabel' => filled($record->name) ? $record->name : ('Proveedor #'.$record->id),
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->action(fn () => null)
                ->extraAttributes([
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

            // Carga de documentos de afiliación del proveedor
            // Action::make('add_documents')
            //     ->label('Agregar Documentos de Afiliación')
            //     ->icon('heroicon-s-document-text')
            //     ->color('warning')
            //     ->tooltip('Aqui puede cargar uno o varios documentos del proveedor, por ejemplo RIF, Registro Mercantil, Baremos u otros soportes necesarios.')
            //     ->form([
            //         FileUpload::make('documents')
            //             ->directory('suppliers/documents')
            //             ->label('Documentos de Afiliación')
            //             ->multiple()
            //             ->required()
            //             ->maxFiles(10)
            //             ->maxSize(1024),
            //     ])
            //     ->action(function (Supplier $record, array $data) {
            //         $existing = $this->normalizeAffiliationDocumentPaths($record->documents ?? []);
            //         $incoming = $this->normalizeAffiliationDocumentPaths($data['documents'] ?? []);
            //         $record->documents = array_values(array_merge($existing, $incoming));
            //         $record->save();
            //         Notification::make()
            //             ->title('Documentos de afiliación actualizados')
            //             ->body(count($incoming) > 1
            //                 ? 'Se agregaron '.count($incoming).' documentos.'
            //                 : 'Se agregó 1 documento.')
            //             ->icon('heroicon-s-check-circle')
            //             ->iconColor('success')
            //             ->success()
            //             ->send();
            //     })
            //     ->extraAttributes([
            //         'class' => self::WARNING_BUTTON_CLASS,
            //         'data-tippy-placement' => 'left',
            //     ])
            //     ->hidden(fn (Supplier $record) => $record->documents != null),

            // Vista previa y gestión de documentos de afiliación del proveedor
            Action::make('view_documents')
                ->label('Documentos de Afiliación')
                ->icon('heroicon-s-document-text')
                ->color('success')
                ->modalHeading('Documentos de afiliación')
                ->modalDescription('Vista previa, agregar nuevos archivos o quitar documentos existentes.')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalIcon('heroicon-o-document-magnifying-glass')
                ->modalContent(function (Supplier $record): ViewContract {
                    $documents = collect($record->documents ?? [])
                        ->map(function (mixed $path, int $index): array {
                            $path = is_string($path) ? trim($path) : '';
                            $exists = filled($path) && Storage::disk('public')->exists($path);
                            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

                            return [
                                'id' => $index + 1,
                                'index' => $index,
                                'path' => $path,
                                'exists' => $exists,
                                'extension' => $extension,
                                'name' => $path !== '' ? basename($path) : 'Documento sin nombre',
                                'url' => $exists ? asset('storage/'.Str::ltrim($path, '/')) : null,
                            ];
                        })
                        ->values()
                        ->all();

                    return View::make('filament.operations.suppliers.documents-preview', [
                        'supplier' => $record,
                        'documents' => $documents,
                    ]);
                })
                ->extraModalFooterActions([
                    Action::make('appendAffiliationDocuments')
                        ->label('Agregar documentos')
                        ->icon('heroicon-o-plus-circle')
                        ->color('warning')
                        ->form([
                            FileUpload::make('new_documents')
                                ->directory('suppliers/documents')
                                ->label('Archivos a agregar')
                                ->helperText('Puede seleccionar uno o varios archivos. Se añaden a los ya cargados.')
                                ->multiple()
                                ->required()
                                ->maxFiles(10)
                                ->maxSize(1024),
                        ])
                        ->action(function (array $data): void {
                            /** @var Supplier $record */
                            $record = $this->getRecord();

                            $existing = $this->normalizeAffiliationDocumentPaths($record->documents ?? []);
                            $incoming = $this->normalizeAffiliationDocumentPaths($data['new_documents'] ?? []);
                            $record->documents = array_values(array_merge($existing, $incoming));
                            $record->save();

                            Notification::make()
                                ->title('Documentos agregados')
                                ->body(count($incoming) > 1
                                    ? 'Se añadieron '.count($incoming).' archivos.'
                                    : 'Se añadió 1 archivo.')
                                ->success()
                                ->send();

                            $this->replaceMountedAction('view_documents');
                        }),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->action(fn () => null)
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
