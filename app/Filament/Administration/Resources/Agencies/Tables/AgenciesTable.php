<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Agencies\Tables;

use App\Filament\Administration\Resources\Agencies\AgencyResource;
use App\Filament\Exports\AgencyExporter;
use App\Http\Controllers\LogController;
use App\Models\Agency;
use App\Models\AgencyType;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
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
use Illuminate\Support\Facades\Auth;

class AgenciesTable
{
    private const COLUMN_GROUP_HEADER_CLASS = '[&_th]:bg-gradient-to-r [&_th]:from-slate-100/95 [&_th]:via-slate-50/90 [&_th]:to-transparent dark:[&_th]:from-white/[0.08] dark:[&_th]:via-white/[0.04] dark:[&_th]:to-transparent [&_th]:font-semibold [&_th]:text-slate-800 dark:[&_th]:text-slate-100 [&_th]:border-b [&_th]:border-slate-200/80 dark:[&_th]:border-white/10';

    /** @return array<string, Tab> */
    public static function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todas')
                ->icon(Heroicon::OutlinedBuildingOffice2),
            'activo' => Tab::make('Activas')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'ACTIVO')),
            'inactivo' => Tab::make('Inactivas')
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
                $base = Agency::query()->with(['typeAgency', 'accountManager']);

                if (Auth::user()->is_accountManagers) {
                    return $base->where('ownerAccountManagers', Auth::user()->id);
                }

                return $base;
            })
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->heading('Agencias')
            ->description('Estructura comercial: jerarquía, contacto, comisiones y estatus. Las filas resaltan según el estado operativo.')
            ->striped()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->recordTitleAttribute('name_corporative')
            ->emptyStateHeading('Sin agencias')
            ->emptyStateDescription('No hay agencias registradas o no coinciden con los filtros aplicados.')
            ->emptyStateIcon(Heroicon::OutlinedBuildingOffice2)
            ->columns([
                ColumnGroup::make('Identificación', [
                    TextColumn::make('owner_code')
                        ->label('Pertenece a')
                        ->icon(Heroicon::OutlinedBuildingLibrary)
                        ->badge()
                        ->color('success')
                        ->searchable()
                        ->sortable()
                        ->placeholder('—')
                        ->wrap(),
                    TextColumn::make('code')
                        ->label('Código')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->weight('semibold')
                        ->badge()
                        ->color('success')
                        ->prefix(fn (Agency $record): string => self::agencyTypePrefix($record))
                        ->searchable()
                        ->sortable()
                        ->copyable()
                        ->copyMessage('Código copiado')
                        ->action(
                            ViewAction::make('viewFromCode')
                                ->url(fn (Agency $record): string => AgencyResource::getUrl('view', ['record' => $record])),
                        ),
                    TextColumn::make('typeAgency.definition')
                        ->label('Tipo')
                        ->badge()
                        ->color('azulOscuro')
                        ->searchable()
                        ->sortable()
                        ->placeholder('—'),
                    TextColumn::make('accountManager.full_name')
                        ->label('Account manager')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->badge()
                        ->color('warning')
                        ->searchable()
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('name_corporative')
                        ->label('Razón social')
                        ->icon(Heroicon::OutlinedBuildingOffice)
                        ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                        ->searchable()
                        ->sortable()
                        ->weight('medium')
                        ->limit(36)
                        ->tooltip(fn (Agency $record): ?string => filled($record->name_corporative)
                            ? mb_strtoupper($record->name_corporative)
                            : null)
                        ->wrap(),
                    TextColumn::make('rif')
                        ->label('RIF')
                        ->searchable()
                        ->copyable()
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('ci_responsable')
                        ->label('CI responsable')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
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
                        ->tooltip(fn (Agency $record): ?string => filled($record->address) ? mb_strtoupper($record->address) : null)
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
                ColumnGroup::make('Comisiones', [
                    TextColumn::make('commission_tdec')
                        ->label('TDEC')
                        ->suffix('%')
                        ->alignCenter()
                        ->badge()
                        ->color(fn (Agency $record): string => self::commissionColor($record->commission_tdec))
                        ->numeric(2)
                        ->sortable(),
                    TextColumn::make('commission_tdec_renewal')
                        ->label('TDEC renov.')
                        ->suffix('%')
                        ->alignCenter()
                        ->badge()
                        ->color(fn (Agency $record): string => self::commissionColor($record->commission_tdec_renewal))
                        ->numeric(2)
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('commission_tdev')
                        ->label('TDEV')
                        ->suffix('%')
                        ->alignCenter()
                        ->badge()
                        ->color(fn (Agency $record): string => self::commissionColor($record->commission_tdev))
                        ->numeric(2)
                        ->sortable(),
                    TextColumn::make('commission_tdev_renewal')
                        ->label('TDEV renov.')
                        ->suffix('%')
                        ->alignCenter()
                        ->badge()
                        ->color(fn (Agency $record): string => self::commissionColor($record->commission_tdev_renewal))
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
                        ->description(fn (Agency $record): string => $record->created_at->diffForHumans())
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('updated_at')
                        ->label('Actualizado')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->dateTime('d/m/Y H:i')
                        ->description(fn (Agency $record): string => $record->updated_at->diffForHumans())
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
                    ->extraHeaderAttributes(['class' => self::COLUMN_GROUP_HEADER_CLASS]),
            ])
            ->recordClasses(fn (Agency $record): array => self::recordRowClasses($record))
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
                SelectFilter::make('agency_type_id')
                    ->label('Tipo de agencia')
                    ->relationship('typeAgency', 'definition')
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
            ->recordUrl(fn (Agency $record): string => AgencyResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver')
                        ->icon(Heroicon::OutlinedEye),
                    EditAction::make()
                        ->label('Editar')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->color('warning'),
                    Action::make('Activate')
                        ->label('Activar')
                        ->action(function (Agency $record): void {
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

                                if (Agency::where('email', $record->email)->exists()) {
                                    Notification::make()
                                        ->title('AGENTE YA REGISTRADO')
                                        ->body('El correo electronico del agente ya se encuentra registrado.')
                                        ->color('danger')
                                        ->send();

                                    return;
                                }
                            } catch (\Throwable $th) {
                                LogController::log(Auth::user()->id, 'EXCEPCION', 'AgencyResource:Tables\Actions\Action::make(Activate)', $th->getMessage());
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
                        ->action(fn (Agency $record) => $record->update(['status' => 'INACTIVO']))
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('danger')
                        ->requiresConfirmation(),
                ])
                    ->label('Acciones')
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(AgencyExporter::class)
                        ->label('Exportar XLS')
                        ->color('warning')
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    private static function agencyTypePrefix(Agency $record): string
    {
        $definition = AgencyType::query()
            ->where('id', $record->agency_type_id)
            ->value('definition');

        return filled($definition) ? $definition.' - ' : '';
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
    private static function recordRowClasses(Agency $record): array
    {
        return match (strtoupper(trim((string) $record->status))) {
            'INACTIVO' => ['bg-red-50/80 dark:bg-red-950/20 border-l-4 border-red-500'],
            'POR REVISION' => ['bg-amber-50/70 dark:bg-amber-950/20 border-l-4 border-amber-400'],
            'ACTIVO' => ['border-l-4 border-emerald-400/80'],
            default => ['border-l-4 border-transparent'],
        };
    }
}
