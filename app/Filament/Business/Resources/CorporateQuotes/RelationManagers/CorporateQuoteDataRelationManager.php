<?php

namespace App\Filament\Business\Resources\CorporateQuotes\RelationManagers;

use Carbon\Carbon;
use App\Models\Fee;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\IndividualQuote;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use App\Models\CorporateQuoteData;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ImportAction;
use Illuminate\Support\Facades\Log;
use App\Models\DetailCorporateQuote;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Illuminate\Validation\Rules\File;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Http\Controllers\UtilsController;
use App\Models\CorporateQuoteRequestData;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\DissociateBulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Filament\Imports\AffiliateCorporateImporter;
use App\Filament\Imports\CorporateQuoteDataImporter;
use Filament\Resources\RelationManagers\RelationManager;
use App\Http\Controllers\DetailCorporateQuotesController;
use App\Filament\Imports\CorporateQuoteRequestDataImporter;

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

                            $exit_request = CorporateQuoteData::where('corporate_quote_id', $livewire->ownerRecord->id)->count();
                            if ($exit_request <= 0) {
                                Notification::make()
                                    ->title('No exite cotización asociada a la solicitud')
                                    ->danger()
                                    ->send();
                                return;
                            }
                            $createCorporateQuote = UtilsController::createCorporateQuote($livewire);

                            if ($createCorporateQuote) {
                                Notification::make()
                                    ->title('La Cotización fue actualizada con éxito, según la data recibida y el rango etario calculado.')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Error al actualizar la cotización, por favor intente nuevamente.')
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Throwable $th) {
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
                        if ($doc == null) {
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
            ])
            ->striped()
            ->defaultSort('id', 'asc')
            ->poll('5s');
    }
}