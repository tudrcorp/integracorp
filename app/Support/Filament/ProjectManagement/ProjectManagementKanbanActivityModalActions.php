<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource;
use App\Models\ProjectManagement\Activity;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

final class ProjectManagementKanbanActivityModalActions
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function activityIdFromArguments(array $arguments): int
    {
        return (int) ($arguments['activityId'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function tryResolveActivity(array $arguments): ?Activity
    {
        $activityId = self::activityIdFromArguments($arguments);

        if ($activityId <= 0) {
            return null;
        }

        return Activity::query()
            ->with(['project:id,name', 'subproject:id,name'])
            ->withCount(['notesLogs', 'documents'])
            ->find($activityId);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function resolveActivity(array $arguments): Activity
    {
        $activity = self::tryResolveActivity($arguments);

        if ($activity === null) {
            throw (new ModelNotFoundException)->setModel(Activity::class, [self::activityIdFromArguments($arguments)]);
        }

        return $activity;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function activityHasNotes(array $arguments): bool
    {
        $activityId = self::activityIdFromArguments($arguments);

        if ($activityId <= 0) {
            return false;
        }

        return Activity::query()
            ->whereKey($activityId)
            ->whereHas('notesLogs')
            ->exists();
    }

    public static function activityViewBitacoraUrl(Activity $activity): string
    {
        $url = ActivityResource::getUrl('view', ['record' => $activity], panel: 'projects');

        return $url.'?tab='.rawurlencode(ProjectManagementFilamentSchemas::ACTIVITY_INFOLIST_BITACORA_TAB_QUERY);
    }

    public static function makeAddNoteAction(): Action
    {
        return Action::make('addActivityNote')
            ->label('Notas')
            ->icon('heroicon-m-chat-bubble-left-ellipsis')
            ->modalIcon('heroicon-o-chat-bubble-left-right')
            ->modalHeading('Agregar nota')
            ->modalDescription('Registra seguimiento interno sin salir del tablero Kanban.')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Guardar nota')
            ->modalCancelActionLabel('Cerrar')
            ->extraModalFooterActions(fn (array $arguments): array => self::addNoteModalFooterActions($arguments))
            ->form(fn (array $arguments): array => self::addNoteFormSchema($arguments))
            ->action(function (array $arguments, array $data): void {
                $activity = self::resolveActivity($arguments);

                $activity->notesLogs()->create([
                    'user_id' => (int) Auth::id(),
                    'content' => trim((string) $data['content']),
                ]);
            })
            ->successNotification(function (array $arguments): Notification {
                $activity = self::resolveActivity($arguments);

                return Notification::make()
                    ->success()
                    ->title('Nota registrada')
                    ->body("La nota quedó asociada a «{$activity->title}».");
            });
    }

    public static function makeUploadDocumentAction(): Action
    {
        return Action::make('uploadActivityDocument')
            ->label('Documentos')
            ->icon('heroicon-m-arrow-up-tray')
            ->modalIcon('heroicon-o-document-arrow-up')
            ->modalHeading('Cargar documento')
            ->modalDescription('Adjunta archivos de soporte sin salir del tablero Kanban.')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Cargar archivo')
            ->modalCancelActionLabel('Cerrar')
            ->form(fn (array $arguments): array => self::uploadDocumentFormSchema($arguments))
            ->action(function (array $arguments, array $data): void {
                $activity = self::resolveActivity($arguments);

                $storedPath = is_array($data['file'] ?? null)
                    ? ($data['file'][0] ?? null)
                    : ($data['file'] ?? null);

                if (! is_string($storedPath) || $storedPath === '') {
                    return;
                }

                $disk = Storage::disk('public');

                $activity->documents()->create([
                    'name' => filled($data['name'] ?? null)
                        ? (string) $data['name']
                        : basename($storedPath),
                    'file_path' => $storedPath,
                    'file_type' => $disk->mimeType($storedPath) ?: null,
                    'file_size' => $disk->exists($storedPath) ? $disk->size($storedPath) : null,
                    'uploaded_by' => (int) Auth::id(),
                ]);
            })
            ->successNotification(function (array $arguments): Notification {
                $activity = self::resolveActivity($arguments);

                return Notification::make()
                    ->success()
                    ->title('Documento cargado')
                    ->body("El archivo quedó asociado a «{$activity->title}».");
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, Action>
     */
    private static function addNoteModalFooterActions(array $arguments): array
    {
        $activity = self::tryResolveActivity($arguments);

        if ($activity === null || ! self::activityHasNotes($arguments)) {
            return [];
        }

        return [
            Action::make('viewActivityBitacora')
                ->label('Ver bitácora completa')
                ->icon('heroicon-m-book-open')
                ->color('gray')
                ->link()
                ->url(self::activityViewBitacoraUrl($activity))
                ->openUrlInNewTab(false),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, Placeholder|Textarea>
     */
    private static function addNoteFormSchema(array $arguments): array
    {
        return [
            Placeholder::make('activity_context')
                ->hiddenLabel()
                ->content(function () use ($arguments): HtmlString {
                    $activity = self::resolveActivity($arguments);

                    return new HtmlString(
                        view('filament.projects.actions.kanban-activity-modal-context', [
                            'record' => $activity,
                            'mode' => 'notes',
                        ])->render(),
                    );
                })
                ->columnSpanFull(),
            Textarea::make('content')
                ->label('Nueva nota')
                ->helperText('Describe avances, acuerdos o el siguiente paso de la actividad.')
                ->placeholder('Escribe la nota de seguimiento…')
                ->rows(5)
                ->required()
                ->maxLength(5000)
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, FileUpload|Placeholder|TextInput>
     */
    private static function uploadDocumentFormSchema(array $arguments): array
    {
        return [
            Placeholder::make('activity_context')
                ->hiddenLabel()
                ->content(function () use ($arguments): HtmlString {
                    $activity = self::resolveActivity($arguments);

                    return new HtmlString(
                        view('filament.projects.actions.kanban-activity-modal-context', [
                            'record' => $activity,
                            'mode' => 'documents',
                        ])->render(),
                    );
                })
                ->columnSpanFull(),
            FileUpload::make('file')
                ->label('Archivo')
                ->helperText('Formatos habituales: PDF, imágenes, Word o Excel. Tamaño máximo 10 MB.')
                ->disk('public')
                ->directory('project-management/activities')
                ->visibility('public')
                ->required()
                ->maxSize(10240)
                ->columnSpanFull(),
            TextInput::make('name')
                ->label('Nombre del documento')
                ->helperText('Opcional. Si lo dejas vacío, se usará el nombre del archivo.')
                ->maxLength(255)
                ->columnSpanFull(),
        ];
    }
}
