<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\Tables;

use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicinePatient;
use App\Support\FilamentDateDisplay;
use App\Support\Telemedicine\TelemedicineCaseFilamentListQuery;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TelemedicinePatientsTable
{
    private static function affiliationStatusColor(?string $status): string
    {
        return match (mb_strtoupper((string) $status)) {
            'ACTIVO', 'ACTIVA', 'VIGENTE' => 'success',
            'SUSPENDIDO', 'SUSPENDIDA' => 'warning',
            'INACTIVO', 'INACTIVA', 'CANCELADO', 'CANCELADA' => 'danger',
            default => 'gray',
        };
    }

    private static function sexBadgeColor(?string $sex): string
    {
        return match (mb_strtoupper((string) $sex)) {
            'M', 'MASCULINO' => 'primary',
            'F', 'FEMENINO' => 'danger',
            default => 'gray',
        };
    }

    private static function activeCaseForPatient(TelemedicinePatient $record): ?TelemedicineCase
    {
        if ($record->relationLoaded('telemedicineCases')) {
            return $record->telemedicineCases->first();
        }

        $doctorId = Auth::user()?->doctor_id;
        if ($doctorId === null) {
            return null;
        }

        return TelemedicineCase::query()
            ->where('telemedicine_patient_id', $record->id)
            ->where('telemedicine_doctor_id', $doctorId)
            ->where('status', '!=', 'PACIENTE DE ALTA')
            ->latest('updated_at')
            ->first();
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Pacientes asignados')
            ->description('Pacientes con casos activos bajo su atención. Use las acciones para ver ficha, historia clínica o registrar una consulta.')
            ->defaultSort('full_name', 'asc')
            ->emptyStateHeading('Sin pacientes asignados')
            ->emptyStateDescription('No tiene pacientes con casos activos en este momento, o su usuario no tiene médico vinculado.')
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->recordActionsColumnLabel('')
            ->extraAttributes([
                'class' => 'telemedicine-case-table-ios telemedicine-patients-table',
            ])
            ->modifyQueryUsing(function (Builder $query): Builder {
                $doctorId = Auth::user()?->doctor_id;
                if ($doctorId === null) {
                    return $query->whereRaw('0 = 1');
                }

                return $query
                    ->with([
                        'country',
                        'city',
                        'state',
                        'plan',
                        'coverage',
                        'telemedicineCases' => function ($caseQuery) use ($doctorId): void {
                            $caseQuery
                                ->where('telemedicine_doctor_id', $doctorId)
                                ->where('status', '!=', 'PACIENTE DE ALTA')
                                ->latest('updated_at');
                        },
                    ])
                    ->whereHas('telemedicineCases', function (Builder $caseQuery) use ($doctorId): void {
                        $caseQuery
                            ->where('telemedicine_doctor_id', $doctorId)
                            ->where('status', '!=', 'PACIENTE DE ALTA');
                    });
            })
            ->columns([
                TextColumn::make('full_name')
                    ->label('Paciente')
                    ->icon(Heroicon::OutlinedUser)
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                    ->description(function (TelemedicinePatient $record): string {
                        $case = self::activeCaseForPatient($record);
                        $identification = filled($record->nro_identificacion) ? 'V-'.$record->nro_identificacion : '—';

                        return $case !== null
                            ? $identification.' · Caso '.$case->code
                            : $identification;
                    })
                    ->searchable()
                    ->wrap()
                    ->extraCellAttributes(['class' => 'py-3 min-w-[11rem] max-w-[16rem]']),
                TextColumn::make('patient_demographics')
                    ->label('Edad / sexo')
                    ->icon(Heroicon::OutlinedCalendar)
                    ->state(function (TelemedicinePatient $record): string {
                        $age = filled($record->age) ? $record->age.' años' : '—';
                        $sex = filled($record->sex) ? mb_strtoupper((string) $record->sex) : '—';

                        return $age.' · '.$sex;
                    })
                    ->badge()
                    ->color(fn (TelemedicinePatient $record): string => self::sexBadgeColor($record->sex))
                    ->extraCellAttributes(['class' => 'py-3 min-w-[8rem]']),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::OutlinedPhone)
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->searchable()
                    ->placeholder('—')
                    ->width('9rem')
                    ->extraCellAttributes([
                        'class' => 'py-3 min-w-[8rem] max-w-[9rem] whitespace-nowrap',
                    ]),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->searchable()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): ?string => filled($state)
                        ? Str::limit((string) $state, 22)
                        : null)
                    ->tooltip(fn (TelemedicinePatient $record): ?string => filled($record->email) && strlen((string) $record->email) > 22
                        ? (string) $record->email
                        : null)
                    ->lineClamp(1)
                    ->extraHeaderAttributes([
                        'class' => 'telemedicine-patient-email-column',
                    ]),
                TextColumn::make('location_summary')
                    ->label('Ubicación')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->wrap()
                    ->state(function (TelemedicinePatient $record): string {
                        $parts = array_filter([
                            $record->city?->definition,
                            $record->state?->definition,
                            $record->country?->name,
                        ]);

                        return $parts !== [] ? implode(', ', $parts) : '—';
                    })
                    ->tooltip(function (TelemedicinePatient $record): ?string {
                        $summary = implode(', ', array_filter([
                            $record->city?->definition,
                            $record->state?->definition,
                            $record->country?->name,
                        ]));

                        return strlen($summary) > 40 ? $summary : null;
                    })
                    ->toggleable()
                    ->extraHeaderAttributes([
                        'class' => 'telemedicine-patient-location-column min-w-[10rem] w-[12rem]',
                    ]),
                TextColumn::make('active_case_status')
                    ->label('Estado del caso')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->state(fn (TelemedicinePatient $record): string => self::activeCaseForPatient($record)?->status ?? '—')
                    ->badge()
                    ->color(function (TelemedicinePatient $record): string {
                        return match (self::activeCaseForPatient($record)?->status) {
                            'ASIGNADO' => 'primary',
                            'EN SEGUIMIENTO' => 'warning',
                            'ALTA MEDICA' => 'success',
                            default => 'gray',
                        };
                    })
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('nro_identificacion')
                    ->label('Identificación')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->prefix('V-')
                    ->badge()
                    ->color('success')
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('birth_date')
                    ->label('Nacimiento')
                    ->icon(Heroicon::OutlinedCake)
                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->badge()
                    ->color(fn (?string $state): string => self::sexBadgeColor($state))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraCellAttributes(['class' => 'py-3']),
                ColumnGroup::make('Domicilio y ubicación')
                    ->columns([
                        TextColumn::make('address')
                            ->label('Dirección')
                            ->icon(Heroicon::OutlinedHome)
                            ->wrap()
                            ->limit(40)
                            ->tooltip(fn (TelemedicinePatient $record): ?string => filled($record->address) ? $record->address : null)
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('country.name')
                            ->label('País')
                            ->placeholder('—')
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('city.definition')
                            ->label('Ciudad')
                            ->placeholder('—')
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('region')
                            ->label('Región')
                            ->placeholder('—')
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('state.definition')
                            ->label('Estado')
                            ->placeholder('—')
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                ColumnGroup::make('Afiliación')
                    ->columns([
                        TextColumn::make('plan.description')
                            ->label('Plan')
                            ->badge()
                            ->color('info')
                            ->searchable()
                            ->placeholder('—')
                            ->visible(fn (): bool => ! TelemedicineCaseFilamentListQuery::userIsInAtenmediTelemedicinaContext(Auth::user()))
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('coverage.price')
                            ->label('Cobertura')
                            ->badge()
                            ->color('success')
                            ->formatStateUsing(function ($state): ?string {
                                if ($state === null || $state === '') {
                                    return null;
                                }

                                return is_numeric($state)
                                    ? number_format((float) $state, 2, ',', '.')
                                    : (string) $state;
                            })
                            ->placeholder('—')
                            ->visible(fn (): bool => ! TelemedicineCaseFilamentListQuery::userIsInAtenmediTelemedicinaContext(Auth::user()))
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('code_affiliation')
                            ->label('Número de afiliación')
                            ->badge()
                            ->color('primary')
                            ->searchable()
                            ->placeholder('—')
                            ->visible(fn (): bool => ! TelemedicineCaseFilamentListQuery::userIsInAtenmediTelemedicinaContext(Auth::user()))
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('type_affiliation')
                            ->label('Tipo')
                            ->badge()
                            ->color('gray')
                            ->searchable()
                            ->placeholder('—')
                            ->visible(fn (): bool => ! TelemedicineCaseFilamentListQuery::userIsInAtenmediTelemedicinaContext(Auth::user()))
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('status_affiliation')
                            ->label('Estatus')
                            ->badge()
                            ->color(fn (?string $state): string => self::affiliationStatusColor($state))
                            ->searchable()
                            ->placeholder('—')
                            ->visible(fn (): bool => ! TelemedicineCaseFilamentListQuery::userIsInAtenmediTelemedicinaContext(Auth::user()))
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                TextColumn::make('created_at')
                    ->label('Registro')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (TelemedicinePatient $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraCellAttributes(['class' => 'py-3']),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label('Fecha de registro')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde'),
                        DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('telemedicine_patients.created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('telemedicine_patients.created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Registro desde '.Carbon::parse($data['desde'])->translatedFormat('d M Y');
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Registro hasta '.Carbon::parse($data['hasta'])->translatedFormat('d M Y');
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
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver ficha')
                        ->icon(Heroicon::OutlinedEye)
                        ->color('primary')
                        ->url(fn (TelemedicinePatient $record): string => TelemedicinePatientResource::getUrl('view', ['record' => $record])),
                    EditAction::make()
                        ->label('Editar')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->color('gray')
                        ->url(fn (TelemedicinePatient $record): string => TelemedicinePatientResource::getUrl('edit', ['record' => $record])),
                    Action::make('view_history')
                        ->label('Historia clínica')
                        ->icon('healthicons-f-cardiogram-e')
                        ->color('info')
                        ->url(fn (TelemedicinePatient $record): string => TelemedicineHistoryPatientResource::getUrl('create', ['record' => $record])),
                    Action::make('new_consultation')
                        ->label('Hacer consulta')
                        ->icon('healthicons-f-i-exam-qualification')
                        ->color('success')
                        ->action(function (TelemedicinePatient $record): mixed {
                            $case = self::activeCaseForPatient($record);

                            if ($case === null) {
                                Notification::make()
                                    ->title('No hay caso activo')
                                    ->body('No se encontró un caso activo asignado para este paciente.')
                                    ->warning()
                                    ->send();

                                return null;
                            }

                            $exitRecord = TelemedicineHistoryPatient::query()
                                ->where('telemedicine_patient_id', $record->id)
                                ->exists();

                            session()->forget(['case', 'patient', 'exit_record', 'action', 'status', 'consultation']);

                            session([
                                'case' => $case,
                                'patient' => $record,
                                'exit_record' => $exitRecord,
                            ]);

                            return redirect()->route(
                                'filament.telemedicina.resources.telemedicine-consultation-patients.create',
                                ['id' => $record->id],
                            );
                        }),
                ])
                    ->icon(Heroicon::OutlinedEllipsisHorizontalCircle)
                    ->tooltip('Acciones del paciente')
                    ->button()
                    ->label('Más'),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
