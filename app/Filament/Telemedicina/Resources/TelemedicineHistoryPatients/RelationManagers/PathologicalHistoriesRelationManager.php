<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\RelationManagers;

use App\Models\PathologicalHistory;
use App\Support\Filament\FilamentIosButton;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PathologicalHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'pathologicalHistories';

    protected static ?string $title = 'Histórico Antecedentes Patologicos';

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

                // ...
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Antecedentes Patológicos')
            ->description('Ordenados de forma cronológica desde el más reciente hasta el más antiguo.')
            ->emptyStateHeading('Sin antecedentes patológicos')
            ->emptyStateDescription('Aún no se han registrado antecedentes patológicos. Use «Nuevo Antecedente» para añadir el primero.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50])
            ->recordActionsColumnLabel('')
            ->extraAttributes([
                'class' => 'telemedicine-case-table-ios telemedicine-history-relation-table',
            ])
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (PathologicalHistory $record): string => $record->updated_at?->diffForHumans() ?? '—')
                    ->color('primary')
                    ->icon('heroicon-s-calendar')
                    ->sortable()
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('observations')
                    ->label('Antecedente')
                    ->wrap()
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
                TextColumn::make('created_by')
                    ->label('Registrado por')
                    ->badge()
                    ->icon('heroicon-s-user')
                    ->color('gray')
                    ->weight(FontWeight::Medium)
                    ->searchable()
                    ->extraCellAttributes(['class' => 'py-3']),
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-s-plus')
                    ->label('Nuevo Antecedente')
                    ->color('primary')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                    ])
                    ->modalHeading('Registro de Nuevo Antecedente Patológico')
                    ->modalSubmitActionLabel('Guardar Antecedente')
                    ->modalSubmitAction(fn (Action $action) => $action
                        ->color('primary')
                        ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('primary')]))
                    ->modalCancelAction(fn (Action $action) => $action
                        ->color('gray')
                        ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('gray')]))
                    ->form([
                        Textarea::make('observations')
                            ->autosize()
                            ->label('Antecedente')
                            ->required(),
                        Hidden::make('created_by')->default(Auth::user()->name),
                    ])
                    ->action(function (RelationManager $livewire, array $data) {
                        try {
                            $record = new PathologicalHistory;
                            $record->telemedicine_history_patient_id = $livewire->ownerRecord->id;
                            $record->telemedicine_patient_id = $livewire->ownerRecord->telemedicine_patient_id;
                            $record->observations = $data['observations'];
                            $record->created_by = $data['created_by'];
                            $record->save();
                        } catch (\Throwable $th) {
                            dd($th);
                        }
                    }),
            ]);
    }
}
