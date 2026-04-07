<?php

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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelemedicinePatientLabsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientLabs';

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
            ->description(fn (RelationManager $livewire): string => 'Indicador por el Dr(a): '.$livewire->ownerRecord->telemedicineDoctor->full_name)
            ->columns([
                // TextColumn::make('telemedicine_consultation_patient_id')
                //     ->searchable(),
                TextColumn::make('laboratory')
                    ->label('Laboratorio')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn ($record): string => $record->type == 'CUBIERTO' ? 'success' : 'danger')
                    ->icon(fn ($record): string => $record->type == 'CUBIERTO' ? 'heroicon-m-check' : 'heroicon-o-x-mark')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Solicitud')
                    ->badge()
                    ->date('d/m/Y')
                    ->color('primary')
                    ->icon('heroicon-s-calendar')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
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
}
