<?php

namespace App\Http\Controllers;

use App\Filament\Administration\Resources\AnnualCollections\Tables\AnnualCollectionsTable;
use App\Http\Requests\SendAvisoCobroEmailRequest;
use App\Mail\AvisoCobroCertificateEmail;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class AvisoCobroController extends Controller
{
    public function regenerateAsync(Collection $collection): JsonResponse
    {
        $ok = AnnualCollectionsTable::runRegeneratePdf($collection);

        if (! $ok) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al regenerar el PDF.',
            ], 422);
        }

        $filename = 'ADP-'.$collection->collection_invoice_number.'.pdf';
        $version = (string) time();
        $directUrl = asset('storage/avisoDeCobro/'.$filename).'?t='.$version;

        return response()->json([
            'ok' => true,
            'preview_url' => $directUrl,
            'direct_url' => $directUrl,
        ]);
    }

    public function sendEmail(SendAvisoCobroEmailRequest $request, Collection $collection): JsonResponse
    {
        $namePdf = 'ADP-'.$collection->collection_invoice_number.'.pdf';
        $path = public_path('storage/avisoDeCobro/'.$namePdf);

        if (! is_file($path)) {
            return response()->json([
                'ok' => false,
                'message' => 'No existe el PDF. Regenerelo primero.',
            ], 422);
        }

        try {
            $email = $request->validated()['email'] ?? null;
            $titular = filled($collection->affiliate_full_name) ? (string) $collection->affiliate_full_name : 'Afiliado';

            $mailable = new AvisoCobroCertificateEmail(
                titular: $titular,
                name_pdf: $namePdf,
            );
            $mailable->onQueue('default');

            if (filled($email)) {
                Mail::to($email)->queue($mailable);

                return response()->json([
                    'ok' => true,
                    'message' => 'Listo. Enviamos el documento al correo indicado. Adjuntamos el PDF en el email.',
                ]);
            }

            Mail::to($collection->affiliate_email)
                ->cc('administracion@tudrencasa.com')
                ->queue($mailable);

            return response()->json([
                'ok' => true,
                'message' => 'Listo. Enviamos el aviso de cobro al correo del afiliado. Adjuntamos el PDF en el email.',
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }
}
