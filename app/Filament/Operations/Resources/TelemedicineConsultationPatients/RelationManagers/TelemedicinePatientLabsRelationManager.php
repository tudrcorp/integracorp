<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineConsultationPatients\RelationManagers;

use BackedEnum;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelemedicinePatientLabsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientLabs';

    protected static ?string $recordTitleAttribute = 'laboratory';

    protected static ?string $title = 'Laboratorios solicitados';

    protected static string|BackedEnum|null $icon = 'healthicons-f-biochemistry-laboratory';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('telemedicine_consultation_patient_id')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('telemedicine_consultation_patient_id'),
            ]);
    }

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
            ->paginationPageOptions([10, 25, 50])
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
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar laboratorio'),
                AssociateAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
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
