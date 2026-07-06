<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendAffiliationDocumentsEmailRequest;
use App\Mail\AffiliationDocumentsGeneratedMail;
use App\Models\Affiliation;
use App\Services\AffiliationBusinessDocumentsService;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class AffiliationBusinessDocumentsController extends Controller
{
    public function regenerateAsync(Affiliation $affiliation): JsonResponse
    {
        try {
            $userId = auth()->id();
            $result = AffiliationBusinessDocumentsService::regenerateCertificateAndTarjetas(
                $affiliation,
                $userId,
                notifyCertificate: false,
            );

            SecurityAudit::log('AUDIT_AFFILIATION_DOCUMENTS_REGENERATED', 'business.affiliation-documents.regenerate-async', [
                'affiliation_id' => $affiliation->id,
                'affiliation_code' => $affiliation->code,
                'documents_count' => count($result['documents'] ?? []),
                'queued' => false,
            ]);

            return response()->json([
                'ok' => true,
                'documents' => $result['documents'],
            ]);
        } catch (\Throwable $e) {
            SecurityAudit::log('AUDIT_AFFILIATION_DOCUMENTS_REGENERATE_FAILED', 'business.affiliation-documents.regenerate-async', [
                'affiliation_id' => $affiliation->id,
                'affiliation_code' => $affiliation->code,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function sendEmail(SendAffiliationDocumentsEmailRequest $request, Affiliation $affiliation): JsonResponse
    {
        $validated = $request->validated();
        $email = $validated['email'] ?? null;

        if (blank($email)) {
            $affiliation->loadMissing('agent', 'agency');
            $email = $affiliation->agent?->email ?? $affiliation->agency?->email;
        }

        if (blank($email)) {
            SecurityAudit::log('AUDIT_AFFILIATION_DOCUMENTS_EMAIL_FAILED', 'business.affiliation-documents.send-email', [
                'affiliation_id' => $affiliation->id,
                'affiliation_code' => $affiliation->code,
                'reason' => 'recipient_not_found',
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No hay correo de agente o agencia asociado. Indique un correo en el campo opcional.',
            ], 422);
        }

        $paths = AffiliationBusinessDocumentsService::absolutePdfPathsForAffiliation($affiliation);

        if ($paths === []) {
            SecurityAudit::log('AUDIT_AFFILIATION_DOCUMENTS_EMAIL_FAILED', 'business.affiliation-documents.send-email', [
                'affiliation_id' => $affiliation->id,
                'affiliation_code' => $affiliation->code,
                'recipient_email' => $email,
                'reason' => 'documents_not_found',
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se encontraron PDF. Use primero la vista previa para generar los documentos.',
            ], 422);
        }

        $affiliation->loadMissing('agent', 'agency');
        $titular = filled($affiliation->full_name_ti) ? (string) $affiliation->full_name_ti : (string) $affiliation->code;
        $recipientName = (string) ($affiliation->agent?->name ?? $affiliation->agency?->name_corporative ?? 'Aliado estratégico');

        $mailable = new AffiliationDocumentsGeneratedMail(
            titular: $titular,
            attachmentPaths: $paths,
            recipientName: $recipientName,
        );
        $mailable->onQueue('default');

        try {
            Mail::to($email)
                ->cc([
                    'afiliaciones@tudrencasa.com',
                    'solrodriguez@tudrencasa.com',
                ])
                ->queue($mailable);

            SecurityAudit::log('AUDIT_AFFILIATION_DOCUMENTS_EMAIL_SENT', 'business.affiliation-documents.send-email', [
                'affiliation_id' => $affiliation->id,
                'affiliation_code' => $affiliation->code,
                'recipient_email' => $email,
                'attachments_count' => count($paths),
            ]);
        } catch (\Throwable $exception) {
            SecurityAudit::log('AUDIT_AFFILIATION_DOCUMENTS_EMAIL_FAILED', 'business.affiliation-documents.send-email', [
                'affiliation_id' => $affiliation->id,
                'affiliation_code' => $affiliation->code,
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
            'message' => 'Listo. Enviamos los documentos al correo indicado (copia a afiliaciones@tudrencasa.com y solrodriguez@tudrencasa.com).',
        ]);
    }
}
