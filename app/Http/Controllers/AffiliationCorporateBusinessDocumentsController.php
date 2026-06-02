<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendAffiliationDocumentsEmailRequest;
use App\Mail\AffiliationDocumentsGeneratedMail;
use App\Models\AffiliationCorporate;
use App\Services\AffiliationCorporateBusinessDocumentsService;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class AffiliationCorporateBusinessDocumentsController extends Controller
{
    public function regenerateAsync(AffiliationCorporate $affiliationCorporate): JsonResponse
    {
        try {
            $result = AffiliationCorporateBusinessDocumentsService::regenerateCertificateAndTarjetas(
                $affiliationCorporate,
                Auth::id(),
            );

            SecurityAudit::log('AUDIT_AFFILIATION_CORPORATE_DOCUMENTS_REGENERATED', 'business.affiliation-corporate-documents.regenerate-async', [
                'affiliation_corporate_id' => $affiliationCorporate->id,
                'affiliation_code' => $affiliationCorporate->code,
                'queued' => (bool) ($result['queued'] ?? false),
                'task_id' => $result['task_id'] ?? null,
                'documents_count' => count($result['documents'] ?? []),
            ]);

            return response()->json([
                'ok' => true,
                ...$result,
            ]);
        } catch (\Throwable $exception) {
            SecurityAudit::log('AUDIT_AFFILIATION_CORPORATE_DOCUMENTS_REGENERATE_FAILED', 'business.affiliation-corporate-documents.regenerate-async', [
                'affiliation_corporate_id' => $affiliationCorporate->id,
                'affiliation_code' => $affiliationCorporate->code,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function status(AffiliationCorporate $affiliationCorporate, string $taskId): JsonResponse
    {
        $payload = AffiliationCorporateBusinessDocumentsService::status($taskId);

        return response()->json([
            'ok' => $payload['status'] !== 'failed',
            ...$payload,
        ], $payload['status'] === 'failed' ? 422 : 200);
    }

    public function sendEmail(
        SendAffiliationDocumentsEmailRequest $request,
        AffiliationCorporate $affiliationCorporate,
    ): JsonResponse {
        $validated = $request->validated();
        $email = $validated['email'] ?? null;

        if (blank($email)) {
            $affiliationCorporate->loadMissing('agent', 'agency');
            $email = $affiliationCorporate->agent?->email ?? $affiliationCorporate->agency?->email;
        }

        if (blank($email)) {
            SecurityAudit::log('AUDIT_AFFILIATION_CORPORATE_DOCUMENTS_EMAIL_FAILED', 'business.affiliation-corporate-documents.send-email', [
                'affiliation_corporate_id' => $affiliationCorporate->id,
                'affiliation_code' => $affiliationCorporate->code,
                'reason' => 'recipient_not_found',
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No hay correo de agente o agencia asociado. Indique un correo en el campo opcional.',
            ], 422);
        }

        $paths = AffiliationCorporateBusinessDocumentsService::absolutePdfPathsForAffiliation($affiliationCorporate);

        if ($paths === []) {
            SecurityAudit::log('AUDIT_AFFILIATION_CORPORATE_DOCUMENTS_EMAIL_FAILED', 'business.affiliation-corporate-documents.send-email', [
                'affiliation_corporate_id' => $affiliationCorporate->id,
                'affiliation_code' => $affiliationCorporate->code,
                'recipient_email' => $email,
                'reason' => 'documents_not_found',
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se encontraron PDF. Use primero la vista previa para generar los documentos.',
            ], 422);
        }

        $affiliationCorporate->loadMissing('agent', 'agency');
        $titular = filled($affiliationCorporate->name_corporate)
            ? (string) $affiliationCorporate->name_corporate
            : (string) $affiliationCorporate->code;
        $recipientName = (string) ($affiliationCorporate->agent?->name ?? $affiliationCorporate->agency?->name_corporative ?? 'Aliado estratégico');

        $mailable = new AffiliationDocumentsGeneratedMail(
            titular: $titular,
            attachmentPaths: $paths,
            recipientName: $recipientName,
        );
        $mailable->onQueue('default');

        try {
            Mail::to($email)
                ->cc('afiliaciones@tudrencasa.com')
                ->queue($mailable);

            SecurityAudit::log('AUDIT_AFFILIATION_CORPORATE_DOCUMENTS_EMAIL_SENT', 'business.affiliation-corporate-documents.send-email', [
                'affiliation_corporate_id' => $affiliationCorporate->id,
                'affiliation_code' => $affiliationCorporate->code,
                'recipient_email' => $email,
                'attachments_count' => count($paths),
            ]);
        } catch (\Throwable $exception) {
            SecurityAudit::log('AUDIT_AFFILIATION_CORPORATE_DOCUMENTS_EMAIL_FAILED', 'business.affiliation-corporate-documents.send-email', [
                'affiliation_corporate_id' => $affiliationCorporate->id,
                'affiliation_code' => $affiliationCorporate->code,
                'recipient_email' => $email,
                'reason' => 'queue_failed',
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No pudimos encolar el correo en este momento. Intente de nuevo.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Listo. Enviamos los documentos al correo indicado (copia a afiliaciones@tudrencasa.com).',
        ]);
    }
}
