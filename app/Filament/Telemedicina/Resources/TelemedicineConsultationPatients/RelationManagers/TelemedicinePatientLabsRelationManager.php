<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\RelationManagers;

use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelemedicinePatientLabsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientLabs';

    protected static ?string $recordTitleAttribute = 'laboratory';

    protected static ?string $title = 'Laboratorios solicitados';

    protected static string|BackedEnum|null $icon = 'healthicons-f-biochemistry-laboratory';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Laboratorios solicitados')
            ->description(function (): string {
                $name = $this->ownerRecord->telemedicineDoctor?->full_name;

                return filled($name)
                    ? 'Estudios de laboratorio vinculados a esta consulta · Prescriptor: Dr(a). '.$name
                    : 'Estudios de laboratorio vinculados a esta consulta · sin médico prescriptor en la ficha.';
            })
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50])
            ->recordActionsColumnLabel('')
            ->extraAttributes([
                'class' => 'telemedicine-case-table-ios telemedicine-consultation-relation-table',
            ])
            ->emptyStateHeading('Sin laboratorios')
            ->emptyStateDescription('Aún no se registran solicitudes de laboratorio para esta consulta.')
            ->emptyStateIcon(Heroicon::OutlinedBeaker)
            ->columns([
                TextColumn::make('laboratory')
                    ->label('Laboratorio')
                    ->icon(Heroicon::OutlinedBeaker)
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
                    ->label('Registrar laboratorio')
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
