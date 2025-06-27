<?php

namespace App\Filament\General\Resources\CorporateQuoteRequests\Tables;

use App\Models\Agent;
use App\Models\Agency;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Illuminate\Support\Facades\Auth;
use App\Models\CorporateQuoteRequest;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;

class CorporateQuoteRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->query(CorporateQuoteRequest::query()->where('code_agency', Auth::user()->code_agency))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Codigo')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-s-plus')
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
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Razon Social')
                    ->searchable(),
                TextColumn::make('rif')
                    ->label('Rif')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('region')
                    ->label('Región')
                    ->numeric()
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
                TextColumn::make('created_at')
                    ->label('Creada el:')
                    ->dateTime()
                    ->sortable(),
                // Tables\Columns\TextColumn::make('document')
                //     ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
            ActionGroup::make([

                /**EDIT */
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-m-pencil')
                    ->color('warning'),
            ])
                ->icon('heroicon-c-ellipsis-vertical')
                ->color('azulOscuro')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}