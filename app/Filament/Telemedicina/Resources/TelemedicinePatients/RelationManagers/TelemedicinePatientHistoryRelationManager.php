<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\RelationManagers;

use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicinePatient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelemedicinePatientHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientHistory';

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $title = 'Historia clínica';

    protected static string|BackedEnum|null $icon = 'healthicons-f-health-worker-form';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Historia del paciente')
            ->description(function (): string {
                $name = $this->ownerRecord instanceof TelemedicinePatient
                    ? $this->ownerRecord->full_name
                    : null;

                return filled($name)
                    ? 'Antecedentes y datos clínicos de '.$name.'. Use «Ver detalle» para la ficha completa.'
                    : 'Antecedentes y datos clínicos. Use «Ver detalle» para la ficha completa.';
            })
            ->emptyStateHeading('Sin historia registrada')
            ->emptyStateDescription('Aún no hay historia clínica para este paciente. Regístrela desde el botón superior o desde la lista de pacientes.')
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->striped()
            ->defaultSort('history_date', 'desc')
            ->recordActionsColumnLabel('')
            ->extraAttributes([
                'class' => 'telemedicine-case-table-ios telemedicine-patient-history-relation-table',
            ])
            ->columns([
                ColumnGroup::make('Datos del paciente', [
                    TextColumn::make('code')
                        ->label('Nro. de historia')
                        ->badge()
                        ->color('success')
                        ->weight(FontWeight::SemiBold)
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->searchable()
                        ->extraCellAttributes(['class' => 'py-3']),
                    TextColumn::make('code_patient')
                        ->label('Código de paciente')
                        ->badge()
                        ->color('gray')
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->searchable(),
                    TextColumn::make('history_date')
                        ->label('Fecha de historia')
                        ->date('d/m/Y')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->sortable()
                        ->searchable()
                        ->extraCellAttributes(['class' => 'py-3']),
                    TextColumn::make('weight')
                        ->label('Peso')
                        ->suffix(' kg')
                        ->placeholder('—'),
                    TextColumn::make('height')
                        ->label('Altura')
                        ->suffix(' cm')
                        ->placeholder('—'),
                ]),
                ColumnGroup::make('Antecedentes personales y familiares', [
                    IconColumn::make('cancer')->label('Cáncer')->boolean()->alignCenter(),
                    IconColumn::make('diabetes')->label('Diabetes')->boolean()->alignCenter(),
                    IconColumn::make('tension_alta')->label('Tensión alta')->boolean()->alignCenter(),
                    IconColumn::make('cardiacos')->label('Cardíacos')->boolean()->alignCenter(),
                    IconColumn::make('psiquiatricas')->label('Psiquiátricas')->boolean()->alignCenter(),
                    IconColumn::make('alteraciones_coagulacion')->label('Coagulación')->boolean()->alignCenter(),
                    IconColumn::make('trombosis_embooleanas')->label('Trombosis/embolia')->boolean()->alignCenter(),
                    IconColumn::make('tranfusiones_sanguineas')->label('Transfusiones')->boolean()->alignCenter(),
                    IconColumn::make('COVID19')->label('COVID-19')->boolean()->alignCenter(),
                ]),
                ColumnGroup::make('Antecedentes patológicos', [
                    IconColumn::make('hepatitis')->label('Hepatitis')->boolean()->alignCenter(),
                    IconColumn::make('VIH_SIDA')->label('VIH/SIDA')->boolean()->alignCenter(),
                    IconColumn::make('gastritis_ulceras')->label('Gastritis/úlceras')->boolean()->alignCenter(),
                    IconColumn::make('neurologia')->label('Neurología')->boolean()->alignCenter(),
                    IconColumn::make('ansiedad_angustia')->label('Ansiedad')->boolean()->alignCenter(),
                    IconColumn::make('tiroides')->label('Tiroides')->boolean()->alignCenter(),
                    IconColumn::make('lupus')->label('Lupus')->boolean()->alignCenter(),
                    IconColumn::make('enfermedad_autoimmune')->label('Autoinmune')->boolean()->alignCenter(),
                    IconColumn::make('diabetes_mellitus')->label('Diabetes mellitus')->boolean()->alignCenter(),
                    IconColumn::make('presion_arterial_alta')->label('HTA')->boolean()->alignCenter(),
                    IconColumn::make('tiene_cateter_venoso')->label('Catéter venoso')->boolean()->alignCenter(),
                    IconColumn::make('fracturas')->label('Fracturas')->boolean()->alignCenter(),
                    IconColumn::make('trombosis_venosa')->label('Trombosis venosa')->boolean()->alignCenter(),
                    IconColumn::make('embooleania_pulmonar')->label('Embolia pulmonar')->boolean()->alignCenter(),
                    IconColumn::make('varices_piernas')->label('Várices')->boolean()->alignCenter(),
                    IconColumn::make('insuficiencia_arterial')->label('Insuf. arterial')->boolean()->alignCenter(),
                    IconColumn::make('coagulacion_anormal')->label('Coag. anormal')->boolean()->alignCenter(),
                    IconColumn::make('moretones_frecuentes')->label('Moretones')->boolean()->alignCenter(),
                    IconColumn::make('sangrado_cirugias_previas')->label('Sangrado quirúrgico')->boolean()->alignCenter(),
                    IconColumn::make('sangrado_cepillado_dental')->label('Sangrado dental')->boolean()->alignCenter(),
                ]),
                ColumnGroup::make('Antecedentes no patológicos', [
                    IconColumn::make('alcohol')->label('Alcohol')->boolean()->alignCenter(),
                    IconColumn::make('drogas')->label('Drogas')->boolean()->alignCenter(),
                    IconColumn::make('vacunas_recientes')->label('Vacunas recientes')->boolean()->alignCenter(),
                ]),
                TextColumn::make('created_at')
                    ->label('Registro')
                    ->dateTime('d/m/Y H:i')
                    ->icon(Heroicon::OutlinedClock)
                    ->description(fn (TelemedicineHistoryPatient $record): string => $record->created_at?->diffForHumans() ?? '—')
                    ->sortable()
                    ->extraCellAttributes(['class' => 'py-3']),
            ])
            ->headerActions([
                Action::make('register_history')
                    ->label('Registrar historia')
                    ->icon(Heroicon::OutlinedPlus)
                    ->color('primary')
                    ->url(fn (): string => TelemedicineHistoryPatientResource::getUrl('create', [
                        'record' => $this->getOwnerRecord()->getKey(),
                    ]))
                    ->visible(fn (): bool => ! $this->getOwnerRecord()->telemedicinePatientHistory()->exists()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver detalle')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('primary')
                    ->link()
                    ->url(fn (TelemedicineHistoryPatient $record): string => TelemedicineHistoryPatientResource::getUrl('view', [
                        'record' => $record->getKey(),
                    ])),
                Action::make('edit_history')
                    ->label('Editar')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->link()
                    ->url(fn (TelemedicineHistoryPatient $record): string => TelemedicineHistoryPatientResource::getUrl('edit', [
                        'record' => $record->getKey(),
                    ])),
            ]);
    }
}
