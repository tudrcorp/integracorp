<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CollectionController extends Controller
{
    //
    public static function regenerateAvisoDeCobro($data)
    {

        try {
            
            ini_set('memory_limit', '2048M');

            $name_pdf = 'ADP-' . $data['invoice_number'] . '.pdf';

            $pdf = Pdf::loadView('documents.aviso-de-cobro', compact('data'));
            $pdf->save(public_path('storage/avisoDeCobro/' . $name_pdf));

            Log::info('AVISO DE COBRO REGENERADO' . $name_pdf);

            return true;

        } catch (\Throwable $th) {

            Log::error('AVISO DE COBRO NO REGENERADO' . $name_pdf . ' ' . $th->getMessage());
            return false;
        }
        
    }

    public static function regenerateAvisoDeCobroCorporate($data)
    {

        try {

            ini_set('memory_limit', '2048M');

            $name_pdf = 'ADP-' . $data['invoice_number'] . '.pdf';

            $pdf = Pdf::loadView('documents.aviso-de-cobro-corporativo', compact('data'));
            $pdf->save(public_path('storage/avisoDeCobro/' . $name_pdf));

            Log::info('AVISO DE COBRO CORPORATIVO REGENERADO' . $name_pdf);

            return true;
        } catch (\Throwable $th) {
            dd($th);
            Log::error('AVISO DE COBRO CORPORATIVO NO REGENERADO' . $name_pdf . ' ' . $th->getMessage());
            return false;
        }
    }
}
