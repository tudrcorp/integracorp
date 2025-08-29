<?php

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\Tables;

use Carbon\Carbon;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\TelemedicineCase;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use App\Models\TelemedicinePatient;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;

class TelemedicinePatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(
                TelemedicinePatient::join( 'telemedicine_cases', 'telemedicine_cases.telemedicine_patient_id', '=', 'telemedicine_patients.id')
                ->select('telemedicine_patients.*')
                ->where('telemedicine_cases.status', 'ASIGNADO') 
                ->where('telemedicine_cases.telemedicine_doctor_id', Auth::user()->doctor_id)
                )
            ->columns([
                TextColumn::make('full_name')
                    ->label('Paciente')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->label('Número de Identificación')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha de Nacimiento')
                    ->searchable(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Número de Teléfono')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable(),
                ColumnGroup::make('Domicilio y Ubicación')->columns([
                    TextColumn::make('address')
                        ->label('Dirección')
                        ->searchable(),
                    TextColumn::make('country.name')
                        ->label('País')
                        ->searchable(),
                    TextColumn::make('city.definition')
                        ->label('Ciudad')
                        ->searchable(),
                    TextColumn::make('region')
                        ->label('Región')
                        ->searchable(),
                    TextColumn::make('state.definition')
                        ->label('Estado'),

                ]),
                ColumnGroup::make('Informacion de la Afiliación')->columns([
                    TextColumn::make('plan.description')
                        ->label('Plan')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('coverage.price')
                        ->label('Cobertura')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('code_affiliation')
                        ->label('Codigo')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('type_affiliation')
                        ->label('Tipo')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('status_affiliation')
                        ->label('Estatus')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                ]),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Venta desde ' . Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Venta hasta ' . Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    // ...
                    Action::make('view_history')
                        ->label('Ver Historia')
                        ->icon('healthicons-f-cardiogram-e')
                        ->color('info')
                        ->url(fn (TelemedicinePatient $record): string => TelemedicineHistoryPatientResource::getUrl('create', ['record' => $record]),),
                    // ...
                    Action::make('new_consultation')
                        ->label('Hacer Consulta')
                        ->icon('healthicons-f-i-exam-qualification')
                        ->color('success'),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                ]),
            ]);
    }
}