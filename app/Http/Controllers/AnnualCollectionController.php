<?php

namespace App\Http\Controllers;

use App\Models\AnnualCollection;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnnualCollectionController extends Controller
{
    //
    public static function createAnnualCollectionAnual($collection)
    {
        // Envuelve la creacion de la coleccion en una transaccion con su clouse catch para rollback en caso de error
        DB::transaction(function () use ($collection) {
            try {
                // 1- Extraemos el mes de la fecha de pago
                $month = self::extractMonth($collection->next_payment_date);

                // 2- escribimos en la tabla de cobros anuales los meses que se deben cobrar
                $annual_collections = new AnnualCollection;
                $annual_collections->sale_id = $collection->sale_id;
                $annual_collections->include_date = $collection->include_date;
                $annual_collections->owner_code = $collection->owner_code;
                $annual_collections->code_agency = $collection->code_agency;
                $annual_collections->agent_id = $collection->agent_id;
                $annual_collections->coverage_id = $collection->coverage_id;
                $annual_collections->collection_invoice_number = $collection->collection_invoice_number;
                $annual_collections->quote_number = $collection->quote_number;
                $annual_collections->affiliation_code = $collection->affiliation_code;
                $annual_collections->affiliate_full_name = $collection->affiliate_full_name;
                $annual_collections->affiliate_contact = $collection->affiliate_contact;
                $annual_collections->affiliate_ci_rif = $collection->affiliate_ci_rif;
                $annual_collections->affiliate_phone = $collection->affiliate_phone;
                $annual_collections->affiliate_email = $collection->affiliate_email;
                $annual_collections->affiliate_status = $collection->affiliate_status;
                $annual_collections->plan_id = $collection->plan_id;
                $annual_collections->service = $collection->service;
                $annual_collections->persons = $collection->persons;
                $annual_collections->type = $collection->type;
                $annual_collections->next_payment_date = $collection->next_payment_date;
                $annual_collections->expiration_date = $collection->expiration_date;
                $annual_collections->filter_next_payment_date = $collection->filter_next_payment_date;
                $annual_collections->save();

                // 3- Tomo el ID del registro anterior y lo actualizo a true el numero del mes que corresponda.
                $annual_collections->update([
                    'month_'.$month => true,
                ]);

            } catch (\Throwable $th) {
                Log::error('Error al crear el registro de cobro anual: '.$th->getMessage());
                throw $th;
            }
        });
    }

    public static function createAnnualCollectionTrimestral($collection, array $array_dates)
    {
        // Envuelve la creacion de la coleccion en una transaccion con su clouse catch para rollback en caso de error
        DB::transaction(function () use ($collection, $array_dates) {
            try {

                // 1- guardamos el registro en la tabla de cobros anuales
                $annual_collections = new AnnualCollection;
                $annual_collections->sale_id = $collection->sale_id;
                $annual_collections->include_date = $collection->include_date;
                $annual_collections->owner_code = $collection->owner_code;
                $annual_collections->code_agency = $collection->code_agency;
                $annual_collections->agent_id = $collection->agent_id;
                $annual_collections->coverage_id = $collection->coverage_id;
                $annual_collections->collection_invoice_number = $collection->collection_invoice_number;
                $annual_collections->quote_number = $collection->quote_number;
                $annual_collections->affiliation_code = $collection->affiliation_code;
                $annual_collections->affiliate_full_name = $collection->affiliate_full_name;
                $annual_collections->affiliate_contact = $collection->affiliate_contact;
                $annual_collections->affiliate_ci_rif = $collection->affiliate_ci_rif;
                $annual_collections->affiliate_phone = $collection->affiliate_phone;
                $annual_collections->affiliate_email = $collection->affiliate_email;
                $annual_collections->affiliate_status = $collection->affiliate_status;
                $annual_collections->plan_id = $collection->plan_id;
                $annual_collections->service = $collection->service;
                $annual_collections->persons = $collection->persons;
                $annual_collections->type = $collection->type;
                $annual_collections->next_payment_date = $collection->next_payment_date;
                $annual_collections->expiration_date = $collection->expiration_date;
                $annual_collections->save();

                // 2- actualizamos el registro en la tabla de cobros anuales con los meses que se deben cobrar
                for ($i = 0; $i < count($array_dates); $i++) {
                    $annual_collections->update([
                        'month_'.self::extractMonth($array_dates[$i]) => true,
                    ]);
                }

            } catch (\Throwable $th) {
                Log::error('Error al crear el registro de cobro trimestral: '.$th->getMessage());
                throw $th;
            }
        });
    }

    public static function createAnnualCollectionSemestral($collection)
    {
        // Envuelve la creacion de la coleccion en una transaccion con su clouse catch para rollback en caso de error
        DB::transaction(function () use ($collection) {
            try {
                // 1- Extraemos el mes de la fecha de pago
                $month = self::extractMonth($collection->next_payment_date);

                // 2- escribimos en la tabla de cobros anuales los meses que se deben cobrar
                $annual_collections = new AnnualCollection;
                $annual_collections->sale_id = $collection->sale_id;
                $annual_collections->include_date = $collection->include_date;
                $annual_collections->owner_code = $collection->owner_code;
                $annual_collections->code_agency = $collection->code_agency;
                $annual_collections->agent_id = $collection->agent_id;
                $annual_collections->coverage_id = $collection->coverage_id;
                $annual_collections->collection_invoice_number = $collection->collection_invoice_number;
                $annual_collections->quote_number = $collection->quote_number;
                $annual_collections->affiliation_code = $collection->affiliation_code;
                $annual_collections->affiliate_full_name = $collection->affiliate_full_name;
                $annual_collections->affiliate_contact = $collection->affiliate_contact;
                $annual_collections->affiliate_ci_rif = $collection->affiliate_ci_rif;
                $annual_collections->affiliate_phone = $collection->affiliate_phone;
                $annual_collections->affiliate_email = $collection->affiliate_email;
                $annual_collections->affiliate_status = $collection->affiliate_status;
                $annual_collections->plan_id = $collection->plan_id;
                $annual_collections->service = $collection->service;
                $annual_collections->persons = $collection->persons;
                $annual_collections->type = $collection->type;
                $annual_collections->next_payment_date = $collection->next_payment_date;
                $annual_collections->expiration_date = $collection->expiration_date;
                $annual_collections->save();

                // 3- Tomo el ID del registro anterior y lo actualizo a true el numero del mes que corresponda.
                $annual_collections->update([
                    'month_'.$month => true,
                ]);
            } catch (\Throwable $th) {
                Log::error('Error al crear el registro de cobro anual: '.$th->getMessage());
                throw $th;
            }
        });
    }

    public static function createAnnualCollectionMensual($collection, $array_dates)
    {
        // Envuelve la creacion de la coleccion en una transaccion con su clouse catch para rollback en caso de error
        DB::transaction(function () use ($collection, $array_dates) {
            try {

                // 1- guardamos el registro en la tabla de cobros anuales
                $annual_collections = new AnnualCollection;
                $annual_collections->sale_id = $collection->sale_id;
                $annual_collections->include_date = $collection->include_date;
                $annual_collections->owner_code = $collection->owner_code;
                $annual_collections->code_agency = $collection->code_agency;
                $annual_collections->agent_id = $collection->agent_id;
                $annual_collections->coverage_id = $collection->coverage_id;
                $annual_collections->collection_invoice_number = $collection->collection_invoice_number;
                $annual_collections->quote_number = $collection->quote_number;
                $annual_collections->affiliation_code = $collection->affiliation_code;
                $annual_collections->affiliate_full_name = $collection->affiliate_full_name;
                $annual_collections->affiliate_contact = $collection->affiliate_contact;
                $annual_collections->affiliate_ci_rif = $collection->affiliate_ci_rif;
                $annual_collections->affiliate_phone = $collection->affiliate_phone;
                $annual_collections->affiliate_email = $collection->affiliate_email;
                $annual_collections->affiliate_status = $collection->affiliate_status;
                $annual_collections->plan_id = $collection->plan_id;
                $annual_collections->service = $collection->service;
                $annual_collections->persons = $collection->persons;
                $annual_collections->type = $collection->type;
                $annual_collections->next_payment_date = $collection->next_payment_date;
                $annual_collections->expiration_date = $collection->expiration_date;
                $annual_collections->save();

                // 2- actualizamos el registro en la tabla de cobros anuales con los meses que se deben cobrar
                for ($i = 0; $i < count($array_dates); $i++) {
                    $annual_collections->update([
                        'month_'.self::extractMonth($array_dates[$i]) => true,
                    ]);
                }
            } catch (\Throwable $th) {
                Log::error('Error al crear el registro de cobro Mensual: '.$th->getMessage());
                throw $th;
            }
        });
    }

    /**
     * Extrae el número del mes de una fecha en formato DD/MM/YYYY
     *
     * @param  string  $date  Fecha en formato DD/MM/YYYY
     * @return int Número del mes (1-12)
     *
     * @throws InvalidFormatException Si el formato es inválido
     */
    public static function extractMonth(string $date): int
    {
        try {
            // Validar formato mínimo (dd/mm/yyyy)
            if (! preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $date)) {
                throw new InvalidFormatException("Formato de fecha inválido: {$date}");
            }

            // Parsear con Carbon (maneja automáticamente el formato europeo)
            $carbon = Carbon::createFromFormat('d/m/Y', $date);

            // Validar que la fecha sea válida (ej: no 31/02/2027)
            if (! $carbon->isValid()) {
                throw new InvalidFormatException("Fecha inválida: {$date}");
            }

            return (int) $carbon->format('m');
        } catch (\Exception $e) {
            // Loggear error para debugging
            Log::error('Error extrayendo mes de fecha', [
                'date' => $date,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new InvalidFormatException(
                "No se pudo extraer el mes de la fecha '{$date}'. ".
                    'Formato esperado: DD/MM/YYYY'
            );
        }
    }
}
