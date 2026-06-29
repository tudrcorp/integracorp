<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Agents\Tables;

use App\Filament\Administration\Resources\Agents\AgentResource;
use App\Http\Controllers\AgentExportCsvController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UtilsController;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class AgentsTable
{
    private const COLUMN_GROUP_HEADER_CLASS = '[&_th]:bg-gradient-to-r [&_th]:from-slate-100/95 [&_th]:via-slate-50/90 [&_th]:to-transparent dark:[&_th]:from-white/[0.08] dark:[&_th]:via-white/[0.04] dark:[&_th]:to-transparent [&_th]:font-semibold [&_th]:text-slate-800 dark:[&_th]:text-slate-100 [&_th]:border-b [&_th]:border-slate-200/80 dark:[&_th]:border-white/10';

    /** @return array<string, Tab> */
    public static function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todos')
                ->icon(Heroicon::OutlinedUserGroup),
            'activo' => Tab::make('Activos')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'ACTIVO')),
            'inactivo' => Tab::make('Inactivos')
                ->icon(Heroicon::OutlinedXCircle)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'INACTIVO')),
            'revision' => Tab::make('Por revisión')
                ->icon(Heroicon::OutlinedClock)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'POR REVISION')),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->query(function (Builder $query): Builder {
                $base = Agent::query()->with(['typeAgent', 'accountManager']);

                if (Auth::user()->is_accountManagers) {
                    return $base->where('ownerAccountManagers', Auth::user()->id);
                }

                return $base;
            })
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->heading('Agentes')
            ->description('Corredores y subagentes: jerarquía, contacto, comisiones y estatus. Use pestañas y filtros para priorizar activaciones.')
            ->striped()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->recordTitleAttribute('name')
            ->emptyStateHeading('Sin agentes')
            ->emptyStateDescription('No hay agentes registrados o no coinciden con los filtros aplicados.')
            ->emptyStateIcon(Heroicon::OutlinedAcademicCap)
            ->columns([
                ColumnGroup::make('Identificación', [
                    TextColumn::make('owner_code')
                        ->label('Jerarquía')
                        ->icon(Heroicon::OutlinedBuildingLibrary)
                        ->prefix(fn (Agent $record): string => self::hierarchyPrefix($record))
                        ->badge()
                        ->color('success')
                        ->searchable()
                        ->placeholder('—')
                        ->wrap(),
                    TextColumn::make('id')
                        ->label('Código')
                        ->icon(Heroicon::OutlinedHashtag)
                        ->prefix('AGT-000')
                        ->weight('semibold')
                        ->badge()
                        ->color('azulOscuro')
                        ->searchable()
                        ->sortable()
                        ->action(
                            ViewAction::make('viewFromCode')
                                ->url(fn (Agent $record): string => AgentResource::getUrl('view', ['record' => $record])),
                        ),
                    TextColumn::make('typeAgent.definition')
                        ->label('Tipo')
                        ->badge()
                        ->color('verde')
                        ->searchable()
                        ->placeholder('—'),
                    TextColumn::make('accountManager.full_name')
                        ->label('Account manager')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->badge()
                        ->color('warning')
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('name')
                        ->label('Razón social')
                        ->icon(Heroicon::OutlinedUser)
                        ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                        ->searchable()
                        ->sortable()
                        ->weight('medium')
                        ->limit(36)
                        ->tooltip(fn (Agent $record): ?string => filled($record->name) ? mb_strtoupper($record->name) : null)
                        ->wrap(),
                    TextColumn::make('ci')
                        ->label('CI')
                        ->searchable()
                        ->copyable()
                        ->placeholder('—')
                        ->toggleable(),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Contacto', [
                    TextColumn::make('email')
                        ->label('Correo')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->searchable()
                        ->copyable()
                        ->copyMessage('Correo copiado')
                        ->wrap()
                        ->placeholder('—'),
                    TextColumn::make('phone')
                        ->label('Teléfono')
                        ->icon(Heroicon::OutlinedPhone)
                        ->searchable()
                        ->copyable()
                        ->placeholder('—'),
                    TextColumn::make('address')
                        ->label('Dirección')
                        ->icon(Heroicon::OutlinedMapPin)
                        ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                        ->limit(40)
                        ->tooltip(fn (Agent $record): ?string => filled($record->address) ? mb_strtoupper($record->address) : null)
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Comisiones', [
                    TextColumn::make('commission_tdec')
                        ->label('TDEC')
                        ->suffix('%')
                        ->alignCenter()
                        ->badge()
                        ->color(fn (Agent $record): string => self::commissionColor($record->commission_tdec))
                        ->numeric(2)
                        ->sortable(),
                    TextColumn::make('commission_tdec_renewal')
                        ->label('TDEC renov.')
                        ->suffix('%')
                        ->alignCenter()
                        ->badge()
                        ->color(fn (Agent $record): string => self::commissionColor($record->commission_tdec_renewal))
                        ->numeric(2)
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('commission_tdev')
                        ->label('TDEV')
                        ->suffix('%')
                        ->alignCenter()
                        ->badge()
                        ->color(fn (Agent $record): string => self::commissionColor($record->commission_tdev))
                        ->numeric(2)
                        ->sortable(),
                    TextColumn::make('commission_tdev_renewal')
                        ->label('TDEV renov.')
                        ->suffix('%')
                        ->alignCenter()
                        ->badge()
                        ->color(fn (Agent $record): string => self::commissionColor($record->commission_tdev_renewal))
                        ->numeric(2)
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Gestión', [
                    TextColumn::make('status')
                        ->label('Estatus')
                        ->icon(fn (?string $state): Heroicon => self::statusIcon($state))
                        ->badge()
                        ->color(fn (?string $state): string => self::statusColor($state))
                        ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('created_by')
                        ->label('Creado por')
                        ->placeholder('—')
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('created_at')
                        ->label('Alta')
                        ->icon(Heroicon::OutlinedCalendar)
                        ->dateTime('d/m/Y H:i')
                        ->description(fn (Agent $record): string => $record->created_at->diffForHumans())
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('updated_at')
                        ->label('Actualizado')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->dateTime('d/m/Y H:i')
                        ->description(fn (Agent $record): string => $record->updated_at->diffForHumans())
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
            ])
            ->recordClasses(fn (Agent $record): array => self::recordRowClasses($record))
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'ACTIVO' => 'Activo',
                        'INACTIVO' => 'Inactivo',
                        'POR REVISION' => 'Por revisión',
                    ])
                    ->placeholder('Todos')
                    ->native(false),
                SelectFilter::make('agent_type_id')
                    ->label('Tipo de agente')
                    ->relationship('typeAgent', 'definition')
                    ->searchable()
                    ->preload()
                    ->native(false),
                Filter::make('created_at')
                    ->label('Fecha de alta')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Desde '.Carbon::parse($data['desde'])->format('d/m/Y');
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Hasta '.Carbon::parse($data['hasta'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filtros'),
            )
            ->recordUrl(fn (Agent $record): string => AgentResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver')
                        ->icon(Heroicon::OutlinedEye),
                    Action::make('Activate')
                        ->label('Activar')
                        ->action(function (Agent $record): void {
                            try {
                                if ($record->status == 'ACTIVO') {
                                    Notification::make()
                                        ->title('AGENTE YA ACTIVADO')
                                        ->body('El agente ya se encuentra activo.')
                                        ->color('danger')
                                        ->icon('heroicon-o-x-circle')
                                        ->iconColor('danger')
                                        ->send();

                                    return;
                                }

                                $record->status = 'ACTIVO';
                                $record->save();
                                LogController::log(Auth::user()->id, 'ACTIVACION DE AGENTE', 'AgentResource:Action:Activate()', $record->save());

                                $user = new User;
                                $user->name = $record->name;
                                $user->email = $record->email;
                                $user->password = Hash::make('12345678');
                                $user->is_agent = true;
                                $user->code_agency = $record->code_agency;
                                $user->code_agent = 'AGT-000'.$record->id;
                                $user->link_agent = env('APP_URL').'/at/lk/'.Crypt::encryptString($record->code_agent);
                                $user->agent_id = $record->id;
                                $user->status = 'ACTIVO';
                                $user->save();

                                $record->sendCartaBienvenida($record->id, $record->name, $record->email);

                                $phone = $record->phone;
                                $email = $record->email;
                                $nofitication = NotificationController::agent_activated($phone, $email, $record->agent_type_id == 2 ? config('parameters.PATH_AGENT') : config('parameters.PATH_SUBAGENT'));

                                if ($nofitication['success'] == true) {
                                    Notification::make()
                                        ->title('AGENTE ACTIVADO')
                                        ->body('Notificacion de activacion enviada con exito.')
                                        ->icon('heroicon-s-check-circle')
                                        ->iconColor('success')
                                        ->color('success')
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('AGENTE ACTIVADO')
                                        ->body('La notificacion de activacion no pudo ser enviada.')
                                        ->icon('heroicon-s-x-circle')
                                        ->iconColor('warning')
                                        ->color('warning')
                                        ->send();
                                }
                            } catch (\Throwable $th) {
                                Notification::make()
                                    ->title('EXCEPCION')
                                    ->body('Falla al realizar la activacion. Por favor comuniquese con el administrador.')
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('error')
                                    ->color('error')
                                    ->send();
                            }
                        })
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation(),
                    Action::make('Inactivate')
                        ->label('Inactivar')
                        ->action(fn (Agent $record) => $record->update(['status' => 'INACTIVO']))
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('danger')
                        ->requiresConfirmation(),
                    DeleteAction::make()
                        ->label('Eliminar')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger'),
                ])
                    ->label('Acciones')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // BulkAction::make('format_phone')
                    //     ->label('Formatear teléfonos')
                    //     ->icon(Heroicon::OutlinedPhone)
                    //     ->action(function (Collection $records): void {
                    //         foreach ($records as $record) {
                    //             $record->phone = UtilsController::normalizeVenezuelanPhone($record->phone);
                    //             $record->save();
                    //         }
                    //     })
                    //     ->requiresConfirmation()
                    //     ->color('azulOscuro'),
                    BulkAction::make('exportCsvController')
                        ->label('Exportar XLS')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('warning')
                        ->action(function (Collection $records) {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Selecciona al menos un agente')
                                    ->body('Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.')
                                    ->send();

                                return;
                            }

                            $ids = $records->pluck('id')->all();
                            $token = AgentExportCsvController::storeIdsAndGetToken($ids);

                            return redirect()->route('administration.agents.export-csv', ['token' => $token]);
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function hierarchyPrefix(Agent $record): string
    {
        $agency = Agency::query()
            ->where('code', $record->owner_code)
            ->with('typeAgency')
            ->first();

        if (filled($agency?->typeAgency?->definition)) {
            return $agency->typeAgency->definition.' - ';
        }

        return 'MASTER - ';
    }

    private static function commissionColor(mixed $value): string
    {
        return ((float) ($value ?? 0)) > 0 ? 'success' : 'warning';
    }

    private static function statusColor(?string $state): string
    {
        return match (strtoupper(trim((string) $state))) {
            'ACTIVO' => 'success',
            'INACTIVO' => 'danger',
            'POR REVISION' => 'warning',
            default => 'gray',
        };
    }

    private static function statusIcon(?string $state): Heroicon
    {
        return match (strtoupper(trim((string) $state))) {
            'ACTIVO' => Heroicon::OutlinedCheckCircle,
            'INACTIVO' => Heroicon::OutlinedXCircle,
            'POR REVISION' => Heroicon::OutlinedClock,
            default => Heroicon::OutlinedMinusCircle,
        };
    }

    private static function statusLabel(?string $state): string
    {
        return match (strtoupper(trim((string) $state))) {
            'ACTIVO' => 'Activo',
            'INACTIVO' => 'Inactivo',
            'POR REVISION' => 'Por revisión',
            default => (string) ($state ?? '—'),
        };
    }

    /**
     * @return list<string>
     */
    private static function recordRowClasses(Agent $record): array
    {
        return match (strtoupper(trim((string) $record->status))) {
            'INACTIVO' => ['bg-red-50/80 dark:bg-red-950/20 border-l-4 border-red-500'],
            'POR REVISION' => ['bg-amber-50/70 dark:bg-amber-950/20 border-l-4 border-amber-400'],
            'ACTIVO' => ['border-l-4 border-emerald-400/80'],
            default => ['border-l-4 border-transparent'],
        };
    }
}
