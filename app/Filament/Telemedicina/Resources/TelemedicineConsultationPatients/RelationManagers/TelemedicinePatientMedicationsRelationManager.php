<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\RelationManagers;

use App\Models\TelemedicinePatientMedications;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TelemedicinePatientMedicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientMedications';

    protected static ?string $recordTitleAttribute = 'medicine';

    protected static ?string $title = 'Medicamentos e indicaciones';

    protected static string|BackedEnum|null $icon = 'healthicons-f-blister-pills-oval-x14';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Medicamentos e indicaciones')
            ->description(function (): string {
                $name = $this->ownerRecord->telemedicineDoctor?->full_name;

                return filled($name)
                    ? 'Prescripciones de esta consulta · Prescriptor: Dr(a). '.$name
                    : 'Prescripciones de esta consulta · sin médico prescriptor en la ficha.';
            })
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('operationInventory'))
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50])
            ->recordActionsColumnLabel('')
            ->extraAttributes([
                'class' => 'telemedicine-case-table-ios telemedicine-consultation-relation-table',
            ])
            ->emptyStateHeading('Sin medicamentos')
            ->emptyStateDescription('No hay fármacos ni indicaciones cargadas para esta consulta.')
            ->emptyStateIcon(Heroicon::OutlinedCube)
            ->columns([
                TextColumn::make('medicine')
                    ->label('Medicamento')
                    ->icon(Heroicon::OutlinedCube)
                    ->wrap()
                    ->searchable()
                    ->sortable()
                    ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null)
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('indications')
                    ->label('Indicaciones')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->wrap()
                    ->searchable()
                    ->placeholder('—')
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('coverage_display')
                    ->label('Cobertura')
                    ->badge()
                    ->getStateUsing(fn (TelemedicinePatientMedications $record): string => TelemedicineMedicationCoverage::isCovered($record)
                        ? 'Cubierto'
                        : 'No cubierto')
                    ->color(fn (TelemedicinePatientMedications $record): string => TelemedicineMedicationCoverage::isCovered($record)
                        ? 'success'
                        : 'danger')
                    ->icon(fn (TelemedicinePatientMedications $record): Heroicon => TelemedicineMedicationCoverage::isCovered($record)
                        ? Heroicon::OutlinedShieldCheck
                        : Heroicon::OutlinedXCircle)
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('created_at')
                    ->label('Fecha de registro')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->extraCellAttributes(['class' => 'py-3']),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar medicamento')
                    ->icon(Heroicon::OutlinedPlus),
            ]);
    }
}
