<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegenerateAvisoCobroRequest;
use App\Http\Requests\SendAvisoCobroEmailRequest;
use App\Models\Collection;
use App\Support\Collections\CollectionNotice;
use App\Support\Collections\CollectionNoticeEmailDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;
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

        try {
            $emailOptions = CollectionNoticeEmailDelivery::options($collection, $regeneration);
        } catch (Throwable $exception) {
            report($exception);
            $emailOptions = null;
        }

        return response()->json([
            'ok' => true,
            'preview_url' => $directUrl,
            'direct_url' => $directUrl,
            'email_options' => $emailOptions,
            'regenerated_at' => $regeneration->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Envía el aviso ya regenerado con el copy elegido para el afiliado y para el agente / agencia.
     */
    public function sendEmail(SendAvisoCobroEmailRequest $request, Collection $collection): JsonResponse
    {
        $validated = $request->validated();
        $testMode = filled($validated['test_email'] ?? null);

        try {
            $deliveries = CollectionNoticeEmailDelivery::deliver(
                $collection,
                affiliateTemplateId: isset($validated['affiliate_template_id']) ? (int) $validated['affiliate_template_id'] : null,
                responsibleTemplateId: isset($validated['responsible_template_id']) ? (int) $validated['responsible_template_id'] : null,
                attachPaymentMethods: (bool) ($validated['attach_payment_methods'] ?? false),
                testMode: $testMode,
                testEmail: $testMode ? (string) $validated['test_email'] : null,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo enviar el aviso. Intente de nuevo; si persiste, avise a Sistemas.',
            ], 500);
        }

        $sentTo = collect($deliveries)->flatMap(fn (array $delivery): array => $delivery['to'])->unique()->implode(', ');

        return response()->json([
            'ok' => true,
            'message' => $testMode
                ? 'Prueba enviada a '.$sentTo.': '.count($deliveries).' '.(count($deliveries) === 1 ? 'correo' : 'correos').', uno por copy elegido. No se notificó a nadie más.'
                : 'Aviso enviado a '.$sentTo.'. Los testigos configurados reciben su copia. Llegará en unos instantes.',
            'deliveries' => $deliveries,
        ]);
    }
}
