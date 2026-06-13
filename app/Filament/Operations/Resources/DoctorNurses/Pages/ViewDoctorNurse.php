<?php

namespace App\Filament\Operations\Resources\DoctorNurses\Pages;

use App\Filament\Operations\Resources\DoctorNurses\DoctorNurseResource;
use App\Models\DoctorNurse;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class ViewDoctorNurse extends ViewRecord
{
    protected static string $resource = DoctorNurseResource::class;

    protected static ?string $title = 'Ficha Técnica del Proveedor Natural';

    // estilos de botones
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function normalizeAffiliationDocumentPaths(DoctorNurse $record): array
    {
        $paths = is_array($record->documents) ? array_values($record->documents) : [];

        return array_values(array_filter($paths, fn ($p): bool => is_string($p) && trim($p) !== ''));
    }

    public function deleteDoctorNurseAffiliationDocument(int $index): void
    {
        /** @var DoctorNurse $record */
        $record = $this->record;

        $documents = $this->normalizeAffiliationDocumentPaths($record);

        $path = $documents[$index] ?? null;
        if (! is_string($path) || trim($path) === '') {
            return;
        }

        unset($documents[$index]);
        $record->documents = array_values($documents);
        $record->save();

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        Notification::make()
            ->success()
            ->title('Documento eliminado')
            ->body('El documento de afiliación fue eliminado correctamente.')
            ->send();
    }

    // ... dentro de la clase ViewDoctorNurse

    public function deleteCartaAcceptance(): void
    {
        /** @var DoctorNurse $record */
        $record = $this->record;

        $path = $record->carta_acceptance;
        $record->carta_acceptance = null;
        $record->save();

        if (is_string($path) && trim($path) !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        Notification::make()
            ->success()
            ->title('Carta eliminada')
            ->send();

        // IMPORTANTE: Redirige a la acción de agregar para completar el ciclo visual
        $this->replaceMountedAction('add_carta_acceptance');
    }

    protected function getHeaderActions(): array
    {
        /** @var DoctorNurse $record */
        $record = $this->record;

        // Lógica de datos de la carta
        $cartaPath = is_string($record->carta_acceptance) ? $record->carta_acceptance : null;
        $cartaExists = is_string($cartaPath) && trim($cartaPath) !== '' && Storage::disk('public')->exists($cartaPath);
        $cartaExtension = $cartaPath ? strtolower((string) pathinfo($cartaPath, PATHINFO_EXTENSION)) : '';
        $cartaUrl = $cartaExists ? url('storage/'.Str::ltrim($cartaPath, '/')) : null;
        $cartaDownloadUrl = $cartaPath ? route('operations.doctor-nurses.carta-acceptance.download', ['doctorNurse' => $record]) : null;

        // Lógica de documentos múltiples
        $documents = $this->normalizeAffiliationDocumentPaths($record);
        $documentCards = collect($documents)->values()->map(function (string $path, int $index) use ($record): array {
            $exists = Storage::disk('public')->exists($path);

            return [
                'id' => $index + 1,
                'index' => $index,
                'name' => basename($path),
                'path' => $path,
                'extension' => strtolower((string) pathinfo($path, PATHINFO_EXTENSION)),
                'exists' => $exists,
                'url' => $exists ? url('storage/'.Str::ltrim($path, '/')) : null,
                'download_url' => route('operations.doctor-nurses.documents.download', ['doctorNurse' => $record, 'index' => $index]),
            ];
        })->all();

        return [
            // BOTÓN VOLVER
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(DoctorNurseResource::getUrl('index'))
                ->extraAttributes(['class' => self::TICKET_BUTTON_GRAY_CLASS]),

            // BOTÓN EDITAR
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color('primary')
                ->extraAttributes(['class' => self::PRIMARY_BUTTON_CLASS]),

            // BOTÓN FICHA TÉCNICA (SIEMPRE VERDE)
            Action::make('print_pdf')
                ->label('Ficha Técnica del Proveedor')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalContent(fn (DoctorNurse $record) => View::make('filament.operations.doctor-nurses.doctor-nurse-ficha-preview-modal', [
                    'pdfPreviewUrl' => route('operations.doctor-nurses.ficha.preview', ['doctorNurse' => $record]),
                    'pdfDownloadUrl' => route('operations.doctor-nurses.ficha.download', ['doctorNurse' => $record]),
                    'doctorNurseLabel' => $record->name ?? 'Proveedor natural #'.$record->id,
                ]))
                ->modalSubmitAction(false)
                ->extraAttributes(['class' => self::TICKET_BUTTON_CLASS]),

            // --- LÓGICA DE CAMBIO DE COLOR PARA CARTA DE ACEPTACIÓN ---

            // BOTÓN AGREGAR (NARANJA - Solo si NO existe carta)
            Action::make('add_carta_acceptance')
                ->label('Agregar Carta de Aceptación')
                ->icon('heroicon-s-document-plus')
                ->color('warning')
                ->form([
                    FileUpload::make('carta_acceptance')
                        ->directory('doctor-nurses/carta-acceptance')
                        ->label('Carta de Aceptación')
                        ->required()
                        ->maxSize(2048),
                ])
                ->action(function (array $data) use ($record): void {
                    $record->update(['carta_acceptance' => $data['carta_acceptance']]);

                    SecurityAudit::log('AUDIT_OPERATIONS_DOCTOR_NURSE_DOCUMENT_UPLOADED', 'operations.doctor-nurses.carta-acceptance.upload', [
                        'doctor_nurse_id' => $record->id,
                        'doctor_nurse_name' => $record->name,
                        'document_type' => 'CARTA_ACEPTACION',
                        'path' => $record->carta_acceptance,
                    ]);

                    Notification::make()->success()->title('Carta cargada')->send();
                })
                ->hidden(fn () => filled($record->carta_acceptance))
                ->extraAttributes(['class' => self::WARNING_BUTTON_CLASS]),

            // BOTÓN VER (VERDE - Solo si YA existe carta)
            Action::make('view_carta_acceptance')
                ->label('Ver Carta de Aceptación')
                ->icon('heroicon-s-document-text')
                ->color('success') // Cambio de color a Verde
                ->modalHeading('Carta de aceptación')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalContent(fn () => View::make('filament.operations.doctor-nurses.carta-acceptance-preview', [
                    'exists' => $cartaExists,
                    'url' => $cartaUrl,
                    'downloadUrl' => $cartaDownloadUrl,
                    'extension' => $cartaExtension,
                    'doctorNurse' => $record,
                ]))
                ->modalSubmitAction(false)
                ->extraModalFooterActions([
                    Action::make('deleteCartaAcceptance')
                        ->label('Eliminar carta')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn () => $this->deleteCartaAcceptance()),
                ])
                ->hidden(fn () => blank($record->carta_acceptance))
                ->extraAttributes(['class' => self::TICKET_BUTTON_CLASS]), // Cambio de clase a Verde

            // BOTÓN DOCUMENTOS DE AFILIACIÓN (SIEMPRE VERDE)
            Action::make('view_documents')
                ->label('Documentos de Afiliación')
                ->icon('heroicon-s-document-duplicate')
                ->color('success')
                ->modalHeading('Documentos de afiliación')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalContent(fn () => View::make('filament.operations.doctor-nurses.documents-preview', [
                    'doctorNurse' => $record,
                    'documents' => $documentCards,
                ]))
                ->modalSubmitAction(false)
                ->extraModalFooterActions([
                    Action::make('appendAffiliationDocuments')
                        ->label('Agregar documentos')
                        ->icon('heroicon-o-plus-circle')
                        ->color('warning')
                        ->form([
                            FileUpload::make('new_documents')
                                ->directory('doctor-nurses/documents')
                                ->multiple()
                                ->maxFiles(10)
                                ->maxSize(2048),
                        ])
                        ->action(function (array $data) use ($record): void {
                            $existing = is_array($record->documents) ? $record->documents : [];
                            $incoming = array_values(array_filter(
                                $data['new_documents'] ?? [],
                                fn (mixed $path): bool => is_string($path) && trim($path) !== '',
                            ));
                            $record->update(['documents' => array_values(array_merge($existing, $incoming))]);

                            SecurityAudit::log('AUDIT_OPERATIONS_DOCTOR_NURSE_DOCUMENT_UPLOADED', 'operations.doctor-nurses.documents.upload', [
                                'doctor_nurse_id' => $record->id,
                                'doctor_nurse_name' => $record->name,
                                'document_type' => 'AFILIACION',
                                'uploaded_count' => count($incoming),
                                'uploaded_paths' => $incoming,
                                'total_documents' => count($record->documents ?? []),
                            ]);

                            Notification::make()->success()->title('Documentos agregados')->send();
                            $this->replaceMountedAction('view_documents');
                        }),
                ])
                ->extraAttributes(['class' => self::TICKET_BUTTON_CLASS]),
        ];
    }
}
