<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendAffiliationDocumentsEmailRequest;
use App\Mail\AffiliationDocumentsGeneratedMail;
use App\Models\AffiliationCorporate;
use App\Services\AffiliationCorporateBusinessDocumentsService;
use App\Support\AffiliateCard\AffiliateCarnetEmailDispatchService;
use App\Support\Affiliations\AffiliationJobFailureLogger;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * Carnets individuales paginados para el buscador del modal. Se sirven aparte
     * para que la respuesta de estado no crezca con miles de documentos.
     */
    public function tarjetas(Request $request, AffiliationCorporate $affiliationCorporate): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $result = AffiliationCorporateBusinessDocumentsService::paginatedTarjetaDocuments(
            $affiliationCorporate,
            (string) ($validated['q'] ?? ''),
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 20),
        );

        return response()->json([
            'ok' => true,
            ...$result,
        ]);
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
                ->bcc('solrodriguez@tudrencasa.com')
                ->queue($mailable);

            SecurityAudit::log('AUDIT_AFFILIATION_CORPORATE_DOCUMENTS_EMAIL_SENT', 'business.affiliation-corporate-documents.send-email', [
                'affiliation_corporate_id' => $affiliationCorporate->id,
                'affiliation_code' => $affiliationCorporate->code,
                'recipient_email' => $email,
                'attachments_count' => count($paths),
            ]);
        } catch (\Throwable $exception) {
            AffiliationJobFailureLogger::dispatchFailed(AffiliationDocumentsGeneratedMail::class, $exception, [
                'action' => 'send-documents-email',
                'affiliation_corporate_id' => $affiliationCorporate->id,
                'affiliation_code' => $affiliationCorporate->code,
                'recipient_email' => $email,
                'attachments_count' => count($paths),
            ]);

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
            'message' => 'Listo. Enviamos los documentos al correo indicado (copia a afiliaciones@tudrencasa.com; copia oculta a solrodriguez@tudrencasa.com).',
        ]);
    }

    public function sendCarnetEmails(AffiliationCorporate $affiliationCorporate): JsonResponse
    {
        $userId = Auth::id();

        if ($userId === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Debe iniciar sesión para enviar los carnets.',
            ], 401);
        }

        $result = AffiliateCarnetEmailDispatchService::queueForCorporate($affiliationCorporate, (int) $userId);

        if (! $result['ok']) {
            SecurityAudit::log('AUDIT_AFFILIATION_CORPORATE_CARNET_EMAILS_FAILED', 'business.affiliation-corporate-documents.send-carnet-emails', [
                'affiliation_corporate_id' => $affiliationCorporate->id,
                'affiliation_code' => $affiliationCorporate->code,
                'skipped' => $result['skipped'],
                'reason' => $result['message'],
            ]);

            return response()->json([
                'ok' => false,
                'message' => $result['message'],
            ], 422);
        }

        SecurityAudit::log('AUDIT_AFFILIATION_CORPORATE_CARNET_EMAILS_QUEUED', 'business.affiliation-corporate-documents.send-carnet-emails', [
            'affiliation_corporate_id' => $affiliationCorporate->id,
            'affiliation_code' => $affiliationCorporate->code,
            'queued' => $result['queued'],
            'skipped' => $result['skipped'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => $result['message'],
            'queued' => $result['queued'],
            'skipped' => $result['skipped'],
        ]);
    }
}
