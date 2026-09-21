<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SendWhiteCompanySalesReportJob;
use App\Jobs\SendWhiteCompanySalesReportWhatsAppJob;
use App\Models\WhiteCompany;
use App\Services\WhiteCompanySalesReportService;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\UserNavigationAccess;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhiteCompanySalesReportController extends Controller
{
    /**
     * Genera el PDF y devuelve su vista previa. No envía nada: el analista decide
     * después, ya con el documento a la vista.
     */
    public function preview(Request $request, WhiteCompany $whiteCompany): JsonResponse
    {
        if (! $this->userCanIssueReport()) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para generar reportes de ventas de empresas aliadas.',
            ], 403);
        }

        $validated = $request->validate([
            'from' => ['required', 'date_format:d/m/Y'],
            'to' => ['required', 'date_format:d/m/Y'],
        ]);

        try {
            $report = WhiteCompanySalesReportService::build(
                $whiteCompany,
                $validated['from'],
                $validated['to'],
            );
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pudo generar el reporte: '.$exception->getMessage(),
            ], 422);
        }

        if ($report['rows'] === []) {
            return response()->json([
                'ok' => true,
                'has_rows' => false,
                'message' => 'No hay afiliaciones activadas entre el '.$validated['from'].' y el '.$validated['to'].'.',
                'totals' => $report['totals'],
            ]);
        }

        $path = WhiteCompanySalesReportService::savePdf($report);

        WhiteCompanySalesReportService::auditIssue(
            $report,
            'administration.white-companies.sales-report.preview',
        );

        return response()->json([
            'ok' => true,
            'has_rows' => true,
            'rows_count' => count($report['rows']),
            'totals' => $report['totals'],
            'security_key' => $report['security_key'],
            'company_name' => $whiteCompany->name,
            'default_recipient' => (string) ($whiteCompany->email ?? ''),
            'default_phone' => (string) ($whiteCompany->phone ?? ''),
            'generated_by' => $report['generated_by'],
            'filename' => basename($path),
            'preview_url' => asset('storage/reportes-aliadas/'.basename($path)).'?t='.time(),
        ]);
    }

    /**
     * Envía el reporte ya revisado a los destinatarios que indique el analista.
     */
    public function send(Request $request, WhiteCompany $whiteCompany): JsonResponse
    {
        if (! $this->userCanIssueReport()) {
            return response()->json([
                'ok' => false,
                'message' => 'No tiene permiso para enviar reportes de ventas de empresas aliadas.',
            ], 403);
        }

        $validated = $request->validate([
            'from' => ['required', 'date_format:d/m/Y'],
            'to' => ['required', 'date_format:d/m/Y'],
            'recipients' => ['nullable', 'array', 'max:10'],
            'recipients.*' => ['required', 'email:rfc'],
            'phones' => ['nullable', 'array', 'max:10'],
            'phones.*' => ['required', 'string', 'min:7', 'max:20'],
        ], [
            'recipients.*.email' => 'Hay un correo con formato inválido.',
            'phones.*.min' => 'Hay un teléfono demasiado corto.',
        ]);

        $recipients = self::normalizeEmails($validated['recipients'] ?? []);
        $phones = self::normalizePhones($validated['phones'] ?? []);

        if ($recipients === [] && $phones === []) {
            return response()->json([
                'ok' => false,
                'message' => 'Indique al menos un correo o un número de WhatsApp.',
            ], 422);
        }

        $report = WhiteCompanySalesReportService::build(
            $whiteCompany,
            $validated['from'],
            $validated['to'],
        );

        if ($report['rows'] === []) {
            return response()->json([
                'ok' => false,
                'message' => 'El rango ya no tiene ventas. No se envió nada.',
            ], 422);
        }

        foreach ($recipients as $recipient) {
            SendWhiteCompanySalesReportJob::dispatch(
                (int) $whiteCompany->getKey(),
                $validated['from'],
                $validated['to'],
                $recipient,
            );
        }

        foreach ($phones as $phone) {
            SendWhiteCompanySalesReportWhatsAppJob::dispatch(
                (int) $whiteCompany->getKey(),
                $validated['from'],
                $validated['to'],
                $phone,
            );
        }

        SecurityAudit::log('AUDIT_WHITE_COMPANY_SALES_REPORT_SENT', 'administration.white-companies.sales-report.send', [
            'panel' => 'administration',
            'module' => 'white_companies',
            'white_company_id' => $whiteCompany->getKey(),
            'white_company_name' => $whiteCompany->name,
            'from' => $report['from'],
            'to' => $report['to'],
            'rows' => count($report['rows']),
            'totals' => $report['totals'],
            'security_key' => $report['security_key'],
            'recipients' => $recipients,
            'phones' => $phones,
            'sent_by' => Auth::user()?->name,
        ]);

        return response()->json([
            'ok' => true,
            'message' => self::sentMessage($recipients, $phones),
            'recipients' => $recipients,
            'phones' => $phones,
        ]);
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    private static function normalizeEmails(array $emails): array
    {
        return array_values(array_unique(array_map(
            static fn (string $email): string => strtolower(trim($email)),
            $emails,
        )));
    }

    /**
     * @param  list<string>  $phones
     * @return list<string>
     */
    private static function normalizePhones(array $phones): array
    {
        $normalized = array_map(
            static fn (string $phone): string => preg_replace('/[^0-9+]/', '', $phone) ?? '',
            $phones,
        );

        return array_values(array_unique(array_filter(
            $normalized,
            static fn (string $phone): bool => $phone !== '',
        )));
    }

    /**
     * @param  list<string>  $recipients
     * @param  list<string>  $phones
     */
    private static function sentMessage(array $recipients, array $phones): string
    {
        $parts = [];

        if ($recipients !== []) {
            $parts[] = count($recipients) === 1
                ? 'un correo ('.$recipients[0].')'
                : count($recipients).' correos';
        }

        if ($phones !== []) {
            $parts[] = count($phones) === 1
                ? 'un WhatsApp ('.$phones[0].')'
                : count($phones).' números de WhatsApp';
        }

        return 'Estado de cuenta enviado a '.implode(' y ', $parts).'.';
    }

    /**
     * Las rutas de preview/envío no pasan por un panel Filament.
     * El permiso es exclusivo de ADMINISTRACION: no debe evaluarse contra NEGOCIOS.
     */
    private function userCanIssueReport(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return UserNavigationAccess::canPerformModuleAction(
            $user,
            'ADMINISTRACION',
            BusinessFilamentActionPermissionRegistry::WHITE_COMPANY_SALES_REPORT,
        );
    }
}
