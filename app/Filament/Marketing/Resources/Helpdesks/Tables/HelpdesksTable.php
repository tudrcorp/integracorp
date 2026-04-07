<?php

namespace App\Filament\Marketing\Resources\Helpdesks\Tables;

use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Support\HelpdeskObservationAppender;
use App\Support\HelpdeskTaskStatusOptions;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class HelpdesksTable
{
    private const IOS_SECTION_CLASS = 'fi-helpdesk-ios-section';

    private const IOS_SUCCESS_BTN = 'aviso-btn-ios-success shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_GRAY_BTN = 'ticket-btn-ios-gray shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public static function configure(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $colaborador = RrhhColaborador::query()
                    ->where('user_id', Auth::id())
                    ->first();

                return HelpDesk::query()->where(function (Builder $q) use ($colaborador): void {
                    $q->where('created_by', Auth::user()->name);
                    if ($colaborador) {
                        $q->orWhere('rrhh_colaborador_id', $colaborador->id);
                    }
                });
            })
            ->columns([
                TextColumn::make('description')
                    ->label('Descripción')
                    ->icon('heroicon-m-document-text')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->icon(fn (?string $state): ?string => match ($state) {
                        'BAJA' => 'heroicon-m-arrow-trending-down',
                        'MEDIA' => 'heroicon-m-minus',
                        'ALTA' => 'heroicon-m-arrow-trending-up',
                        default => null,
                    })
                    ->iconColor(fn (?string $state): ?string => match ($state) {
                        'BAJA' => 'success',
                        'MEDIA' => 'warning',
                        'ALTA' => 'danger',
                        default => null,
                    })
                    ->badge()
                    ->color(function ($record) {
                        return match ($record->priority) {
                            'BAJA' => 'success',
                            'MEDIA' => 'warning',
                            'ALTA' => 'danger',
                        };
                    })
                    ->searchable(),
                TextColumn::make('rrhhColaborador.fullName')
                    ->label('Asignado a')
                    ->icon('heroicon-m-user')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->icon('heroicon-m-user-circle')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->icon('heroicon-m-calendar')
                    ->description(fn ($record) => $record->created_at->diffForHumans())
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->icon(fn (?string $state): ?string => match ($state) {
                        'PENDIENTE POR INICIAR' => 'heroicon-m-clock',
                        'EN PROCESO' => 'heroicon-m-arrow-path',
                        'TERMINADO' => 'heroicon-m-check-circle',
                        'CANCELADO' => 'heroicon-m-x-circle',
                        default => null,
                    })
                    ->iconColor(fn (?string $state): ?string => match ($state) {
                        'PENDIENTE POR INICIAR' => 'warning',
                        'EN PROCESO' => 'primary',
                        'TERMINADO' => 'success',
                        'CANCELADO' => 'danger',
                        default => null,
                    })
                    ->badge()
                    ->color(function ($record) {
                        return match ($record->status) {
                            'PENDIENTE POR INICIAR' => 'warning',
                            'EN PROCESO' => 'primary',
                            'TERMINADO' => 'success',
                            'CANCELADO' => 'danger',
                        };
                    })
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->icon('heroicon-m-calendar-days')
                    ->description(fn ($record) => $record->updated_at->diffForHumans())
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordClasses(fn ($record): array => $record->status === 'TERMINADO'
                ? []
                : [
                    match ($record->priority) {
                        'BAJA' => 'bg-green-50 dark:bg-green-950/30 border-l-4 border-green-500',
                        'MEDIA' => 'bg-amber-50 dark:bg-amber-950/30 border-l-4 border-amber-500',
                        'ALTA' => 'bg-red-50 dark:bg-red-950/30 border-l-4 border-red-500',
                        default => 'border-l-4 border-gray-200 dark:border-gray-700',
                    },
                ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('addNote')
                        ->label('Añadir nota')
                        ->icon('heroicon-m-plus-circle')
                        ->color('success')
                        ->slideOver()
                        ->modalWidth(Width::ThreeExtraLarge)
                        ->modalHeading('Añadir nota al ticket')
                        ->modalDescription(fn (HelpDesk $record): string => 'Seguimiento interno · Ticket #'.$record->getKey().' · '.$record->created_by)
                        ->modalSubmitActionLabel('Guardar nota')
                        ->modalSubmitAction(
                            fn (Action $action): Action => $action
                                ->extraAttributes([
                                    'class' => self::IOS_SUCCESS_BTN,
                                ])
                        )
                        ->modalCancelAction(
                            fn (Action $action): Action => $action
                                ->label('Cancelar')
                                ->extraAttributes([
                                    'class' => self::IOS_GRAY_BTN,
                                ])
                        )
                        ->form([
                            Section::make('Nueva entrada')
                                ->description('El texto se añade al historial con fecha y tu nombre. No sustituye notas anteriores.')
                                ->icon('heroicon-m-pencil-square')
                                ->schema([
                                    Textarea::make('note')
                                        ->label('Nota')
                                        ->placeholder('Describe el avance, acuerdos o el siguiente paso…')
                                        ->rows(8)
                                        ->autosize()
                                        ->required()
                                        ->minLength(3)
                                        ->maxLength(65000)
                                        ->columnSpanFull(),
                                ])
                                ->columns(1)
                                ->columnSpanFull()
                                ->extraAttributes([
                                    'class' => self::IOS_SECTION_CLASS,
                                ]),
                        ])
                        ->successNotification(null)
                        ->action(function (HelpDesk $record, array $data): void {
                            $user = Auth::user();
                            if ($user === null) {
                                return;
                            }

                            HelpdeskObservationAppender::append($record, (string) ($data['note'] ?? ''), $user->name);

                            Notification::make()
                                ->title('Nota guardada')
                                ->body('Se añadió la nota al ticket #'.$record->getKey().'.')
                                ->success()
                                ->send();
                        })
                        ->hidden(fn (HelpDesk $record): bool => $record->status === 'TERMINADO'),
                    Action::make('updateStatus')
                        ->label('Actualizar estado')
                        ->icon('heroicon-m-arrow-path')
                        ->color('warning')
                        ->slideOver()
                        ->modalWidth(Width::ThreeExtraLarge)
                        ->modalHeading('Actualizar estado del ticket')
                        ->modalDescription(fn (HelpDesk $record): string => 'Ticket #'.$record->getKey().' · '.$record->created_by)
                        ->modalSubmitActionLabel('Guardar estado')
                        ->modalSubmitAction(
                            fn (Action $action): Action => $action
                                ->extraAttributes([
                                    'class' => self::IOS_SUCCESS_BTN,
                                ])
                        )
                        ->modalCancelAction(
                            fn (Action $action): Action => $action
                                ->label('Cancelar')
                                ->extraAttributes([
                                    'class' => self::IOS_GRAY_BTN,
                                ])
                        )
                        ->fillForm(fn (HelpDesk $record): array => [
                            'status' => $record->status,
                        ])
                        ->form(function (HelpDesk $record): array {
                            return [
                                Section::make('Estado')
                                    ->description('Solo quien creó el ticket puede marcarlo como Terminado o Cancelado.')
                                    ->icon('heroicon-m-flag')
                                    ->schema([
                                        Select::make('status')
                                            ->label('Estado')
                                            ->prefixIcon('heroicon-m-flag')
                                            ->options(HelpdeskTaskStatusOptions::forSelect($record, Auth::user()?->name))
                                            ->required()
                                            ->native(true)
                                            ->extraInputAttributes([
                                                'class' => 'helpdesk-status-native-select w-full max-w-full min-h-11 text-base sm:text-sm',
                                            ]),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull()
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ]),
                            ];
                        })
                        ->successNotification(null)
                        ->action(function (HelpDesk $record, array $data): void {
                            $user = Auth::user();
                            if ($user === null) {
                                return;
                            }

                            $newStatus = (string) ($data['status'] ?? $record->status);
                            $sanitized = HelpdeskTaskStatusOptions::sanitizeStatusForSave($record, $newStatus, $user->name);

                            if ($sanitized === $record->status) {
                                Notification::make()
                                    ->title('Sin cambios')
                                    ->body('El estado del ticket no se modificó.')
                                    ->info()
                                    ->send();

                                return;
                            }

                            $record->status = $sanitized;
                            $record->updated_by = $user->name;
                            $record->save();

                            Notification::make()
                                ->title('Estado actualizado')
                                ->body('El ticket #'.$record->getKey().' quedó en: '.(HelpdeskTaskStatusOptions::all()[$sanitized] ?? $sanitized).'.')
                                ->success()
                                ->send();
                        })
                        ->hidden(fn (HelpDesk $record): bool => $record->status === 'TERMINADO'),
                    Action::make('previewAttachment')
                        ->label('Vista previa')
                        ->icon('heroicon-m-eye')
                        ->color('info')
                        ->visible(fn (HelpDesk $record): bool => filled($record->image))
                        ->slideOver()
                        ->modalWidth(Width::FiveExtraLarge)
                        ->modalHeading('Adjunto del ticket')
                        ->modalDescription(fn (HelpDesk $record): string => 'Ticket #'.$record->getKey().' · '.$record->created_by)
                        ->modalContent(function (HelpDesk $record) {
                            $path = $record->image;
                            $disk = Storage::disk('public');
                            $exists = filled($path) && $disk->exists($path);
                            $url = $exists ? $disk->url($path) : '';
                            $extension = $exists ? pathinfo($path, PATHINFO_EXTENSION) : '';

                            return view('filament.business.helpdesks.preview-attachment', [
                                'record' => $record,
                                'url' => $url,
                                'extension' => $extension,
                                'missing' => ! $exists,
                            ]);
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->modalFooterActions([
                            Action::make('openInTab')
                                ->label('Abrir en pestaña')
                                ->icon('heroicon-m-arrow-top-right-on-square')
                                ->url(function (HelpDesk $record): string {
                                    $path = $record->image;
                                    if (! filled($path) || ! Storage::disk('public')->exists($path)) {
                                        return '#';
                                    }

                                    return Storage::disk('public')->url($path);
                                })
                                ->openUrlInNewTab()
                                ->color('gray')
                                ->extraAttributes([
                                    'class' => 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]',
                                ])
                                ->disabled(fn (HelpDesk $record): bool => ! filled($record->image) || ! Storage::disk('public')->exists((string) $record->image)),
                        ])
                        ->action(fn (): null => null),
                    Action::make('viewNotes')
                        ->label('Ver notas')
                        ->icon('heroicon-m-clipboard-document-list')
                        ->color('gray')
                        ->slideOver()
                        ->modalWidth(Width::ThreeExtraLarge)
                        ->modalHeading('Notas del ticket')
                        ->modalDescription(fn (HelpDesk $record): string => 'Seguimiento interno · Ticket #'.$record->getKey())
                        ->modalContent(function (HelpDesk $record) {
                            $updated = $record->updated_at
                                ? Carbon::parse($record->updated_at)->timezone(config('app.timezone'))
                                : now()->timezone(config('app.timezone'));

                            $daysElapsed = (int) $updated->copy()->startOfDay()->diffInDays(now()->copy()->startOfDay());

                            return view('filament.business.helpdesks.notes-modal', [
                                'record' => $record,
                                'observation' => $record->observation,
                                'updatedAtFormatted' => $updated->format('d/m/Y H:i'),
                                'updatedRelative' => $updated->diffForHumans(),
                                'daysElapsed' => $daysElapsed,
                                'updatedBy' => $record->updated_by,
                            ]);
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelAction(
                            Action::make('dismissNotes')
                                ->label('Listo')
                                ->extraAttributes([
                                    'class' => 'aviso-btn-ios-success shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]',
                                ]),
                        )
                        ->action(fn (): null => null),
                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                ]),
            ]);
    }
}
