<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProspectAgent;
use App\Services\ProspectAgentFichaPdfService;
use App\Support\SecurityAudit;
use Symfony\Component\HttpFoundation\Response;

class ProspectAgentFichaPdfController extends Controller
{
    public function download(ProspectAgent $prospectAgent): Response
    {
        self::prepareLongRunningPdfResponse();

        $binary = ProspectAgentFichaPdfService::outputBinaryCached($prospectAgent);
        $filename = ProspectAgentFichaPdfService::downloadFilename($prospectAgent);

        SecurityAudit::log('AUDIT_BUSINESS_PROSPECT_AGENT_FICHA_DOWNLOADED', 'business.prospect-agents.ficha.download', [
            'prospect_agent_id' => $prospectAgent->id,
            'prospect_agent_name' => $prospectAgent->name,
            'filename' => $filename,
        ]);

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function preview(ProspectAgent $prospectAgent): Response
    {
        self::prepareLongRunningPdfResponse();

        $binary = ProspectAgentFichaPdfService::outputBinaryCached($prospectAgent);
        $filename = ProspectAgentFichaPdfService::downloadFilename($prospectAgent);

        SecurityAudit::log('AUDIT_BUSINESS_PROSPECT_AGENT_FICHA_VIEWED', 'business.prospect-agents.ficha.preview', [
            'prospect_agent_id' => $prospectAgent->id,
            'prospect_agent_name' => $prospectAgent->name,
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
