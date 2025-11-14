<?php

namespace App\Filament\General\Resources\CorporateQuotes\RelationManagers;

use App\Filament\General\Resources\CorporateQuotes\CorporateQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

use App\Models\Agent;
use App\Models\Agency;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Illuminate\Support\Collection;
use App\Models\AffiliationCorporate;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class DetailCoporateQuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'detailCoporateQuotes';

    protected static ?string $title = 'CALCULO DE COTIZACIÓN';

    public function table(Table $table): Table
    {
        return $table
            ->heading('TABLA DE SELECCIÓN MULTIPLE')
            ->description('En esta tabla se muestran los planes y coberturas cotizados. Para realizar una pre afiliación multiple debes seleccionar dos o mas planes y por cada plan debe seleccionar solo una cobertura, de lo contrario no se realizara la pre afiliación.')
            ->recordTitleAttribute('individual_quote_id')
            ->columns([
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->sortable(),
                TextColumn::make('ageRange.range')
                    ->label('Rango de Edad')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->searchable()
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('fee')
                    ->label('Tarifa individual')
                    ->alignCenter()
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_anual')
                    ->label('Total anual')
                    ->alignCenter()
                    ->description(fn($record): string => $record->total_persons . ' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_biannual')
                    ->label('Total semestral')
                    ->alignCenter()
                    ->description(fn($record): string => $record->total_persons . ' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_quarterly')
                    ->label('Total trimestral')
                    ->alignCenter()
                    ->description(fn($record): string => $record->total_persons . ' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_monthly')
                    ->label('Total Mensual')
                    ->alignCenter()
                    ->description(fn($record): string => $record->total_persons . ' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$')
                    ->hidden(fn(): bool => Agency::where('code', Auth::user()->code_agency)->first()->activate_monthly_frequency == 0),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-APROBADA' => 'verde',
                            'APROBADA' => 'success',
                            'EJECUTADA' => 'azul',
                            'ACTIVA-PENDIENTE' => 'azul',
                        };
                    })
                    ->sortable(),
            ])
            //agrupar por planes y por coberturas
            ->defaultGroup('ageRange.range')
            ->filters([
                SelectFilter::make('plan_id')
                    ->label('Lista de planes')
                    ->multiple()
                    ->preload()
                    ->relationship('plan', 'description')
                    ->attribute('plan_id'),
                SelectFilter::make('coverage_id')
                    ->label('Lista de coberturas')
                    ->multiple()
                    ->preload()
                    ->relationship('coverage', 'price')
                    ->attribute('coverage_id'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('pre_affiliation_multiple')
                        ->label('Preafiliacion Multiple')
                        ->icon('heroicon-s-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, RelationManager $livewire) {
                            try {

                                // dd($records, $records->count(), $records->toArray(), $livewire->ownerRecord);

                                //Guardo data records en una varaiable de sesion, si la variable de session exite y tiene informacion se actualiza

                                session()->get('data_records', []);

                                session()->put('data_records', $records->toArray());

                                $data_records = session()->get('data_records');

                                dd($data_records);

                                /**
                                 * Actualizo el status a APROBADA
                                 */

                                $livewire->ownerRecord->status = 'APROBADA';
                                $livewire->ownerRecord->save();

                                $record = $records->first();

                                if ($records->count() == 1) {
                                    return redirect()->route('filament.agents.resources.affiliation-corporates.create', ['id' => $record->corporate_quote_id, 'plan_id' => $record->plan_id]);
                                }

                                if ($records->count() > 1) {
                                    return redirect()->route('filament.agents.resources.affiliation-corporates.create', ['id' => $record->plan_id, 'plan_id' => null]);
                                }
                            } catch (\Throwable $th) {
                                dd($th);
                                // $parte_entera = 0;
                            }
                        })
                ]),
            ]);
    }

    public function getOwnerCode()
    {
        try {

            /**
             * Logica para asignar el owner_code
             * ---------------------------------------------------------------------------------------------------------
             */
            $owner      = Agent::select('owner_code', 'id')->where('id', Auth::user()->agent_id)->first()->owner_code;
            $jerarquia  = Agency::select('code', 'owner_code')->where('code', $owner)->first()->owner_code;

            /**
             * Cuando el agente pertenece a una AGENCIA GENERAL
             * -----------------------------------------------------
             */
            if ($owner != $jerarquia && $jerarquia != 'TDG-100') {
                return $jerarquia;
            }

            /**
             * Cuando el agente pertenece a una AGENCIA MASTER
             * -----------------------------------------------------
             */
            if ($owner != $jerarquia && $jerarquia == 'TDG-100') {
                return $owner;
            }
        } catch (\Throwable $th) {
            dd($th);
            // return null;
        }
    }

    public function getCodeAgency()
    {
        try {

            return Agent::select('owner_code', 'id')->where('id', Auth::user()->agent_id)->first()->owner_code;
        } catch (\Throwable $th) {
            dd($th);
            // return null;
        }
    }

    public function getCode()
    {
        try {

            if (AffiliationCorporate::max('id') == null) {
                $parte_entera = 0;
            } else {
                $parte_entera = AffiliationCorporate::max('id');
            }

            $code = 'TDEC-AFC-000' . $parte_entera + 1;

            return $code;
        } catch (\Throwable $th) {
            dd($th);
            // $parte_entera = 0;
        }
    }
}