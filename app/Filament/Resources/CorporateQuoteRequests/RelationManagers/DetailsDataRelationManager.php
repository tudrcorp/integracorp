<?php

namespace App\Filament\Resources\CorporateQuoteRequests\RelationManagers;

use BackedEnum;
use Carbon\Carbon;
use App\Models\Fee;
use App\Models\Agent;
use App\Models\State;
use App\Models\Agency;
use App\Models\Region;
use App\Models\AgeRange;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\CorporateQuote;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\DetailCorporateQuote;
use Illuminate\Support\Facades\Auth;
use App\Models\CorporateQuoteRequest;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Illuminate\Validation\Rules\File;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use App\Http\Controllers\UtilsController;
use App\Models\CorporateQuoteRequestData;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Imports\CorporateQuoteRequestDataImporter;
use App\Filament\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;

class DetailsDataRelationManager extends RelationManager
{
    protected static string $relationship = 'detailsData';

    protected static ?string $title = 'POBLACIÓN';

    protected static string|BackedEnum|null $icon = 'heroicon-s-user-plus';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('corporate_quote_request_id')
            ->columns([
                TextColumn::make('first_name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Apellido')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->label('Cédula de Identidad')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->searchable(),
                TextColumn::make('age')
                    ->label('Edad')
                    ->suffix(' años')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable(),
                TextColumn::make('condition_medical')
                    ->label('Condición Medica')
                    ->searchable(),
                TextColumn::make('initial_date')
                    ->label('Fecha de Ingreso')
                    ->searchable(),
                TextColumn::make('position_company')
                    ->label('Cargo')
                    ->suffix(' años')
                    ->searchable(),

        ])
            ->filters([
                //
            ])
            ->headerActions([

                /**Crear cotización */
                Action::make('update_corporate_quote')
                    ->label('Actualizar cotización')
                    ->color('success')
                    ->icon('heroicon-s-check-circle')
                    ->requiresConfirmation()
                    ->action(function (RelationManager $livewire, array $data) {
                        // dd($livewire->ownerRecord->id, $livewire->ownerRecord);
                        $exit_request = DetailCorporateQuote::where('corporate_quote_request_id', $livewire->ownerRecord->id)->count();
                        if($exit_request <= 0){
                            Notification::make()
                                ->title('No exite cotización asociada a la solicitud')
                                ->danger()
                                ->send();
                                return;
                        }
                        $createCorporateQuote = UtilsController::createCorporateQuote($livewire, $data);

                        if ($createCorporateQuote) {
                            Notification::make()
                                ->title('Cotización creada con éxito')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Error al crear la cotización')
                                ->danger()
                                ->send();
                        }

                    }),

                /**Calculo de edades */
                Action::make('calculate_ages')
                    ->label('Calcular edades')
                    ->color('azul')
                    ->icon('heroicon-s-check-circle')
                    ->requiresConfirmation()
                    ->action(function (RelationManager $livewire) {

                        try {

                            //Poblacion de la solicitud
                            $data = CorporateQuoteRequestData::where('corporate_quote_request_id', $livewire->ownerRecord->id)->get()->toArray();


                            /**Calculo las edades */
                            for ($i = 0; $i < count($data); $i++) {
                                $data[$i]['age'] = Carbon::createFromFormat('d/m/Y', $data[$i]['birth_date'])->age; //Carbon::parse($data[$i]['birth_date'])->age;
                                CorporateQuoteRequestData::where('id', $data[$i]['id'])->update(['age' => $data[$i]['age']]);
                            }

                            Notification::make()
                                ->success()
                                ->title('Edades calculadas con éxito')
                                ->send();
                        } catch (\Throwable $th) {
                            Log::error('Error al calcular edades: ' . $th->getMessage());
                            Notification::make()
                                ->danger()
                                ->title('Excepción: ')
                                ->body('Error al calcular edades, por favor verificar el archivo de Logs')
                                ->send();
                        }
                    }),


                /**Importar data de poblacion */
                ImportAction::make()
                    ->importer(CorporateQuoteRequestDataImporter::class)
                    ->label('Importar CSV(Población)')
                    ->color('warning')
                    ->icon('heroicon-s-cloud-arrow-up')
                    ->options(function (RelationManager $livewire) {
                        return [
                            'corporate_quote_request_id' => $livewire->ownerRecord->id,
                        ];
                    })
                    ->fileRules([
                        File::types(['csv', 'txt'])->max(1024),
                    ]),

            ])
            ->toolbarActions([
                BulkAction::make('create_quote')
                    ->label('Crear cotización')
                    ->color('verde')
                    ->icon('heroicon-s-check-circle')
                    ->requiresConfirmation()
                    ->action(function (Collection $records, RelationManager $livewire) {
                        /**
                         * Array para el detalle de la solicutud
                         * Con ente array obtenemos los planes asociados a la solicitud
                         */
                        $details = CorporateQuoteRequest::find($livewire->ownerRecord->id);
                        $details_plan = $details->details->toArray();

                        //Poblacion
                        $poblacion = CorporateQuoteRequestData::where('corporate_quote_request_id', $livewire->ownerRecord->id)->get()->toArray();

                        $array = [];

                        for ($i = 0; $i < count($details_plan); $i++) {
                            //Rabgo de edades segun el plan
                            $rangos = DB::table('age_ranges')->select('id', 'range', 'plan_id', 'age_init', 'age_end')->where('plan_id', $details_plan[$i]['plan_id'])->orderBy('range')->get();
                            foreach ($poblacion as $persona) {
                                $edad = (int) $persona['age'];
                                foreach ($rangos as $rango) {
                                    if ($edad >= $rango->age_init && $edad <= $rango->age_end) {
                                        array_push($array, [
                                            'id' => $persona['id'],
                                            'age' => $persona['age'],
                                            'plan_id' => $details_plan[$i]['plan_id'],
                                            'age_range_id' => $rango->id,
                                            'range' => $rango->range,
                                        ]);
                                        break;
                                    }
                                }
                            }
                        }

                        $resultado = collect($array)
                            ->groupBy('plan_id')
                            ->flatMap(function ($grupoPorPlan, $planId) {
                                return $grupoPorPlan->groupBy('age_range_id')->map(fn($subgrupo, $rangoId) => [
                                    'plan_id' => $planId,
                                    'age_range_id' => $rangoId,
                                    'total_persons' => $subgrupo->count(),
                                ])->values();
                            })
                            ->values()
                            ->toArray();

                        dd($resultado);

                        /**
                         * For para realizar el guardado en la tabla de detalle de cotizacion
                         * ----------------------------------------------------------------------------------------------------
                         */
                        for ($i = 1; $i <= count($res_array); $i++) {
                            //Guardamos el detalle de la cotizacion en la tabla de detalle de cotizacion como segundo paso
                            $plan_ageRange = AgeRange::where('plan_id', $res_array[$i])
                                ->where('id', $array_details[$i]['age_range_id'])
                                ->with('fees')
                                ->get()
                                ->toArray();

                            for ($j = 0; $j < count($plan_ageRange[0]['fees']); $j++) {

                                $fee = Fee::where('id', $plan_ageRange[0]['fees'][$j]['id'])->first();

                                $detail_individual_quote = new DetailCorporateQuote();
                                $detail_individual_quote->corporate_quote_id   = $array_form['id'];
                                $detail_individual_quote->plan_id               = $array_details[$i]['plan_id'];
                                $detail_individual_quote->age_range_id          = $array_details[$i]['age_range_id'];
                                $detail_individual_quote->coverage_id           = $fee->coverage_id;
                                $detail_individual_quote->fee                   = $fee->price;
                                $detail_individual_quote->total_persons         = $array_details[$i]['total_persons'];
                                $detail_individual_quote->subtotal_anual        = $array_details[$i]['total_persons'] * $fee->price;
                                $detail_individual_quote->subtotal_quarterly    = ($array_details[$i]['total_persons'] * $fee->price) / 4;
                                $detail_individual_quote->subtotal_biannual     = ($array_details[$i]['total_persons'] * $fee->price) / 2;
                                $detail_individual_quote->subtotal_monthly      = ($array_details[$i]['total_persons'] * $fee->price) / 12;
                                $detail_individual_quote->status                = 'PRE-APROBADA';
                                $detail_individual_quote->created_by            = Auth::user()->name;
                                $detail_individual_quote->save();
                            }
                        }
                    }),
                DeleteBulkAction::make(),
            ]);
    }
}