<?php

namespace App\Filament\Master\Resources\CorporateQuotes\Tables;

use App\Models\Agent;
use App\Models\Agency;
use Filament\Tables\Table;
use App\Models\CorporateQuote;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;

class CorporateQuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->query(CorporateQuote::query()->whereIn('owner_code', [Auth::user()->code_agency, 'TDG-100']))
            ->defaultSort('created_at', 'desc')
            ->heading('COTIZACIONES CORPORATIVAS')
            ->description('Lista de cotizaciones corporativas')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-tag')
                    ->searchable(),
                TextColumn::make('registrated_by')
                    ->label('Registrado por:')
                    ->default(function ($record) {
                        if ($record->agent_id == null) {
                            return $record->code_agency;
                        }
                        if ($record->agent_id != null) {
                            if (Agent::where('id', $record->agent_id)->where('agent_type_id', 3)->exists()) {
                                return 'SUB-AGT-000' . $record->agent_id;
                            }
                            return 'AGT-000' . $record->agent_id;
                        }
                    })
                    ->badge()
                    ->icon(function ($record) {
                        $agency_type = Agency::select('agency_type_id')
                            ->where('code', $record->code_agency)
                            ->with('typeAgency')
                            ->first();
                        if (Agent::where('id', $record->agent_id)->where('agent_type_id', 3)->exists()) {
                            return 'heroicon-m-users';
                        } elseif (Agent::where('id', $record->agent_id)->where('agent_type_id', 2)->exists()) {
                            return 'heroicon-m-user';
                        } elseif ($agency_type->typeAgency->definition == 'MASTER') {
                            return 'heroicon-m-academic-cap';
                        } else {
                            return 'heroicon-s-building-library';
                        }
                    })
                    ->color('azul')
                    ->searchable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('region')
                    ->label('Región')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('count_days')
                    ->label('Transcurrido')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('Solicitada por:')
                    ->searchable(),
                TextColumn::make('rif')
                    ->label('Rif')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Generada el:')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('count_days')
                    ->label('Transcurrido')
                    ->alignCenter()
                    ->badge()
                    ->suffix(' dias')
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-APROBADA'  => 'verdeOpaco',
                            'APROBADA'      => 'success',
                            'ANULADA'       => 'warning',
                            'DECLINADA'     => 'danger',
                            default => 'azul',
                        };
                    })
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}