<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TelemedicinePatient;
use App\Services\PatientDetailedSiniestralidadReportService;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Symfony\Component\HttpFoundation\Response;

final class TelemedicinePatientDetailedSiniestralidadReportController extends Controller
{
    public function preview(TelemedicinePatient $patient): Response
    {
        return $this->respond($patient, inline: true);
    }

    public function download(TelemedicinePatient $patient): Response
    {
        return $this->respond($patient, inline: false);
    }

    private function respond(TelemedicinePatient $patient, bool $inline): Response
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        if (! OperationsSupplierScope::authenticatedUserIsTdgAnalyst()) {
            abort(403, 'Solo analistas TDG pueden generar el reporte detallado de siniestralidad.');
        }

        $binary = PatientDetailedSiniestralidadReportService::makePdf($patient)->output();
        $filename = PatientDetailedSiniestralidadReportService::filename($patient);
        $disposition = $inline ? 'inline' : 'attachment';

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', $disposition.'; filename="'.$filename.'"');
    }
}
