<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CrmHandoffAttachment;
use App\Models\User;
use App\Support\CrmInbox\CrmHandoffMedia;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Abre o descarga un archivo del cliente desde la bandeja. Imagen, PDF y audio se muestran en el navegador;
 * el resto se descarga. El archivo nunca se sirve como página: va con su tipo real y aislado (sandbox).
 */
class CrmHandoffAttachmentController extends Controller
{
    private const INLINE = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/aac'];

    public function __invoke(Request $request, int $attachment): StreamedResponse
    {
        $user = $request->user();
        $file = CrmHandoffAttachment::query()->find($attachment);

        abort_if($file === null, 404);
        abort_unless($user instanceof User && CrmHandoffMedia::canView($user, $file), 403);
        abort_if($file->isPurged(), 410, 'Este archivo ya no está disponible: pasó el tiempo de resguardo.');

        $disk = Storage::disk((string) $file->disk);

        abort_unless($disk->exists((string) $file->path), 404);

        $download = $request->boolean('descargar') || ! in_array($file->mime_type, self::INLINE, true);

        if ($download || ! $request->hasHeader('Range')) {
            SecurityAudit::log('AUDIT_CRM_ATTACHMENT_OPENED', 'crm.attachments.show', [
                'attachment_id' => $file->id,
                'handoff_id' => $file->handoff_id,
                'media_type' => $file->media_type,
                'download' => $download,
            ], $user);
        }

        $headers = [
            'Content-Type' => (string) $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, max-age=600',
        ];

        return $download
            ? $disk->download((string) $file->path, (string) $file->original_name, $headers)
            : $disk->response((string) $file->path, (string) $file->original_name, $headers);
    }
}
