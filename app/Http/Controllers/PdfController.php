<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class PdfController extends Controller
{
    public function generatePdf()
    {
        $pdf = Pdf::loadView('documents.certificate');
        return $pdf->stream();
    }

    public function generatePdfInformeMedicoGeneral()
    {
        $pdf = Pdf::loadView('documents.informe-medico-general');
        return $pdf->stream();
    }

    public function generatePdf_propuestaEconomica()
    {
        $pdf = Pdf::loadView('documents.propuesta-economica');
        return $pdf->stream();
    }

    public function generatePdf_cartaBienvenida()
    {
        $pdf = Pdf::loadView('documents.carta-bienvenida-agente');
        return $pdf->stream();
    }

    public function generatePdf_targetaAfiliado()
    {
        $pdf = Pdf::loadView('pruebaPdf');
        return $pdf->stream();
    }

    public function generatePdf_aviso_de_pago()
    {
        $pdf = Pdf::loadView('documents.aviso-de-pago');
        return $pdf->stream();
    }

    public static function generatePdfIndividualQuote($record)
    {

        try {
            
            if ($record->plan == 1) {
                $detalle = DB::table('detail_individual_quotes')
                    ->join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                    ->join('age_ranges', 'detail_individual_quotes.age_range_id', '=', 'age_ranges.id')
                    ->select('detail_individual_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range')
                    ->where('individual_quote_id', $record->id)
                    ->get()
                    ->toArray();

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                $details = [
                    'plan' => 1,
                    'code' => $record->code,
                    'name' => $record->full_name,
                    'email' => $record->email,
                    'phone' => $record->phone,
                    'date' => $record->created_at->format('d-m-Y'),
                    'data' => $detalle
                ];

                $record->sendPropuestaEconomicaPlanInicial($details);
            }
            
            if ($record->plan != 1) {
                
                $detalle = DB::table('detail_individual_quotes')
                    ->join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                    ->join('age_ranges', 'detail_individual_quotes.age_range_id', '=', 'age_ranges.id')
                    ->join('coverages', 'detail_individual_quotes.coverage_id', '=', 'coverages.id')
                    ->select('detail_individual_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                    ->where('individual_quote_id', $record->id)
                    ->get()
                    ->toArray();

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                // dd($details_quote[0]['plan_id']);
                $details = [
                    'plan' => $record->plan,
                    'code' => $record->code,
                    'name' => $record->full_name,
                    'email' => $record->email,
                    'phone' => $record->phone,
                    'date' => $record->created_at->format('d-m-Y'),
                    'data' => $detalle
                ];

                $record->sendPropuestaEconomicaPlanIdeal($details);

            }

            /**
             * COTIZACION MULTIPLE
             * ----------------------------------------------------------------------------------------------------
             */
            if ($record->plan == 'CM') {

                // $detalle_array_plan_incial      = [];
                // $detalle_array_plan_ideal       = [];
                // $detalle_array_plan_especial    = [];

                $group_details = [];
                $details_quote = $record->detailsQuote->toArray();

                for ($i = 0; $i < count($details_quote); $i++) {
                    if ($details_quote[$i]['plan_id'] == 1) {
                        $detalle_1 = DB::table('detail_individual_quotes')
                            ->join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_individual_quotes.age_range_id', '=', 'age_ranges.id')
                            ->select('detail_individual_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range')
                            ->where('individual_quote_id', $record->id)
                            ->where('detail_individual_quotes.plan_id', 1)
                            ->get()
                            ->toArray();

                        $details_inicial = [
                            'plan' => 1,
                            'code' => $record->code,
                            'name' => $record->full_name,
                            'email' => $record->email,
                            'phone' => $record->phone,
                            'date' => $record->created_at->format('d-m-Y'),
                            'data' => $detalle_1
                        ];

                        array_push($group_details, $details_inicial);
                    }
                    if ($details_quote[$i]['plan_id'] != 1) {
                        $detalle = DB::table('detail_individual_quotes')
                            ->join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_individual_quotes.age_range_id', '=', 'age_ranges.id')
                            ->join('coverages', 'detail_individual_quotes.coverage_id', '=', 'coverages.id')
                            ->select('detail_individual_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                            ->where('individual_quote_id', $record->id)
                            ->where('detail_individual_quotes.plan_id', $details_quote[$i]['plan_id'])
                            ->get()
                            ->toArray();

                        $details_ideal = [
                            'plan' => $details_quote[$i]['plan_id'],
                            'code' => $record->code,
                            'name' => $record->full_name,
                            'email' => $record->email,
                            'phone' => $record->phone,
                            'date' => $record->created_at->format('d-m-Y'),
                            'data' => $detalle
                        ];

                        array_push($group_details, $details_ideal);
                    } 
                }

                usort($group_details, function ($a, $b) {
                    return $a['plan'] <=> $b['plan'];
                });

                $collect_final = [];
                for ($i = 0; $i < count($group_details); $i++) {
                    if ($group_details[$i]['plan'] == 1) {
                        array_push($collect_final, $group_details[$i]);
                    }
                    if ($group_details[$i]['plan'] != 1) {
                        array_push($collect_final, $group_details[$i]);
                    }

                }

                $record->sendPropuestaEconomicaMultiple($collect_final);
            }



            
    } catch (\Throwable $th) {
            //throw $th;
        }
    }

    public static function prueba()
    {
        $pdf = Pdf::loadView('livewire.volt.individual_quote');
        $pdf->setPaper('A4', 'portrait');
        return $pdf->stream();
    }
}