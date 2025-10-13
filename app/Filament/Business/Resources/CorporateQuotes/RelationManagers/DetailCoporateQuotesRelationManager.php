<?php

namespace App\Filament\Business\Resources\CorporateQuotes\RelationManagers;

use App\Filament\Business\Resources\CorporateQuotes\CorporateQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use App\Models\Agent;
use App\Models\Agency;
use Filament\Actions\BulkAction;
use App\Models\AffiliationCorporate;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Collection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class DetailCoporateQuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'detailCoporateQuotes';

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


                                // $data_records = session()->get('data_records', []);
                                // session()->put('data_records', $data_records);

                                // dd($data_records, $data_records[0]['corporate_quote_id']);

                                /** 2. Guardar los datos de la pre afiliacion */
                                // $preAfiliation = new AffiliationCorporate();

                                // $preAfiliation->code                = $this->getCode();
                                // $preAfiliation->corporate_quote_id  = $data_records[0]['corporate_quote_id'];
                                // $preAfiliation->type                = count($data_records) > 1 ? 'MULTIPLE' : null;
                                // $preAfiliation->plan_id             = count($data_records) > 1 ? null : $data_records[0]['plan_id'];
                                // $preAfiliation->coverage_id         = count($data_records) > 1 ? null : $data_records[0]['coverage_id'];
                                // $preAfiliation->full_name_con       = $data['full_name_con'];
                                // $preAfiliation->rif                 = $data['rif'];
                                // $preAfiliation->email_con           = $data['email_con'];
                                // $preAfiliation->phone_con           = $data['phone_con'];
                                // // $preAfiliation->total_persons = array_sum(array_column($data, 'total_persons'));
                                // $preAfiliation->agent_id            = Auth::user()->agent_id;
                                // $preAfiliation->code_agency         = $this->getCodeAgency();
                                // $preAfiliation->owner_code          = $this->getOwnerCode();
                                // $preAfiliation->created_by          = Auth::user()->name;
                                // $preAfiliation->status              = 'PRE-APROBADA';
                                // //------------------------------------------------------------------------------------------------------------------------
                                // $preAfiliation->save();

                                // /** 1.- Cargo la data de la pre afiliacion en la tabla de afiliados */
                                // for ($i = 0; $i < count($data_records); $i++) {

                                //     /** Guardar los datos en la tabla de afiliados */
                                //     $detailsAfiliationPlans = AfilliationCorporatePlan::create([
                                //         'affiliation_corporate_id'  => $preAfiliation->id,
                                //         'code_affiliation'          => $preAfiliation->code,
                                //         'plan_id'                   => $data_records[$i]['plan_id'],
                                //         'coverage_id'               => $data_records[$i]['coverage_id'],
                                //         'age_range_id'              => $data_records[$i]['age_range_id'],
                                //         'total_persons'             => $data_records[$i]['total_persons'],
                                //         'fee'                       => $data_records[$i]['fee'],
                                //         'subtotal_anual'            => $data_records[$i]['subtotal_anual'],
                                //         'subtotal_quarterly'        => $data_records[$i]['subtotal_quarterly'],
                                //         'subtotal_biannual'         => $data_records[$i]['subtotal_biannual'],
                                //         'subtotal_monthly'          => $data_records[$i]['subtotal_monthly'],
                                //         'status'                    => 'PRE-APROBADA',
                                //         'created_by'                => Auth::user()->name,
                                //     ]);

                                // }

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