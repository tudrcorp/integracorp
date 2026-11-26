<?php

namespace App\Filament\General\Resources\Affiliations\Pages;

use App\Filament\General\Resources\Affiliations\AffiliationResource;
use App\Filament\Concerns\PricesIndividualPreAffiliation;
use App\Http\Controllers\AffiliationController;
use App\Models\Affiliate;
use App\Models\DetailIndividualQuote;
use App\Models\IndividualQuote;
use App\Models\User;
use App\Support\Affiliations\AffiliationDocumentTypeColumns;
use App\Support\Affiliations\PersonIdentity\DuplicatePersonIdentityException;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;

class CreateAffiliation extends CreateRecord
{
    use PricesIndividualPreAffiliation;

    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Formulario de preafiliación';

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('gray')
                ->url(AffiliationResource::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        session()->put('affiliates', isset($data['affiliates']) ? $data['affiliates'] : []);

        $data['feedback'] = $data['feedback'] == true ? true : false;

        if ($data['feedback_dos']) {
            $data['full_name_payer'] = $data['full_name_ti'];
            $data['nro_identificacion_payer'] = $data['nro_identificacion_ti'];
            $data = AffiliationDocumentTypeColumns::copyHolderTypeToPayer($data);
            $data['email_payer'] = $data['email_ti'];
            $data['phone_payer'] = $data['phone_ti'];
        }

        /** Montos por edad, calculados en el servidor: reemplazan los que vengan del formulario. */
        $data = $this->priceIndividualPreAffiliationData($data);

        return $data;
    }

    // protected function beforeCreate(): void
    // {
    //     //Si el titular en menor de edad no puede afiliarse
    //     if (Carbon::parse($this->data['birth_date_ti'])->age < 18) {
    //         Notification::make()
    //             ->title('El titular debe ser mayor de 18 años')
    //             ->icon('heroicon-s-exclamation-triangle')
    //             ->danger()
    //             ->send();

    //         $this->halt();
    //     }

    //     return;
    // }

