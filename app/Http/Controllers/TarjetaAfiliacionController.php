<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class TarjetaAfiliacionController extends Controller
{
    public static function generateTarjetaAfiliacion($data) {
        // dd($data);
        try {

            ini_set('memory_limit', '2048M');
            set_time_limit(120);
            
            $pdf = Pdf::loadView('documents.tarjeta-afiliado', compact('data'));
            $name_pdf = 'TAR-' . $data['code'] . '.pdf';
            $pdf->save(public_path('storage/tarjeta-afiliacion/' . $name_pdf));

            return true;
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return 'Error al generar la tarjeta de afiliación';
        }
    }
}