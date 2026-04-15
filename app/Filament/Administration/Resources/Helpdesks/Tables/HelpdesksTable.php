<?php

namespace App\Filament\Administration\Resources\Helpdesks\Tables;

use App\Filament\Administration\Resources\Helpdesks\Actions\HelpdeskTicketModalActions;
use App\Filament\Administration\Resources\Helpdesks\HelpdeskResource;
use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Support\HelpdeskDocumentPaths;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class HelpdesksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $colaborador = RrhhColaborador::query()
                    ->where('user_id', Auth::id())
                    ->first();

                return HelpDesk::query()
                    ->with(['rrhhColaboradores'])
                    ->where(function (Builder $q) use ($colaborador): void {
                        $q->where('created_by', Auth::user()->name);
                        if ($colaborador) {
                            $q->orWhereHas(
                                'rrhhColaboradores',
                                fn (Builder $sub): Builder => $sub->where('rrhh_colaboradors.id', $colaborador->id)
                            );
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
                TextColumn::make('rrhhColaboradores.fullName')
                    ->label('Asignados')
                    ->icon('heroicon-m-user')
                    ->listWithLineBreaks()
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
                EditAction::make()
                    ->visible(fn (HelpDesk $record): bool => HelpdeskResource::currentUserIsHelpdeskTicketCreator($record)),
                Action::make('previewDocuments')
                    ->label('Documentos')
                    ->icon('heroicon-m-document-magnifying-glass')
                    ->color('info')
                    ->visible(fn (HelpDesk $record): bool => count(HelpdeskDocumentPaths::paths($record)) > 0)
                    ->slideOver()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->modalHeading('Documentos del ticket')
                    ->modalDescription(fn (HelpDesk $record): string => 'Vista previa de archivos · Ticket #'.$record->getKey().' · '.$record->created_by)
                    ->modalContent(fn (HelpDesk $record) => view('filament.business.helpdesks.documents-preview-modal', [
                        'record' => $record,
                        'documents' => HelpdeskDocumentPaths::forPublicDisk($record),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(
                        Action::make('dismissDocumentsPreview')
                            ->label('Listo')
                            ->extraAttributes([
                                'class' => HelpdeskTicketModalActions::IOS_SUCCESS_BTN,
                            ]),
                    )
                    ->action(fn (): null => null),
                ActionGroup::make([
                    HelpdeskTicketModalActions::makeAddNoteAction(),
                    HelpdeskTicketModalActions::makeUpdateStatusAction(),
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
                                    'class' => HelpdeskTicketModalActions::IOS_SUCCESS_BTN,
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
