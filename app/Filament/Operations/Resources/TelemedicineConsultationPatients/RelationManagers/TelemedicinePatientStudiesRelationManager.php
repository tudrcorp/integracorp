<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineConsultationPatients\RelationManagers;

use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelemedicinePatientStudiesRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientStudies';

    protected static ?string $recordTitleAttribute = 'study';

    protected static ?string $title = 'Imagenología';

    protected static string|BackedEnum|null $icon = 'healthicons-f-desktop-app';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Estudios de imagenología')
            ->description(function (): string {
                $name = $this->ownerRecord->telemedicineDoctor?->full_name;

                return filled($name)
                    ? 'Imagenología u otros estudios solicitados · Prescriptor: Dr(a). '.$name
                    : 'Imagenología u otros estudios solicitados · sin médico prescriptor en la ficha.';
            })
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->emptyStateHeading('Sin estudios')
            ->emptyStateDescription('No hay estudios de imagen u otros registros para esta consulta.')
            ->emptyStateIcon(Heroicon::OutlinedPhoto)
            ->columns([
                TextColumn::make('study')
                    ->label('Estudio')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->wrap()
                    ->searchable()
                    ->sortable()
                    ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null),
                TextColumn::make('type')
                    ->label('Cobertura')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::coverageLabel($state))
                    ->color(fn (?string $state): string => self::coverageIsPositive($state) ? 'success' : 'danger')
                    ->icon(fn (?string $state): Heroicon => self::coverageIsPositive($state)
                        ? Heroicon::OutlinedShieldCheck
                        : Heroicon::OutlinedXCircle)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de solicitud')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar estudio'),
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
