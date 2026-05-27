<?php

namespace App\Filament\Business\Resources\Helpdesks\Pages;

use App\Filament\Business\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
use App\Filament\Business\Resources\Helpdesks\Tables\HelpdesksTable;
use App\Filament\Business\Resources\Helpdesks\Widgets\HelpdeskStatusWeeklyChart;
use App\Filament\Business\Resources\Helpdesks\Widgets\StatsOverviewHelpdesk;
use App\Filament\Concerns\ManagesHelpdeskWorkGroupsOnList;
use App\Models\HelpdeskFlowProcessFile;
use App\Models\HelpdeskVideoTutorialFile;
use App\Support\HelpdeskWorkGroupHeaderAction;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ListHelpdesks extends ListRecords
{
    use ExposesTableToWidgets;
    use ManagesHelpdeskWorkGroupsOnList;

    protected static string $resource = HelpdeskResource::class;

    protected static ?string $title = 'Gestión de Tickets';

    public ?string $activeTab = null;

    public function mountDeleteHelpdeskFlowProcessFile(int $fileId): void
    {
        $this->replaceMountedAction('deleteHelpdeskFlowProcessFile', ['fileId' => $fileId]);
    }

    protected function deleteHelpdeskFlowProcessFileAction(): Action
    {
        return Action::make('deleteHelpdeskFlowProcessFile')
            ->label('Eliminar')
            ->color('danger')
            ->icon(Heroicon::OutlinedTrash)
            ->modalIcon(Heroicon::OutlinedTrash)
            ->requiresConfirmation()
            ->modalHeading('Borrar archivo del flujo')
            ->modalDescription('¿Está segura/o de hacer esto?')
            ->modalSubmitActionLabel('Borrar')
            ->modalCancelActionLabel('Cancelar')
            ->modalSubmitAction(
                fn (Action $action): Action => $action->color('danger')
            )
            ->action(function (Action $action): void {
                $fileId = (int) ($action->getArguments()['fileId'] ?? 0);
                if ($fileId < 1) {
                    $this->replaceMountedAction('helpdeskFlowProcess');

                    return;
                }

                $file = HelpdeskFlowProcessFile::query()->find($fileId);
                if ($file === null) {
                    Notification::make()
                        ->title('Archivo no encontrado')
                        ->warning()
                        ->send();

                    $this->replaceMountedAction('helpdeskFlowProcess');

                    return;
                }

                $path = (string) $file->file_path;
                $disk = Storage::disk('public');
                if ($path !== '' && $disk->exists($path)) {
                    $disk->delete($path);
                }

                $file->delete();

                Notification::make()
                    ->title('Archivo eliminado')
                    ->success()
                    ->send();

                $this->replaceMountedAction('helpdeskFlowProcess');
            });
    }

    public function mountDeleteHelpdeskVideoTutorialFile(int $fileId): void
    {
        $this->replaceMountedAction('deleteHelpdeskVideoTutorialFile', ['fileId' => $fileId]);
    }

    protected function deleteHelpdeskVideoTutorialFileAction(): Action
    {
        return Action::make('deleteHelpdeskVideoTutorialFile')
            ->label('Eliminar')
            ->color('danger')
            ->icon(Heroicon::OutlinedTrash)
            ->modalIcon(Heroicon::OutlinedTrash)
            ->requiresConfirmation()
            ->modalHeading('Borrar archivo del video tutorial')
            ->modalDescription('¿Está segura/o de hacer esto?')
            ->modalSubmitActionLabel('Borrar')
            ->modalCancelActionLabel('Cancelar')
            ->modalSubmitAction(
                fn (Action $action): Action => $action->color('danger')
            )
            ->action(function (Action $action): void {
                $fileId = (int) ($action->getArguments()['fileId'] ?? 0);
                if ($fileId < 1) {
                    $this->replaceMountedAction('helpdeskVideoTutorial');

                    return;
                }

                $file = HelpdeskVideoTutorialFile::query()->find($fileId);
                if ($file === null) {
                    Notification::make()
                        ->title('Archivo no encontrado')
                        ->warning()
                        ->send();

                    $this->replaceMountedAction('helpdeskVideoTutorial');

                    return;
                }

                $path = (string) $file->file_path;
                $disk = Storage::disk('public');
                if ($path !== '' && $disk->exists($path)) {
                    $disk->delete($path);
                }

                $file->delete();

                Notification::make()
                    ->title('Archivo eliminado')
                    ->success()
                    ->send();

                $this->replaceMountedAction('helpdeskVideoTutorial');
            });
    }

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * Estilo visual del tutorial: theme.css → #helpdesk-tour-btn (borde azul, fondo claro).
     * No usar .ticket-btn-ios-shell aquí: fuerza otro border con !important.
     */
    private const TOUR_BUTTON_CLASS = 'shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const FLOW_PROCESS_BUTTON_CLASS = 'shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('helpdeskVideoTutorial')
                ->label('Video tutorial')
                ->icon('heroicon-o-video-camera')
                ->color('gray')
                ->extraAttributes([
                    'id' => 'helpdesk-video-tutorial-btn',
                    'data-helpdesk-video-tutorial-trigger' => 'true',
                    'class' => self::TOUR_BUTTON_CLASS,
                ])
                ->slideOver()
                ->modalWidth(Width::FiveExtraLarge)
                ->modalHeading('Video tutorial')
                ->modalDescription('Carga y gestiona videos tutoriales y material relacionado (PDF, PPT, PPTX, videos e imágenes).')
                ->modalSubmitActionLabel('Guardar archivos')
                ->modalSubmitAction(
                    fn (Action $action): Action => $action->extraAttributes([
                        'class' => HelpdeskTicketModalActions::IOS_SUCCESS_BTN,
                    ])
                )
                ->modalCancelAction(
                    fn (Action $action): Action => $action
                        ->label('Cerrar')
                        ->extraAttributes([
                            'class' => HelpdeskTicketModalActions::IOS_GRAY_BTN,
                        ])
                )
                ->form([
                    FileUpload::make('videoTutorialFiles')
                        ->label('Archivos del video tutorial')
                        ->multiple()
                        ->reorderable()
                        ->openable()
                        ->downloadable()
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/vnd.ms-powerpoint',
                            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                            'video/mp4',
                            'video/quicktime',
                            'video/x-msvideo',
                            'video/x-ms-wmv',
                            'video/x-matroska',
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                        ])
                        ->maxSize(10240)
                        ->disk('public')
                        ->directory('helpdesks/video-tutorial')
                        ->preserveFilenames()
                        ->helperText('Formatos permitidos: PDF, PPT, PPTX, video e imágenes. Máximo 10 MB por archivo.')
                        ->columnSpanFull(),
                ])
                ->modalContent(fn () => view('filament.helpdesks.video-tutorial-modal', [
                    'files' => HelpdeskVideoTutorialFile::query()->latest()->get(),
                ]))
                ->action(function (array $data): void {
                    $paths = collect($data['videoTutorialFiles'] ?? [])
                        ->filter(fn (mixed $path): bool => is_string($path) && trim($path) !== '')
                        ->map(fn (string $path): string => trim($path))
                        ->unique()
                        ->values();

                    if ($paths->isEmpty()) {
                        Notification::make()
                            ->warning()
                            ->title('Sin cambios')
                            ->body('No se cargaron archivos nuevos.')
                            ->send();

                        return;
                    }

                    $disk = Storage::disk('public');
                    $uploadedBy = auth()->id();
                    $storedCount = 0;

                    foreach ($paths as $path) {
                        $alreadyExists = HelpdeskVideoTutorialFile::query()
                            ->where('file_path', $path)
                            ->exists();

                        if ($alreadyExists) {
                            continue;
                        }

                        HelpdeskVideoTutorialFile::query()->create([
                            'file_path' => $path,
                            'original_name' => basename($path),
                            'mime_type' => $disk->mimeType($path) ?: null,
                            'file_size' => $disk->exists($path) ? $disk->size($path) : null,
                            'uploaded_by' => is_numeric($uploadedBy) ? (int) $uploadedBy : null,
                        ]);

                        $storedCount++;
                    }

                    Notification::make()
                        ->success()
                        ->title($storedCount > 0 ? 'Archivos guardados' : 'Sin nuevos registros')
                        ->body(
                            $storedCount > 0
                                ? $storedCount.' archivo(s) agregado(s) a la biblioteca de video tutoriales.'
                                : 'Los archivos ya estaban registrados en la biblioteca.'
                        )
                        ->send();
                }),
            Action::make('helpdeskTour')
                ->label('Tutorial de uso')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->extraAttributes([
                    'id' => 'helpdesk-tour-btn',
                    'data-helpdesk-tour-trigger' => 'true',
                    'class' => self::TOUR_BUTTON_CLASS,
                ])
                ->action(fn (): null => null),
            Action::make('helpdeskFlowProcess')
                ->label('Flujo del proceso')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('gray')
                ->extraAttributes([
                    'id' => 'helpdesk-flow-process-btn',
                    'data-helpdesk-flow-process-trigger' => 'true',
                    'class' => self::FLOW_PROCESS_BUTTON_CLASS,
                ])
                ->slideOver()
                ->modalWidth(Width::FiveExtraLarge)
                ->modalHeading('Flujo del proceso')
                ->modalDescription('Carga y gestiona material de apoyo (PDF, PPT, PPTX, videos e imágenes).')
                ->modalSubmitActionLabel('Guardar archivos')
                ->modalSubmitAction(
                    fn (Action $action): Action => $action->extraAttributes([
                        'class' => HelpdeskTicketModalActions::IOS_SUCCESS_BTN,
                    ])
                )
                ->modalCancelAction(
                    fn (Action $action): Action => $action
                        ->label('Cerrar')
                        ->extraAttributes([
                            'class' => HelpdeskTicketModalActions::IOS_GRAY_BTN,
                        ])
                )
                ->form([
                    FileUpload::make('flowProcessFiles')
                        ->label('Archivos del flujo')
                        ->multiple()
                        ->reorderable()
                        ->openable()
                        ->downloadable()
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/vnd.ms-powerpoint',
                            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                            'video/mp4',
                            'video/quicktime',
                            'video/x-msvideo',
                            'video/x-ms-wmv',
                            'video/x-matroska',
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                        ])
                        ->maxSize(10240)
                        ->disk('public')
                        ->directory('helpdesks/flow-process')
                        ->preserveFilenames()
                        ->helperText('Formatos permitidos: PDF, PPT, PPTX, video e imágenes. Máximo 10 MB por archivo.')
                        ->columnSpanFull(),
                ])
                ->modalContent(fn () => view('filament.helpdesks.flow-process-modal', [
                    'files' => HelpdeskFlowProcessFile::query()->latest()->get(),
                ]))
                ->action(function (array $data): void {
                    $paths = collect($data['flowProcessFiles'] ?? [])
                        ->filter(fn (mixed $path): bool => is_string($path) && trim($path) !== '')
                        ->map(fn (string $path): string => trim($path))
                        ->unique()
                        ->values();

                    if ($paths->isEmpty()) {
                        Notification::make()
                            ->warning()
                            ->title('Sin cambios')
                            ->body('No se cargaron archivos nuevos.')
                            ->send();

                        return;
                    }

                    $disk = Storage::disk('public');
                    $uploadedBy = auth()->id();
                    $storedCount = 0;

                    foreach ($paths as $path) {
                        $alreadyExists = HelpdeskFlowProcessFile::query()
                            ->where('file_path', $path)
                            ->exists();

                        if ($alreadyExists) {
                            continue;
                        }

                        HelpdeskFlowProcessFile::query()->create([
                            'file_path' => $path,
                            'original_name' => basename($path),
                            'mime_type' => $disk->mimeType($path) ?: null,
                            'file_size' => $disk->exists($path) ? $disk->size($path) : null,
                            'uploaded_by' => is_numeric($uploadedBy) ? (int) $uploadedBy : null,
                        ]);

                        $storedCount++;
                    }

                    Notification::make()
                        ->success()
                        ->title($storedCount > 0 ? 'Archivos guardados' : 'Sin nuevos registros')
                        ->body(
                            $storedCount > 0
                                ? $storedCount.' archivo(s) agregado(s) a la biblioteca del flujo.'
                                : 'Los archivos ya estaban registrados en la biblioteca.'
                        )
                        ->send();
                }),
            HelpdeskWorkGroupHeaderAction::make(),
            CreateAction::make()
                ->label('Crear ticket de soporte')
                ->icon('heroicon-s-plus')
                ->color('primary')
                ->extraAttributes([
                    'id' => 'helpdesk-create-ticket-btn',
                    'data-tour-shape' => 'pill',
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverviewHelpdesk::class,
            HelpdeskStatusWeeklyChart::class,
        ];
    }

    public function getTabs(): array
    {
        return HelpdesksTable::getTabs();
    }
}
