<?php

namespace App\Filament\Resources\CorporateQuotes\RelationManagers;

use Carbon\Carbon;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use App\Models\CorporateQuoteData;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Illuminate\Support\Facades\Log;
use App\Models\AffiliationCorporate;
use Illuminate\Validation\Rules\File;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use App\Filament\Imports\AffiliateCorporateImporter;
use App\Filament\Imports\CorporateQuoteDataImporter;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Resources\CorporateQuotes\CorporateQuoteResource;

class CorporateQuoteDataRelationManager extends RelationManager
{
    protected static string $relationship = 'corporateQuoteData';

    protected static ?string $title = 'DATA DE POBLACIÓN';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('corporate_quote_id')
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
                    ->label('Correo electrónico')
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
                    /**Calculo de edades */
                    Action::make('calculate_ages')
                        ->label('Calcular edades')
                        ->color('azul')
                        ->icon('heroicon-s-check-circle')
                        ->requiresConfirmation()
                        ->modalIcon('fluentui-document-arrow-right-20')
                        ->modalHeading('Calcular Edades')
                        ->modalDescription('Permite recalcular el rango etario, para verificar posibles errores en la data recibida por parte del agente.')
                        ->modalWidth(Width::ExtraLarge)
                        ->action(function (RelationManager $livewire) {

                            try {

                                //Poblacion de la solicitud
                                $data = CorporateQuoteData::where('corporate_quote_id', $livewire->ownerRecord->id)->get()->toArray();

                                /**Calculo las edades */
                                for ($i = 0; $i < count($data); $i++) {
                                    $data[$i]['age'] = Carbon::createFromFormat('d/m/Y', $data[$i]['birth_date'])->age;
                                    CorporateQuoteData::where('id', $data[$i]['id'])->update(['age' => $data[$i]['age']]);
                                }

                                Notification::make()
                                    ->success()
                                    ->title('Edades calculadas con éxito')
                                    ->send();
                            } catch (\Throwable $th) {
                                dd($th);   
                                Log::error('Error al calcular edades: ' . $th->getMessage());
                                Notification::make()
                                    ->danger()
                                    ->title('Excepción: ')
                                    ->body('Error al calcular edades, por favor verificar el archivo de Logs')
                                    ->send();
                            }
                        }),
                    ImportAction::make()
                        ->label('Importar Población')
                        ->importer(CorporateQuoteDataImporter::class)
                        ->icon('fluentui-database-arrow-up-20')
                        ->color('success')
                        ->modalHeading('Importar Población')
                        // ->modalDescription('Permite recalcular el rango etario, para verificar posibles errores en la data recibida por parte del agente.')
                        ->options(function (RelationManager $livewire) {
                            return [
                                'corporate_quote_id' => $livewire->ownerRecord->id,
                            ];
                        })
                        ->fileRules([
                            File::types(['csv', 'txt'])->max(1024),
                        ]),
                    Action::make('download_file')
                        ->label('Descargar archivo')
                        ->icon('fluentui-attach-arrow-right-24-o')
                        ->color('info')
                        ->tooltip('Archivo adjuntado por el agente')
                        ->action(function (RelationManager $livewire) {
                            $doc = $livewire->ownerRecord->data_doc;
                            if($doc == null){
                                Notification::make()
                                    ->title('El agente no ha cargado el archivo adjunto. Por favor intente nuevamente o comuníquese con el agente.')
                                    ->icon('heroicon-s-exclamation-triangle')
                                    ->warning()
                                    ->send();
                                return;
                            }
                            $path = public_path('storage/' . $doc);
                            return response()->download($path);
                        }),
                        // ->hidden(function (RelationManager $livewire) {
                        //     return $livewire->ownerRecord->isAffiliated($livewire->ownerRecord->id);
                        // }),
            ]);
    }
}