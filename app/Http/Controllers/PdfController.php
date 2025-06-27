<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfController extends Controller
{
    public function generatePdf()
    {
        $pdf = Pdf::loadView('documents.certificate');
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
}