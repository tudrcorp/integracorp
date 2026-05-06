<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DoctorNurse;
use App\Services\DoctorNurseFichaPdfService;
use App\Support\SecurityAudit;
use Symfony\Component\HttpFoundation\Response;

class DoctorNurseFichaPdfController extends Controller
{
    public function download(DoctorNurse $doctorNurse): Response
    {
        self::prepareLongRunningPdfResponse();

        $binary = DoctorNurseFichaPdfService::outputBinaryCached($doctorNurse);
        $filename = DoctorNurseFichaPdfService::downloadFilename($doctorNurse);

        SecurityAudit::log('AUDIT_OPERATIONS_DOCTOR_NURSE_FICHA_DOWNLOADED', 'operations.doctor-nurses.ficha.download', [
            'doctor_nurse_id' => $doctorNurse->id,
            'doctor_nurse_name' => $doctorNurse->name,
            'filename' => $filename,
        ]);

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function preview(DoctorNurse $doctorNurse): Response
    {
        self::prepareLongRunningPdfResponse();

        $binary = DoctorNurseFichaPdfService::outputBinaryCached($doctorNurse);
        $filename = DoctorNurseFichaPdfService::downloadFilename($doctorNurse);

        SecurityAudit::log('AUDIT_OPERATIONS_DOCTOR_NURSE_FICHA_VIEWED', 'operations.doctor-nurses.ficha.preview', [
            'doctor_nurse_id' => $doctorNurse->id,
            'doctor_nurse_name' => $doctorNurse->name,
            'filename' => $filename,
        ]);

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    private static function prepareLongRunningPdfResponse(): void
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $limit = config('supplier-report.pdf_memory_limit');
        if (is_string($limit) && $limit !== '' && $limit !== '0') {
            @ini_set('memory_limit', $limit);
        }
    }
}
