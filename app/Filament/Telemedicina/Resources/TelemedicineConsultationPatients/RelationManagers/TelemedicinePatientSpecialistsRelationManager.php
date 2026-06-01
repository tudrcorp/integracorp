<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\RelationManagers;

use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelemedicinePatientSpecialistsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientSpecialists';

    protected static ?string $recordTitleAttribute = 'specialty';

    protected static ?string $title = 'Consultas con especialistas';

    protected static string|BackedEnum|null $icon = 'healthicons-f-doctor-male';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Consultas con especialistas')
            ->description(function (): string {
                $name = $this->ownerRecord->telemedicineDoctor?->full_name;

                return filled($name)
                    ? 'Derivaciones o interconsultas registradas · Prescriptor: Dr(a). '.$name
                    : 'Derivaciones o interconsultas registradas · sin médico prescriptor en la ficha.';
            })
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50])
            ->recordActionsColumnLabel('')
            ->extraAttributes([
                'class' => 'telemedicine-case-table-ios telemedicine-consultation-relation-table',
            ])
            ->emptyStateHeading('Sin especialistas')
            ->emptyStateDescription('No hay especialidades solicitadas para esta consulta.')
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->columns([
                TextColumn::make('specialty')
                    ->label('Especialidad')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->wrap()
                    ->searchable()
                    ->sortable()
                    ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null)
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('type')
                    ->label('Cobertura')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::coverageLabel($state))
                    ->color(fn (?string $state): string => self::coverageIsPositive($state) ? 'success' : 'danger')
                    ->icon(fn (?string $state): Heroicon => self::coverageIsPositive($state)
                        ? Heroicon::OutlinedShieldCheck
                        : Heroicon::OutlinedXCircle)
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('created_at')
                    ->label('Fecha de solicitud')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->extraCellAttributes(['class' => 'py-3']),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar especialidad')
                    ->icon(Heroicon::OutlinedPlus),
            ]);
    }

    private static function coverageIsPositive(?string $type): bool
    {
        return strtoupper(trim((string) $type)) === 'CUBIERTO';
    }

    private static function coverageLabel(?string $type): string
    {
        return self::coverageIsPositive($type) ? 'Cubierto' : 'No cubierto';
    }
}
