<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PlanGenerator;
use App\Services\PlanGeneratorPdfService;
use App\Support\PlanGeneratorPdfAccess;
use App\Support\SecurityAudit;
use Symfony\Component\HttpFoundation\Response;

class BusinessPlanGeneratorPdfController extends Controller
{
    public function preview(PlanGenerator $planGenerator): Response
    {
        self::prepareLongRunningPdfResponse();

        if (! PlanGeneratorPdfAccess::userCanAccess()) {
            SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_PDF_ACCESS_DENIED', 'business.plan-generators.pdf.preview', [
                'plan_generator_id' => $planGenerator->getKey(),
                'reason' => 'forbidden',
            ]);

            abort(403);
        }

        $pdf = PlanGeneratorPdfService::make($planGenerator);
        $filename = PlanGeneratorPdfService::filename($planGenerator);

        SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_PDF_VIEWED', 'business.plan-generators.pdf.preview', [
            'plan_generator_id' => $planGenerator->getKey(),
            'plan_name' => $planGenerator->name,
            'filename' => $filename,
        ]);

        return $pdf->stream($filename);
    }

    public function download(PlanGenerator $planGenerator): Response
    {
        self::prepareLongRunningPdfResponse();

        if (! PlanGeneratorPdfAccess::userCanAccess()) {
            SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_PDF_ACCESS_DENIED', 'business.plan-generators.pdf.download', [
                'plan_generator_id' => $planGenerator->getKey(),
                'reason' => 'forbidden',
            ]);

            abort(403);
        }

        $pdf = PlanGeneratorPdfService::make($planGenerator);
        $filename = PlanGeneratorPdfService::filename($planGenerator);

        SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_PDF_DOWNLOADED', 'business.plan-generators.pdf.download', [
            'plan_generator_id' => $planGenerator->getKey(),
            'plan_name' => $planGenerator->name,
            'filename' => $filename,
        ]);

        return $pdf->download($filename);
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