    protected function afterCreate(): void
    {
        try {

            $record = $this->getRecord();

            /**
             * ? Preguntamos si e l contratante es el mismo titular de la cotizacion
             * $feedback == false significa que el contratante no es el titular, y debemos agregar afiliados
             * $feedback == true significa que el contratante es el titular, y debemos afiliar al contratante
             * -----------------------------------------------------------------------------------------------------------------------------
             */
            // El titular desea agregar afiliados
            if ($record->feedback == true) {
                /**
                 * Recupero los detalles de los afiliados
                 * ----------------------------------------------------------------------------------------------------
                 */
                $affiliates = session()->get('affiliates');
                // dd($affiliates);
                // Agregamos al titular al array de afiliados
                $affiliates[] = [
                    'full_name' => $record->full_name_ti,
                    'nro_identificacion' => $record->nro_identificacion_ti,
                    'sex' => $record->sex_ti,
                    'birth_date' => $record->birth_date_ti,
                    'relationship' => 'TITULAR',
                    'document' => $record->document,
                ];

                // Ordenamos los afiliados por fecha de nacimiento
                usort($affiliates, function ($a, $b) {
                    // Si uno es TITULAR, va primero
                    if ($a['relationship'] === 'TITULAR' && $b['relationship'] !== 'TITULAR') {
                        return -1;
                    }
                    if ($a['relationship'] !== 'TITULAR' && $b['relationship'] === 'TITULAR') {
                        return 1;
                    }

                    // Si ambos son distintos de TITULAR, ordenar alfabéticamente descendente por relationship
                    return $b['relationship'] <=> $a['relationship'];
                });

                // dd($affiliates);

                /**
                 * La cotización no se modifica aunque cambien las personas o sus edades: es la propuesta
                 * que recibió el cliente. Los montos reales salen de las edades (más abajo) y la diferencia
                 * con lo cotizado queda en la bitácora de la afiliación.
                 */

                /**----------------------------------------------------------------------------------------------------------------------------- */

                /**
                 * For para cargar la data de los afiliados en la tabla de affiliates
                 * ----------------------------------------------------------------------------------------------------
                 */
                for ($i = 0; $i < count($affiliates); $i++) {
                    $record->affiliates()->create([
                        'full_name' => $affiliates[$i]['full_name'],
                        'nro_identificacion' => $affiliates[$i]['nro_identificacion'],
                        'sex' => $affiliates[$i]['sex'],
                        'birth_date' => $affiliates[$i]['birth_date'],
                        'age' => Carbon::parse($affiliates[$i]['birth_date'])->age,
                        'relationship' => $affiliates[$i]['relationship'],
                        'document' => $affiliates[$i]['document'],
                        'address' => $record->adress_ti,
                        'phone' => $record->phone_ti,
                        'country_id' => $record->country_id_ti,
                        'state_id' => $record->state_id_ti,
                        'city_id' => $record->city_id_ti,
                        'region' => $record->region_ti,
                        'status' => 'PRE-APROBADA',
                    ]);
                }

                /** Plan, cobertura, rango y tarifa de cada afiliado, y la suma real en la afiliación. */
                $this->priceIndividualPreAffiliation($record);

                /**
                 * Actualizamos el estatus de la cotizacion a EJECUTADA
                 * para evitar que pueda volverse a pre-afiliarse
                 *
                 * Esta actualizacion se realiza en ambas tablas
                 * ----------------------------------------------------------------------------------------------------
                 */
                $quote = IndividualQuote::select('status', 'id')->where('id', $record->individual_quote_id)->firstOrFail();
                $quote->status = 'EJECUTADA';
                $quote->save();

                $quote->detailsQuote()->update(['status' => 'EJECUTADA']);

                /**
                 * Actualizacion de la cotizacion
                 * Se cambia el estatus de la cobertura de la cotizacion que selecciono el cliente
                 * ----------------------------------------------------------------------------------------------------
                 */
                $quote_detail = DetailIndividualQuote::select('coverage_id', 'status', 'id')
                    ->where('individual_quote_id', $record->individual_quote_id)
                    ->where('plan_id', $record->plan_id)
                    ->where('coverage_id', $record->coverage_id)
                    ->firstOrFail();
                $quote_detail->status = 'APROBADA';
                $quote_detail->save();

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                $data_titular = Affiliate::where('affiliation_id', $record->id)->where('relationship', 'TITULAR')->firstOrFail()->toArray();

                // $this->getRecord()->sendCertificate($record, $data_titular, $affiliates);
                AffiliationController::generateCertificateIndividual($record, $affiliates, Auth::id());

                /**
                 * Elimino las variable de sesion para evitar sobrecargar
                 * ----------------------------------------------------------------------------------------------------
                 */
                session()->forget('affiliates');
                session()->forget('persons');

                /**
                 * Actualizo el numero de afiliados (poblacion)
                 * ----------------------------------------------------------------------------------------------------
                 */
                $record->family_members = $record->affiliates()->count();
                $record->save();

                /**
                 * Logica para enviar una notificacion a la sesion del administrador despues de crear la corizacion
                 * ----------------------------------------------------------------------------------------------------
                 * $record [Data de la cotizacion guardada en la base de dastos]
                 */
                $recipient = User::where('is_admin', 1)->get();
                foreach ($recipient as $user) {
                    $recipient_for_user = User::find($user->id);
                    Notification::make()
                        ->title('PRE-AFILIACION INDIVIDUAL CREADA')
                        ->body('Se ha registrado una nueva pre-afiliacion individual de forma exitosa. Codigo: '.$record->code)
                        ->icon('heroicon-s-user-group')
                        ->iconColor('success')
                        ->success()
                        ->actions([
                            Action::make('view')
                                ->label('Ver detalle de pre-afiliacion')
                                ->button()
                                ->url(AffiliationResource::getUrl('edit', ['record' => $record->id], panel: 'admin')),
                        ])
                        ->sendToDatabase($recipient_for_user);
                }
            }

            /**----------------------------------------------------------------------------------------------------------------------------- */

            // El titular ese el unico afiliado
            if ($record->feedback == false) {
                /** 1- Registro los datos de contratante como los datos del afiliado ya que la cotizacion es para el mismo*/
                $record->affiliates()->create([
                    'full_name' => $record->full_name_ti,
                    'nro_identificacion' => $record->nro_identificacion_ti,
                    'sex' => $record->sex_ti,
                    'birth_date' => $record->birth_date_ti,
                    'age' => Carbon::parse($record->birth_date_ti)->age,
                    'address' => $record->adress_ti,
                    'document' => $record->document,
                    'phone' => $record->phone_ti,
                    'country_id' => $record->country_id_ti,
                    'state_id' => $record->state_id_ti,
                    'city_id' => $record->city_id_ti,
                    'region' => $record->region_ti,
                    'status' => 'PRE-APROBADA',
                    'relationship' => 'TITULAR',
                ]);

                /** Plan, cobertura, rango y tarifa del titular, y la suma real en la afiliación. */
                $this->priceIndividualPreAffiliation($record);

                /**
                 * Actualizamos el estatus de la cotizacion a EJECUTADA
                 * para evitar que pueda volverse a pre-afiliarse
                 *
                 * Esta actualizacion se realiza en ambas tablas
                 * ----------------------------------------------------------------------------------------------------
                 */
                $quote = IndividualQuote::select('status', 'id')->where('id', $record->individual_quote_id)->firstOrFail();
                $quote->status = 'EJECUTADA';
                $quote->save();

                $quote->detailsQuote()->update(['status' => 'EJECUTADA']);

                /**
                 * Actualizacion de la cotizacion
                 * Se cambia el estatus de la cobertura de la cotizacion que selecciono el cliente
                 * ----------------------------------------------------------------------------------------------------
                 */
                $quote_detail = DetailIndividualQuote::select('coverage_id', 'status', 'id')
                    ->where('individual_quote_id', $record->individual_quote_id)
                    ->where('plan_id', $record->plan_id)
                    ->where('coverage_id', $record->coverage_id)
                    ->firstOrFail();
                $quote_detail->status = 'APROBADA';
                $quote_detail->save();

                /**
                 * Elimino las variable de sesion para evitar sobrecargar
                 * ----------------------------------------------------------------------------------------------------
                 */
                session()->forget('persons');

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                // $data_titular = Affiliate::where('affiliation_id', $record->id)->where('relationship', 'TITULAR')->firstOrFail()->toArray();
                $affiliate = Affiliate::where('affiliation_id', $record->id)->get()->toArray();
                // dd($data_titular, $affiliate);
                $this->getRecord()->sendCertificateOnlyHolder($record, $affiliate);
                AffiliationController::generateCertificateIndividual($record, $affiliate, Auth::id());

                /**
                 * Actualizo el numero de afiliados (poblacion)
                 * ----------------------------------------------------------------------------------------------------
                 */
                $record->family_members = 1;
                $record->save();
            }
        } catch (DuplicatePersonIdentityException $duplicatePerson) {
            throw $duplicatePerson;
        } catch (Halt $halt) {
            throw $halt;
        } catch (\Throwable $th) {
            $this->failIndividualPreAffiliation($th);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
