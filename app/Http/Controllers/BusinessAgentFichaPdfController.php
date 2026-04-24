<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\AgentFichaPdfService;
use App\Support\BusinessAgentFichaPdfAccess;
use App\Support\SecurityAudit;
use Symfony\Component\HttpFoundation\Response;

class BusinessAgentFichaPdfController extends Controller
{
    public function preview(Agent $agent): Response
    {
        self::prepareLongRunningPdfResponse();

        if (! BusinessAgentFichaPdfAccess::userCanAccess($agent)) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENT_FICHA_ACCESS_DENIED', 'business.agents.ficha-pdf.preview', [
                'agent_id' => $agent->getKey(),
                'reason' => 'forbidden',
            ]);

            abort(403);
        }

        $pdf = AgentFichaPdfService::make($agent);
        $filename = AgentFichaPdfService::filename($agent);

        SecurityAudit::log('AUDIT_BUSINESS_AGENT_FICHA_VIEWED', 'business.agents.ficha-pdf.preview', [
            'agent_id' => $agent->getKey(),
            'agent_name' => $agent->name,
            'filename' => $filename,
        ]);

        return $pdf->stream($filename);
    }

    public function download(Agent $agent): Response
    {
        self::prepareLongRunningPdfResponse();

        if (! BusinessAgentFichaPdfAccess::userCanAccess($agent)) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENT_FICHA_ACCESS_DENIED', 'business.agents.ficha-pdf.download', [
                'agent_id' => $agent->getKey(),
                'reason' => 'forbidden',
            ]);

            abort(403);
        }

        $pdf = AgentFichaPdfService::make($agent);
        $filename = AgentFichaPdfService::filename($agent);

        SecurityAudit::log('AUDIT_BUSINESS_AGENT_FICHA_DOWNLOADED', 'business.agents.ficha-pdf.download', [
            'agent_id' => $agent->getKey(),
            'agent_name' => $agent->name,
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
