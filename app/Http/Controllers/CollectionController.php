<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class CollectionController extends Controller
{
    //
    public static function regenerateAvisoDeCobro($data)
    {

        try {

            ini_set('memory_limit', '2048M');

            $name_pdf = 'ADP-'.$data['invoice_number'].'.pdf';

            $pdf = Pdf::loadView('documents.aviso-de-cobro', compact('data'));
            $pdf->save(public_path('storage/avisoDeCobro/'.$name_pdf));

            Log::info('AVISO DE COBRO REGENERADO'.$name_pdf);

            return true;

        } catch (\Throwable $th) {

            Log::error('AVISO DE COBRO NO REGENERADO'.$name_pdf.' '.$th->getMessage());

            return false;
        }

    }

    public static function regenerateAvisoDeCobroCorporate($data)
    {

        try {

            ini_set('memory_limit', '2048M');

            $name_pdf = 'ADP-'.$data['invoice_number'].'.pdf';

            $pdf = Pdf::loadView('documents.aviso-de-cobro-corporativo', compact('data'));
            $pdf->save(public_path('storage/avisoDeCobro/'.$name_pdf));

            Log::info('AVISO DE COBRO CORPORATIVO REGENERADO'.$name_pdf);

            return true;
        } catch (\Throwable $th) {
            Log::error('AVISO DE COBRO CORPORATIVO NO REGENERADO '.$name_pdf.' '.$th->getMessage());

            return false;
        }
    }

    /**
     * Genera el PDF del aviso de cobro y lo devuelve para descarga/vista previa (síncrono).
     */
    public static function generateAndDownloadAvisoDeCobro(\App\Models\Collection $collection)
    {
        ini_set('memory_limit', '2048M');

        $name_pdf = 'ADP-'.$collection->collection_invoice_number.'.pdf';

        if ($collection->type === 'AFILIACION CORPORATIVA') {
            $address = \App\Models\AffiliationCorporate::where('code', $collection->affiliation_code)->first();
            $planes = \App\Models\AffiliationCorporate::where('code', $collection->affiliation_code)
                ->with('affiliationCorporatePlans')
                ->first()
                ?->toArray();
            $array_data = [
                'invoice_number' => $collection->collection_invoice_number,
                'emission_date' => $collection->next_payment_date,
                'full_name_ti' => $collection->affiliate_full_name,
                'ci_rif_ti' => $collection->affiliate_ci_rif,
                'address_ti' => $address?->adress_ti ?? '',
                'phone_ti' => $collection->affiliate_phone,
                'email_ti' => $collection->affiliate_email,
                'total_amount' => $collection->total_amount,
                'plan' => $planes['affiliation_corporate_plans'] ?? [],
                'coverage' => $collection->coverage?->price,
                'frequency' => $collection->payment_frequency,
            ];
            $view = 'documents.aviso-de-cobro-corporativo';
        } else {
            $address = \App\Models\Affiliation::where('code', $collection->affiliation_code)->first();
            $array_data = [
                'invoice_number' => $collection->collection_invoice_number,
                'emission_date' => $collection->next_payment_date,
                'full_name_ti' => $collection->affiliate_full_name,
                'ci_rif_ti' => $collection->affiliate_ci_rif,
                'address_ti' => $address?->adress_ti ?? '',
                'phone_ti' => $collection->affiliate_phone,
                'email_ti' => $collection->affiliate_email,
                'total_amount' => $collection->total_amount,
                'plan' => $collection->plan?->description,
                'coverage' => $collection->coverage?->price,
                'frequency' => $collection->payment_frequency,
            ];
            $view = 'documents.aviso-de-cobro';
        }

        $pdf = Pdf::loadView($view, ['data' => $array_data]);
        $pdf->save(public_path('storage/avisoDeCobro/'.$name_pdf));

        return response()->download(public_path('storage/avisoDeCobro/'.$name_pdf), $name_pdf, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }
}
