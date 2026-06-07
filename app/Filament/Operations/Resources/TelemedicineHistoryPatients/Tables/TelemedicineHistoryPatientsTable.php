<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineHistoryPatients\Tables;

use App\Filament\Operations\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Models\TelemedicineHistoryPatient;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TelemedicineHistoryPatientsTable
{
    /** @var array<string, string> */
    private const PATHOLOGY_LABELS = [
        'cancer' => 'Cáncer',
        'diabetes' => 'Diabetes',
        'tension_alta' => 'HTA',
        'asma' => 'Asma',
        'cardiacos' => 'Cardíacos',
        'gastritis_ulceras' => 'Gastritis/úlceras',
        'enfermedad_autoimmune' => 'Autoinmune',
        'trombosis_embooleanas' => 'Trombosis',
        'fracturas' => 'Fracturas',
        'tranfusiones_sanguineas' => 'Transfusiones',
        'tiroides' => 'Tiroides',
        'hepatitis' => 'Hepatitis',
        'moretones_frecuentes' => 'Moretones',
        'psiquiatricas' => 'Psiquiátricas',
        'covid' => 'COVID-19',
        'alteraciones_coagulacion' => 'Coagulación',
        'vih' => 'VIH',
        'neurologia' => 'Neurología',
        'ansiedad_angustia' => 'Ansiedad',
        'lupus' => 'Lupus',
        'diabetes_mellitus' => 'Diabetes mellitus',
        'presion_arterial_alta' => 'PA alta',
        'tiene_cateter_venoso' => 'Catéter',
        'trombosis_venosa' => 'TVP',
        'embooleania_pulmonar' => 'TEP',
        'varices_piernas' => 'Várices',
        'insuficiencia_arterial' => 'Insuf. arterial',
        'coagulacion_anormal' => 'Coag. anormal',
        'alcohol' => 'Alcohol',
        'drogas' => 'Drogas',
        'tabaco' => 'Tabaco',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('Historias clínicas')
            ->description('Expedientes de antecedentes por paciente. El código y el paciente abren el detalle; use filtros para acotar por médico, fechas o antecedentes.')
            ->recordUrl(fn (TelemedicineHistoryPatient $record): string => TelemedicineHistoryPatientResource::getUrl('view', ['record' => $record]))
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->with([
                    'telemedicineDoctor:id,full_name',
                    'telemedicinePatient:id,full_name,nro_identificacion,managed_by,sex,age,supplier_id',
                    'telemedicinePatient.supplier:id,name',
                    'supplier:id,name',
                ]);

                if (in_array('ATENMEDI', Auth::user()?->departament ?? [], true)) {
                    $query->whereHas('telemedicinePatient', fn (Builder $q): Builder => $q->where('managed_by', 'ATENMEDI'));
                }

                OperationsSupplierScope::applyToQuery($query);

                return $query;
            })
            ->columns([
                TextColumn::make('code')
                    ->label('Historia')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (?string $state): string => $state ? mb_strtoupper($state) : '—')
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->copyMessageDuration(1500)
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->tooltip('Abrir detalle de la historia clínica'),
                TextColumn::make('telemedicinePatient.managed_by')
                    ->label('Gestión')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? mb_strtoupper($state) : '—')
                    ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                        'ATENMEDI' => 'success',
                        'TDG' => 'info',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('telemedicinePatient.full_name')
                    ->label('Paciente')
                    ->icon(Heroicon::OutlinedUser)
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state ? mb_strtoupper($state) : '—')
                    ->description(fn (TelemedicineHistoryPatient $record): string => collect([
                        filled($record->telemedicinePatient?->nro_identificacion)
                            ? 'ID: '.$record->telemedicinePatient->nro_identificacion
                            : null,
                        filled($record->telemedicinePatient?->sex)
                            ? mb_strtoupper((string) $record->telemedicinePatient->sex)
                            : null,
                        filled($record->telemedicinePatient?->age)
                            ? $record->telemedicinePatient->age.' años'
                            : null,
                    ])->filter()->implode(' · ') ?: 'Sin datos adicionales')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->wrap(),
                TextColumn::make('telemedicineDoctor.full_name')
                    ->label('Médico')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->limit(32)
                    ->tooltip(fn (TelemedicineHistoryPatient $record): ?string => $record->telemedicineDoctor?->full_name),
                TextColumn::make('history_date')
                    ->label('Fecha clínica')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->placeholder('—')
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }

                        try {
                            return Carbon::parse($state)->translatedFormat('d/m/Y');
                        } catch (\Throwable) {
                            return $state;
                        }
                    })
                    ->description(function (TelemedicineHistoryPatient $record): ?string {
                        if (blank($record->history_date)) {
                            return null;
                        }

                        try {
                            return Carbon::parse($record->history_date)->diffForHumans();
                        } catch (\Throwable) {
                            return null;
                        }
                    })
                    ->sortable(),
                TextColumn::make('pathology_summary')
                    ->label('Antecedentes')
                    ->icon(Heroicon::OutlinedHeart)
                    ->state(fn (TelemedicineHistoryPatient $record): string => self::pathologySummaryLabel($record))
                    ->description(fn (TelemedicineHistoryPatient $record): ?string => self::pathologySummaryDescription($record))
                    ->badge()
                    ->color(fn (TelemedicineHistoryPatient $record): string => self::positivePathologyCount($record) > 0 ? 'warning' : 'success')
                    ->tooltip(fn (TelemedicineHistoryPatient $record): ?string => self::pathologySummaryTooltip($record))
                    ->toggleable(),
                TextColumn::make('allergies_summary')
                    ->label('Alergias')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->state(fn (TelemedicineHistoryPatient $record): string => self::allergiesSummaryLabel($record))
                    ->badge()
                    ->color(fn (TelemedicineHistoryPatient $record): string => self::hasAllergies($record) ? 'danger' : 'gray')
                    ->tooltip(fn (TelemedicineHistoryPatient $record): ?string => self::allergiesTooltip($record))
                    ->toggleable(),
                TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->placeholder('—')
                    ->limit(24)
                    ->tooltip(fn (TelemedicineHistoryPatient $record): ?string => $record->supplier?->name ?? $record->telemedicinePatient?->supplier?->name)
                    ->toggleable()
                    ->visible(fn (): bool => OperationsSupplierScope::currentSupplierId() === null),
                ColumnGroup::make('Antecedentes patológicos')
                    ->columns([
                        IconColumn::make('cancer')
                            ->label('Cáncer')
                            ->boolean()
                            ->trueIcon(Heroicon::OutlinedCheckCircle)
                            ->falseIcon(Heroicon::OutlinedMinusCircle)
                            ->toggleable(isToggledHiddenByDefault: true),
                        IconColumn::make('diabetes')
                            ->label('Diabetes')
                            ->boolean()
                            ->trueIcon(Heroicon::OutlinedCheckCircle)
                            ->falseIcon(Heroicon::OutlinedMinusCircle)
                            ->toggleable(isToggledHiddenByDefault: true),
                        IconColumn::make('tension_alta')
                            ->label('HTA')
                            ->boolean()
                            ->trueIcon(Heroicon::OutlinedCheckCircle)
                            ->falseIcon(Heroicon::OutlinedMinusCircle)
                            ->toggleable(isToggledHiddenByDefault: true),
                        IconColumn::make('cardiacos')
                            ->label('Cardíacos')
                            ->boolean()
                            ->trueIcon(Heroicon::OutlinedCheckCircle)
                            ->falseIcon(Heroicon::OutlinedMinusCircle)
                            ->toggleable(isToggledHiddenByDefault: true),
                        IconColumn::make('covid')
                            ->label('COVID-19')
                            ->boolean()
                            ->trueIcon(Heroicon::OutlinedCheckCircle)
                            ->falseIcon(Heroicon::OutlinedMinusCircle)
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                ColumnGroup::make('Auditoría')
                    ->columns([
                        TextColumn::make('created_by')
                            ->label('Registrado por')
                            ->icon(Heroicon::OutlinedPencilSquare)
                            ->placeholder('—')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('created_at')
                            ->label('Alta en sistema')
                            ->icon(Heroicon::OutlinedClock)
                            ->dateTime('d/m/Y H:i')
                            ->sortable()
                            ->description(fn (TelemedicineHistoryPatient $record): string => $record->created_at?->diffForHumans() ?? '')
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('updated_by')
                            ->label('Última edición por')
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->placeholder('—')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('updated_at')
                            ->label('Última actualización')
                            ->icon(Heroicon::OutlinedCalendar)
                            ->dateTime('d/m/Y H:i')
                            ->sortable()
                            ->description(fn (TelemedicineHistoryPatient $record): string => $record->updated_at?->diffForHumans() ?? '')
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
            ])
            ->filters([
                SelectFilter::make('telemedicine_doctor_id')
                    ->label('Médico')
                    ->relationship('telemedicineDoctor', 'full_name')
                    ->searchable()
                    ->preload()
                    ->native(false),
                SelectFilter::make('managed_by')
                    ->label('Gestión del paciente')
                    ->options([
                        'ATENMEDI' => 'ATENMEDI',
                        'TDG' => 'TDG',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $q): Builder => $q->whereHas(
                            'telemedicinePatient',
                            fn (Builder $patientQuery): Builder => $patientQuery->where('managed_by', $data['value']),
                        ),
                    ))
                    ->native(false),
                TernaryFilter::make('with_pathologies')
                    ->label('Antecedentes activos')
                    ->placeholder('Todas las historias')
                    ->trueLabel('Con antecedentes')
                    ->falseLabel('Sin antecedentes registrados')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(function (Builder $pathologyQuery): void {
                            foreach (array_keys(self::PATHOLOGY_LABELS) as $field) {
                                $pathologyQuery->orWhere($field, true)
                                    ->orWhere($field, 1)
                                    ->orWhere($field, '1')
                                    ->orWhere($field, 'si')
                                    ->orWhere($field, 'Sí');
                            }
                        }),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $pathologyQuery): void {
                            foreach (array_keys(self::PATHOLOGY_LABELS) as $field) {
                                $pathologyQuery->where(function (Builder $fieldQuery) use ($field): void {
                                    $fieldQuery->whereNull($field)
                                        ->orWhere($field, false)
                                        ->orWhere($field, 0)
                                        ->orWhere($field, '0')
                                        ->orWhere($field, 'no')
                                        ->orWhere($field, 'No');
                                });
                            }
                        }),
                    ),
                Filter::make('history_date')
                    ->label('Fecha clínica')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('history_date', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('history_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (! empty($data['desde'])) {
                            $indicators['desde'] = 'Clínica desde '.Carbon::parse($data['desde'])->translatedFormat('d/m/Y');
                        }

                        if (! empty($data['hasta'])) {
                            $indicators['hasta'] = 'Clínica hasta '.Carbon::parse($data['hasta'])->translatedFormat('d/m/Y');
                        }

                        return $indicators;
                    }),
                Filter::make('created_at')
                    ->label('Alta en sistema')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
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

                        if (! empty($data['desde'])) {
                            $indicators['desde'] = 'Alta desde '.Carbon::parse($data['desde'])->translatedFormat('d/m/Y');
                        }

                        if (! empty($data['hasta'])) {
                            $indicators['hasta'] = 'Alta hasta '.Carbon::parse($data['hasta'])->translatedFormat('d/m/Y');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filtros')
                    ->icon(Heroicon::OutlinedFunnel),
            )
            ->deferFilters(false)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver detalle')
                        ->icon(Heroicon::OutlinedEye)
                        ->color('primary'),
                    EditAction::make()
                        ->label('Editar')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->color('warning'),
                ])
                    ->icon(Heroicon::OutlinedEllipsisHorizontalCircle)
                    ->tooltip('Acciones')
                    ->button()
                    ->label('Acciones'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar historias')
                        ->color('danger')
                        ->icon(Heroicon::OutlinedTrash)
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar historias clínicas')
                        ->modalDescription('¿Confirma la eliminación de las historias seleccionadas? Esta acción no se puede deshacer.')
                        ->modalIcon(Heroicon::OutlinedTrash)
                        ->modalSubmitActionLabel('Sí, eliminar')
                        ->successNotificationTitle('Historias eliminadas')
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                $patientName = $record->telemedicinePatient?->full_name ?? 'N/D';
                                Log::info('OPERACIONES: El usuario '.Auth::user()->name.' eliminó la historia clínica del paciente: '.$patientName);
                                $record->delete();
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('No hay historias clínicas')
            ->emptyStateDescription('Cuando se registre una historia, aparecerá aquí. Puede crear una nueva con el botón superior.')
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    /**
     * @return array<int, string>
     */
    private static function positivePathologyLabels(TelemedicineHistoryPatient $record): array
    {
        $labels = [];

        foreach (self::PATHOLOGY_LABELS as $field => $label) {
            if (self::isTruthy($record->{$field} ?? null)) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    private static function positivePathologyCount(TelemedicineHistoryPatient $record): int
    {
        return count(self::positivePathologyLabels($record));
    }

    private static function pathologySummaryLabel(TelemedicineHistoryPatient $record): string
    {
        $count = self::positivePathologyCount($record);

        if ($count === 0) {
            return 'Sin antecedentes';
        }

        return $count.' activo'.($count === 1 ? '' : 's');
    }

    private static function pathologySummaryDescription(TelemedicineHistoryPatient $record): ?string
    {
        $labels = self::positivePathologyLabels($record);

        if ($labels === []) {
            return 'Sin marcadores patológicos';
        }

        $preview = array_slice($labels, 0, 3);

        if (count($labels) > 3) {
            return implode(', ', $preview).' +'.(count($labels) - 3);
        }

        return implode(', ', $preview);
    }

    private static function pathologySummaryTooltip(TelemedicineHistoryPatient $record): ?string
    {
        $labels = self::positivePathologyLabels($record);

        if ($labels === []) {
            return null;
        }

        return implode(' · ', $labels);
    }

    private static function hasAllergies(TelemedicineHistoryPatient $record): bool
    {
        $allergies = $record->allergies;

        if (! is_array($allergies)) {
            return filled($record->observations_allergies);
        }

        return $allergies !== [] || filled($record->observations_allergies);
    }

    private static function allergiesSummaryLabel(TelemedicineHistoryPatient $record): string
    {
        if (! self::hasAllergies($record)) {
            return 'Ninguna';
        }

        $allergies = is_array($record->allergies) ? $record->allergies : [];
        $count = count($allergies);

        if ($count === 0) {
            return 'Con observaciones';
        }

        return $count.' registrada'.($count === 1 ? '' : 's');
    }

    private static function allergiesTooltip(TelemedicineHistoryPatient $record): ?string
    {
        if (! self::hasAllergies($record)) {
            return null;
        }

        $parts = [];

        if (is_array($record->allergies) && $record->allergies !== []) {
            $parts[] = implode(', ', array_map(
                fn (mixed $item): string => is_scalar($item) ? (string) $item : json_encode($item),
                $record->allergies,
            ));
        }

        if (filled($record->observations_allergies)) {
            $parts[] = (string) $record->observations_allergies;
        }

        return implode(' — ', $parts) ?: null;
    }

    private static function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (! is_string($value)) {
            return false;
        }

        return in_array(mb_strtolower(trim($value)), ['1', 'true', 'si', 'sí', 'yes'], true);
    }
}
