<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use App\Models\PathologicalHistory;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Models\FamilyHistory;

class FamilyHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'familyHistories';

    protected static ?string $title = 'Histórico Antecedentes Familiares';

    protected static string|BackedEnum|null $icon = 'heroicon-c-bars-arrow-down';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('observations')
                    ->autosize()
                    ->label('Antecedente')
                    ->required(),
                Hidden::make('created_by')->default(Auth::user()->name),
            ]);
    }


    public function table(Table $table): Table
    {
        return $table
            ->heading('Antecedentes Familiares')
            ->description('Ordenados de forma cronológica desde el mas reciente hasta el mas antiguo.')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    // ->description(fn (TelemedicineConsultationPatient $record): string => $record->created_at->diffForHumans())
                    ->description(fn(FamilyHistory $record): string => $record->updated_at->diffForHumans())
                    ->color('primary')
                    ->icon('heroicon-s-calendar')
                    ->searchable(),
                TextColumn::make('observations')
                    ->label('Antecedente')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->label('Registrado por:')
                    ->badge()
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-s-plus')
                    ->label('Nuevo Antecedente')
                    ->modalHeading('Registro de Nuevo  Antecedente Quirúrgico')
                    ->form([
                        Textarea::make('observations')
                            ->autosize()
                            ->label('Antecedente')
                            ->required(),
                        Hidden::make('created_by')->default(Auth::user()->name),
                    ])
                    ->modalButton('Guardar Antecedente')
                    ->action(function (RelationManager $livewire, array $data) {
                        try {
                            $record = new FamilyHistory();
                            $record->telemedicine_history_patient_id    = $livewire->ownerRecord->id;
                            $record->telemedicine_patient_id            = $livewire->ownerRecord->telemedicine_patient_id;
                            $record->observations                       = $data['observations'];
                            $record->created_by                         = $data['created_by'];
                            $record->save();
                        } catch (\Throwable $th) {
                            dd($th);
                        }
                    })
            ]);

    }

}