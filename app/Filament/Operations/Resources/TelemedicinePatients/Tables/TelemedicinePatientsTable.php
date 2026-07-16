<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Tables;

use App\Filament\Operations\Resources\TelemedicinePatients\Actions\AssignDoctorAction;
use App\Models\TelemedicinePatient;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\SecurityAudit;
use Carbon\Carbon;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TelemedicinePatientsTable
{
    public static function configure(Table $table): Table
    {
        // dd(Auth::user()?->departament ?? []);
        return $table
            ->heading('Listado de pacientes')
            ->description('Pacientes afiliados y externos. Use columnas ocultas para ver domicilio y datos de afiliación.')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->with([
                    'businessUnit',
                    'businessLine',
                    'plan',
                    'coverage',
                    'country',
                    'city',
                    'state',
                    'supplier',
                    'createdBy',
                ]);

                if (in_array('ATENMEDI', Auth::user()?->departament ?? [], true)) {
                    $query->where('managed_by', 'ATENMEDI');
                }

                OperationsSupplierScope::applyToQuery($query);

                return $query;
            })
            ->columns([
                TextColumn::make('full_name')
                    ->label('Paciente')
                    ->icon('heroicon-o-user')
                    ->iconColor('gray')
                    ->formatStateUsing(fn (?string $state): string => $state ? mb_strtoupper($state) : '—')
                    ->searchable()
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (TelemedicinePatient $record): ?string => self::patientIdentificationDescription($record))
                    ->extraCellAttributes([
                        'class' => 'fi-telemedicine-patient-name-cell align-top min-w-[12rem]',
                    ])
                    ->tooltip(fn (TelemedicinePatient $record): ?string => filled($record->full_name)
                        ? $record->full_name.($record->nro_identificacion ? ' · '.$record->nro_identificacion : '')
                        : null),
                TextColumn::make('nro_identificacion')
                    ->label('Identificación')
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon('heroicon-m-phone')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Copiado')
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon('heroicon-m-envelope')
                    ->searchable()
                    ->limit(28)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->copyable()
                    ->copyMessage('Copiado')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('businessUnit.definition')
                    ->label('Unidad de negocio')
                    ->badge()
                    ->color(fn (?string $state): string => self::badgeColorFromString($state, [
                        'primary', 'info', 'success', 'warning',
                    ]))
                    ->searchable()
                    ->toggleable()
                    ->visible(fn (): bool => OperationsSupplierScope::authenticatedUserIsTdgAnalyst()),
                TextColumn::make('businessLine.definition')
                    ->label('Línea de servicio')
                    ->badge()
                    ->color(fn (?string $state): string => self::badgeColorFromString($state, [
                        'success', 'warning', 'primary', 'danger',
                    ]))
                    ->searchable()
                    ->toggleable()
                    ->visible(fn (): bool => OperationsSupplierScope::authenticatedUserIsTdgAnalyst()),
                TextColumn::make('type_affiliation')
                    ->label('Tipo de afiliación')
                    ->badge()
                    ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                        'TITULAR', 'TITULAR ' => 'success',
                        'BENEFICIARIO', 'DEPENDIENTE' => 'info',
                        'EXTERNO', 'PARTICULAR' => 'warning',
                        default => self::badgeColorFromString($state, ['primary', 'gray', 'info']),
                    })
                    ->searchable()
                    ->toggleable()
                    ->visible(fn (): bool => OperationsSupplierScope::authenticatedUserIsTdgAnalyst()),
                TextColumn::make('name_corporate')
                    ->label('Corporativo')
                    ->limit(24)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('birth_date')
                    ->label('Nacimiento')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ?: '—')
                    ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                        'M', 'MASCULINO' => 'primary',
                        'F', 'FEMENINO' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                ColumnGroup::make('Domicilio y ubicación')
                    ->columns([
                        TextColumn::make('address')
                            ->label('Dirección')
                            ->limit(36)
                            ->tooltip(fn (?string $state): ?string => $state)
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('country.name')
                            ->label('País')
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('city.definition')
                            ->label('Ciudad')
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('region')
                            ->label('Región')
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('state.definition')
                            ->label('Estado')
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                ColumnGroup::make('Afiliación')
                    ->columns([
                        TextColumn::make('plan.description')
                            ->label('Plan')
                            ->badge()
                            ->color(fn (?string $state): string => self::badgeColorFromString($state, [
                                'success', 'info', 'primary', 'warning',
                            ]))
                            ->searchable()
                            ->limit(20)
                            ->tooltip(fn (?string $state): ?string => $state)
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('coverage.price')
                            ->label('Cobertura')
                            ->badge()
                            ->color('warning')
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('code_affiliation')
                            ->label('Código')
                            ->badge()
                            ->color('info')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('status_affiliation')
                            ->label('Estatus')
                            ->badge()
                            ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                                'ACTIVO', 'ACTIVA', 'VIGENTE' => 'success',
                                'INACTIVO', 'INACTIVA', 'SUSPENDIDO', 'BAJA' => 'danger',
                                'PENDIENTE', 'EN PROCESO' => 'warning',
                                default => 'primary',
                            })
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                TextColumn::make('created_by')
                    ->label('Asociado por')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Fecha de asociación')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (TelemedicinePatient $record): string => $record->created_at->diffForHumans())
                    ->sortable()
                    ->icon('heroicon-m-calendar'),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label('Fecha de asociación')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Desde '.Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Hasta '.Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver Detalle'),
                    EditAction::make()
                        ->label('Editar')
                        ->hidden(fn (TelemedicinePatient $record) => in_array('ATENMEDI', Auth::user()->departament)),
                    AssignDoctorAction::make(),

                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar Paciente')
                        ->color('danger')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Paciente')
                        ->modalDescription('¿Está seguro de eliminar el paciente? Esta acción elimina la asociación del paciente. Para revertir deberá asociar nuevamente al afiliado.')
                        ->modalIcon('heroicon-o-trash')
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                Log::info('OPERACIONES: El usuario '.Auth::user()->name.' elimino el paciente: '.$record->full_name);
                                SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_PATIENT_DELETED', 'operations.telemedicine-patients.bulk-delete', [
                                    'telemedicine_patient_id' => $record->id,
                                    'patient_name' => $record->full_name,
                                ]);
                                $record->delete();
                            }
                        }),
                ]),
            ])
            ->striped();
    }

    private static function patientIdentificationDescription(TelemedicinePatient $record): ?string
    {
        $lines = array_values(array_filter([
            self::patientContactSummaryLine($record),
            self::patientSupplierSummaryLine($record),
        ]));

        if ($lines === []) {
            return null;
        }

        return implode("\n", $lines);
    }

    private static function patientContactSummaryLine(TelemedicinePatient $record): ?string
    {
        $parts = array_values(array_filter([
            filled($record->nro_identificacion) ? 'C.I. '.$record->nro_identificacion : null,
            filled($record->phone) ? $record->phone : null,
        ]));

        if ($parts === []) {
            return null;
        }

        return implode(' · ', $parts);
    }

    private static function patientSupplierSummaryLine(TelemedicinePatient $record): ?string
    {
        if (OperationsSupplierScope::currentSupplierId() !== null) {
            return null;
        }

        if (! filled($record->supplier_id)) {
            return 'Proveedor: TUDRGROUP';
        }

        $supplierName = trim((string) ($record->supplier?->name ?? ''));
        $managedBy = trim((string) ($record->managed_by ?? ''));
        $line = 'Proveedor: #'.(int) $record->supplier_id;

        if ($supplierName !== '') {
            $line .= ' · '.$supplierName;
        }

        if ($managedBy !== '') {
            $line .= ' ('.mb_strtoupper($managedBy).')';
        }

        return $line;
    }

    /**
     * Asigna un color de badge estable según el texto (misma cadena = mismo color).
     *
     * @param  array<int, string>  $palette
     */
    private static function badgeColorFromString(?string $state, array $palette): string
    {
        if ($state === null || $state === '') {
            return 'gray';
        }

        $index = crc32(mb_strtolower($state)) % count($palette);

        return $palette[$index];
    }
}
