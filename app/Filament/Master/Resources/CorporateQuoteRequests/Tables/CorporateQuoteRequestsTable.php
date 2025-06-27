<?php

namespace App\Filament\Master\Resources\CorporateQuoteRequests\Tables;

use App\Models\Agent;
use App\Models\Agency;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
        ->query(CorporateQuoteRequest::query()->whereIn('owner_code', [Auth::user()->code_agency, 'TDG-100']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code_agency')
                    ->prefix(function ($record) {
                        $agency_type = Agency::select('agency_type_id')
                            ->where('code', $record->code_agency)
                            ->with('typeAgency')
                            ->first();

                        return isset($agency_type) ? $agency_type->typeAgency->definition . ' - ' : 'MASTER - ';
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-s-building-library')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Codigo')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                TextColumn::make('agent_id')
                    ->label('Código de agente')
                    ->prefix(function ($record) {
                        if (Agent::where('id', $record->agent_id)->where('agent_type_id', 3)->exists()) {
                            return 'SUB-AGT-000';
                        }
                        return 'AGT-000';
                    })
                    ->alignCenter()
                    ->badge()
                    ->icon('heroicon-s-identification')
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
            ])
            ->filters([
                //
            ])
            ->recordActions([
                // ViewAction::make(),
                // EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                ]),
            ]);
    }
}