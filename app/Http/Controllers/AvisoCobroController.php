<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegenerateAvisoCobroRequest;
use App\Http\Requests\SendAvisoCobroEmailRequest;
use App\Mail\AvisoCobroCertificateEmail;
use App\Models\Collection;
use App\Support\Collections\CollectionNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class AvisoCobroController extends Controller
{
    /**
     * Regenera el aviso con lo que editó el analista (o con los datos por defecto) y devuelve la vista previa.
     */
    public function regenerateAsync(RegenerateAvisoCobroRequest $request, Collection $collection): JsonResponse
    {
        try {
            $regeneration = app(CollectionNotice::class)->regenerate($collection, $request->edits(), $request->user());
        } catch (ValidationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => collect($exception->errors())->flatten()->first() ?? 'Revise los datos del aviso.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo regenerar el PDF. El aviso anterior no cambió; intente de nuevo.',
            ], 500);
        }

        $directUrl = asset('storage/avisoDeCobro/'.CollectionNotice::pdfFilename($collection)).'?t='.$regeneration->id;

        return response()->json([
            'ok' => true,
            'preview_url' => $directUrl,
            'direct_url' => $directUrl,
            'regenerated_at' => $regeneration->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
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
