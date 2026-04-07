<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendAffiliationDocumentsEmailRequest;
use App\Mail\AffiliationDocumentsGeneratedMail;
use App\Models\Affiliation;
use App\Services\AffiliationBusinessDocumentsService;
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

            return response()->json([
                'ok' => true,
                'documents' => $result['documents'],
            ]);
        } catch (\Throwable $e) {
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
            return response()->json([
                'ok' => false,
                'message' => 'No hay correo de agente o agencia asociado. Indique un correo en el campo opcional.',
            ], 422);
        }

        $paths = AffiliationBusinessDocumentsService::absolutePdfPathsForAffiliation($affiliation);

        if ($paths === []) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontraron PDF. Use primero la vista previa para generar los documentos.',
            ], 422);
        }

        $titular = filled($affiliation->full_name_ti) ? (string) $affiliation->full_name_ti : (string) $affiliation->code;

        $mailable = new AffiliationDocumentsGeneratedMail(
            titular: $titular,
            attachmentPaths: $paths,
        );
        $mailable->onQueue('default');

        Mail::to($email)
            ->cc('afiliaciones@tudrencasa.com')
            ->queue($mailable);

        return response()->json([
            'ok' => true,
            'message' => 'Listo. Enviamos los documentos al correo indicado (copia a afiliaciones@tudrencasa.com).',
        ]);
    }
}
