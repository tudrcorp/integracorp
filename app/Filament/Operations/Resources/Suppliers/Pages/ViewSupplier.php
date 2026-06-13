<?php

namespace App\Filament\Operations\Resources\Suppliers\Pages;

use App\Filament\Operations\Concerns\AppliesOperationsAddressFromMaps;
use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use App\Models\Supplier;
use App\Support\Filament\Operations\OperationsSuperAdmin;
use App\Support\Filament\Operations\SupplierIntegracorpManagement;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class ViewSupplier extends ViewRecord
{
    use AppliesOperationsAddressFromMaps;

    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Ficha Técnica del Proveedor';

    public bool $gestionIntegracorp = false;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var Supplier $supplier */
        $supplier = $this->getRecord();
        $this->gestionIntegracorp = (bool) $supplier->gestion_integracorp;
    }

    public function updatedGestionIntegracorp(bool $value): void
    {
        if (! OperationsSuperAdmin::check()) {
            /** @var Supplier $supplier */
            $supplier = $this->getRecord();
            $this->gestionIntegracorp = (bool) $supplier->gestion_integracorp;

            Notification::make()
                ->title('Acción no permitida')
                ->body('Solo un analista con rol SUPERADMIN puede modificar la gestión Integracorp del proveedor.')
                ->danger()
                ->send();

            return;
        }

        /** @var Supplier $supplier */
        $supplier = $this->getRecord();
        $previous = (bool) $supplier->gestion_integracorp;
        $supplier->gestion_integracorp = $value;
        $supplier->save();

        if (! $value) {
            SupplierIntegracorpManagement::deactivateIntegracorpUsers($supplier);
        }

        SecurityAudit::log('AUDIT_OPERATIONS_SUPPLIER_GESTION_INTEGRACORP_UPDATED', 'operations.suppliers.gestion-integracorp.update', [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'previous' => $previous,
            'current' => $value,
        ]);

        Notification::make()
            ->title($value ? 'Gestión Integracorp habilitada' : 'Gestión Integracorp deshabilitada')
            ->body($value
                ? 'El proveedor quedó habilitado para telemedicina, gestión de servicios médicos y órdenes de servicio.'
                : 'El proveedor ya no tiene habilitada la gestión de procesos en Integracorp.')
            ->success()
            ->send();
    }

    public function getFooter(): ?ViewContract
    {
        return view('filament.operations.suppliers.supplier-location-maps-loader');
    }

    protected function resolveRecord(int|string $key): Model
    {
        /** @var Supplier $record */
        $record = parent::resolveRecord($key);

        $record->load([
            'finalizedOperationServiceOrders.telemedicinePriority',
            'finalizedOperationServiceOrders.operationCoordinationService',
        ]);

        return $record;
    }

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

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

    public function deleteCartaAcceptance(): void
    {
        /** @var Supplier $supplier */
        $supplier = $this->getRecord();

        $path = $supplier->carta_acceptance;
        $pathForAudit = is_string($path) ? trim($path) : null;

        if (is_string($path) && filled(trim($path))) {
            $trimmed = trim($path);
            if (Storage::disk('public')->exists($trimmed)) {
                Storage::disk('public')->delete($trimmed);
            }
        }

        $supplier->carta_acceptance = null;
        $supplier->save();

        SecurityAudit::log('AUDIT_OPERATIONS_SUPPLIER_DOCUMENT_DELETED', 'operations.suppliers.carta-acceptance.delete', [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'document_type' => 'CARTA_ACEPTACION',
            'path' => $pathForAudit,
        ]);

        Notification::make()
            ->title('Carta de aceptación eliminada')
            ->body('Seleccione el nuevo archivo en el formulario que se muestra a continuación.')
            ->success()
            ->send();

        $this->replaceMountedAction('add_carta_acceptance');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(SupplierResource::getUrl('index'))
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_GRAY_CLASS,
                ]),
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::PRIMARY_BUTTON_CLASS,
                ]),
            Action::make('print_pdf')
                ->label('Ficha Técnica del Proveedor')
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
                ->modalHeading('Cargar carta de aceptación')
                ->modalDescription('Seleccione el archivo. Se guardará al confirmar.')
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

                    SecurityAudit::log('AUDIT_OPERATIONS_SUPPLIER_DOCUMENT_UPLOADED', 'operations.suppliers.carta-acceptance.upload', [
                        'supplier_id' => $record->id,
                        'supplier_name' => $record->name,
                        'document_type' => 'CARTA_ACEPTACION',
                        'path' => $record->carta_acceptance,
                    ]);

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
                        'url' => route('operations.suppliers.carta-acceptance.preview', ['supplier' => $record]),
                        'downloadUrl' => route('operations.suppliers.carta-acceptance.download', ['supplier' => $record]),
                        'extension' => $extension,
                        'supplier' => $record,
                    ]);
                })
                ->extraModalFooterActions([
                    Action::make('delete_carta_acceptance')
                        ->label('Eliminar carta')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('¿Eliminar la carta de aceptación?')
                        ->modalDescription('Se quitará la referencia y, si el archivo existe en el servidor, se borrará. A continuación se abrirá el formulario para cargar la nueva carta.')
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->action(fn () => $this->deleteCartaAcceptance()),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->action(fn () => null)
                ->hidden(fn (Supplier $record) => $record->carta_acceptance == null)
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),

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
                        ->map(function (mixed $path, int $index) use ($record): array {
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
                                'download_url' => $exists
                                    ? route('operations.suppliers.documents.download', ['supplier' => $record, 'index' => $index])
                                    : null,
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
                                ->maxSize(2048),
                        ])
                        ->action(function (array $data): void {
                            /** @var Supplier $record */
                            $record = $this->getRecord();

                            $existing = $this->normalizeAffiliationDocumentPaths($record->documents ?? []);
                            $incoming = $this->normalizeAffiliationDocumentPaths($data['new_documents'] ?? []);
                            $record->documents = array_values(array_merge($existing, $incoming));
                            $record->save();

                            SecurityAudit::log('AUDIT_OPERATIONS_SUPPLIER_DOCUMENT_UPLOADED', 'operations.suppliers.documents.upload', [
                                'supplier_id' => $record->id,
                                'supplier_name' => $record->name,
                                'document_type' => 'AFILIACION',
                                'uploaded_count' => count($incoming),
                                'uploaded_paths' => $incoming,
                                'total_documents' => count($record->documents ?? []),
                            ]);

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
