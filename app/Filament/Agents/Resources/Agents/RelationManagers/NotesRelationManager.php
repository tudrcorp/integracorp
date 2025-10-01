<?php

namespace App\Filament\Agents\Resources\Agents\RelationManagers;

use BackedEnum;
use App\Models\Agent;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\AgentNoteBlog;
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use App\Filament\Agents\Resources\Agents\AgentResource;
use Filament\Resources\RelationManagers\RelationManager;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Notas';

    protected static string|BackedEnum|null $icon = 'heroicon-o-square-3-stack-3d';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Notas y/o Observaciones')
            ->description('Listas de Notas y/o registradas en la Agencia, ordenas de forma cronológica.')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    ->description(fn($record): string => $record->created_at->diffForHumans())
                    ->sortable(),
                TextColumn::make('note')
                    ->label('Nota')
                    ->searchable()
                    ->wrap(),
            ])
            ->headerActions([
                Action::make('uploadNote')
                    ->label('Notas')
                    ->color('success')
                    ->icon('heroicon-o-square-3-stack-3d')
                    ->modalWidth(Width::Large)
                    ->modalHeading('Cargar Notas')
                    ->modalButton('Cargar')
                    ->modalIcon('heroicon-o-square-3-stack-3d')
                    ->form([
                        Textarea::make('note')
                            ->autosize()
                            ->label('Notas y/o Observaciones')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        AgentNoteBlog::create([
                            'agency_id' => Agent::where('code', Auth::user()->code_agency)->first()->id,
                            'note' => $data['note'],
                            'created_by' => Auth::user()->name
                        ]);

                        Notification::make()
                            ->title('Nota Cargada')
                            ->success()
                            ->send();
                    })
            ]);
    }
}