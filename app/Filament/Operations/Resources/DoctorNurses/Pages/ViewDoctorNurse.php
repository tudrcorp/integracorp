<?php

namespace App\Filament\Operations\Resources\DoctorNurses\Pages;

use App\Filament\Operations\Resources\DoctorNurses\DoctorNurseResource;
use App\Models\DoctorNurse;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\View\View as ViewContract;

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
            ->body('La carta de aceptación fue eliminada correctamente.')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        /** @var DoctorNurse $record */
        $record = $this->record;

        $cartaPath = is_string($record->carta_acceptance) ? $record->carta_acceptance : null;
        $cartaExists = is_string($cartaPath) && trim($cartaPath) !== '' && Storage::disk('public')->exists($cartaPath);
        $cartaExtension = $cartaPath ? strtolower((string) pathinfo($cartaPath, PATHINFO_EXTENSION)) : '';
        $cartaUrl = $cartaExists ? url('storage/'.Str::ltrim($cartaPath, '/')) : null;
        $cartaDownloadUrl = $cartaPath ? route('operations.doctor-nurses.carta-acceptance.download', ['doctorNurse' => $record]) : null;

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
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(DoctorNurseResource::getUrl('index'))
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
                ->modalHeading('Ficha del proveedor natural en PDF')
                ->modalDescription('Vista previa de la ficha técnica. La primera generación puede tardar; las siguientes suelen ser más rápidas mientras los datos no cambien (caché por proveedor).')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalIcon('heroicon-o-document-text')
                ->modalContent(function (DoctorNurse $record): ViewContract {
                    return View::make('filament.operations.doctor-nurses.doctor-nurse-ficha-preview-modal', [
                        'pdfPreviewUrl' => route('operations.doctor-nurses.ficha.preview', ['doctorNurse' => $record]),
                        'pdfDownloadUrl' => route('operations.doctor-nurses.ficha.download', ['doctorNurse' => $record]),
                        'doctorNurseLabel' => filled($record->name) ? $record->name : ('Proveedor natural #'.$record->id),
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
                        ->directory('doctor-nurses/carta-acceptance')
                        ->label('Carta de Aceptación')
                        ->required()
                        ->maxFiles(1)
                        ->maxSize(2048),
                ])
                ->action(function (array $data) use ($record): void {
                    $record->carta_acceptance = $data['carta_acceptance'] ?? null;
                    $record->save();

                    Notification::make()
                        ->success()
                        ->title('Carta cargada')
                        ->body('La carta de aceptación fue cargada correctamente.')
                        ->send();
                })
                ->hidden(fn (): bool => filled($record->carta_acceptance))
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ]),

            Action::make('view_carta_acceptance')
                ->label('Ver Carta de Aceptación')
                ->icon('heroicon-s-document-text')
                ->color('warning')
                ->modalHeading('Carta de aceptación')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalContent(fn (): ViewContract => View::make('filament.operations.doctor-nurses.carta-acceptance-preview', [
                    'exists' => $cartaExists,
                    'url' => $cartaUrl,
                    'downloadUrl' => $cartaDownloadUrl,
                    'extension' => $cartaExtension,
                    'doctorNurse' => $record,
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->extraModalFooterActions([
                    Action::make('deleteCartaAcceptance')
                        ->label('Eliminar carta')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn () => $this->deleteCartaAcceptance()),
                ])
                ->action(fn () => null)
                ->hidden(fn (): bool => blank($record->carta_acceptance))
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ]),

            Action::make('view_documents')
                ->label('Documentos de Afiliación')
                ->icon('heroicon-s-document-text')
                ->color('success')
                ->modalHeading('Documentos de afiliación')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalContent(fn (): ViewContract => View::make('filament.operations.doctor-nurses.documents-preview', [
                    'doctorNurse' => $record,
                    'documents' => $documentCards,
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->extraModalFooterActions([
                    Action::make('appendAffiliationDocuments')
                        ->label('Agregar documentos')
                        ->icon('heroicon-o-plus-circle')
                        ->color('warning')
                        ->form([
                            FileUpload::make('new_documents')
                                ->directory('doctor-nurses/documents')
                                ->label('Archivos a agregar')
                                ->multiple()
                                ->maxFiles(10)
                                ->maxSize(2048),
                        ])
                        ->action(function (array $data) use ($record): void {
                            $new = $data['new_documents'] ?? [];
                            $new = is_array($new) ? array_values(array_filter($new)) : [];

                            $existing = is_array($record->documents) ? array_values($record->documents) : [];
                            $record->documents = array_values(array_merge($existing, $new));
                            $record->save();

                            Notification::make()
                                ->success()
                                ->title('Documentos agregados')
                                ->body('Los documentos de afiliación fueron agregados correctamente.')
                                ->send();
                        }),
                ])
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),

        ];
    }
}
