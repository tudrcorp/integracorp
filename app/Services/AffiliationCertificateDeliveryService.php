<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendAffiliationCertificateDeliveryJob;
use App\Mail\AffiliationCertificateDeliveryMail;
use App\Models\AffiliationCertificateDelivery;
use App\Models\AffiliationCertificateIssue;
use App\Models\User;
use App\Support\Affiliations\Certificates\CertificateDeliveryRecipients;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Envío por correo de un certificado del generador: el documento completo o
 * solo algunos carnets. Hasta {@see self::MAX_ATTACHMENT_BYTES} va adjunto; por
 * encima (corporativos grandes), un enlace de descarga firmado que vence a los
 * {@see self::LINK_DAYS} días, porque la mayoría de los buzones rechaza adjuntos
 * de más de 10–25 MB.
 */
final class AffiliationCertificateDeliveryService
{
    /** 8 MB: con la codificación del correo (+33 %) queda bajo el límite de casi todos los buzones. */
    public const MAX_ATTACHMENT_BYTES = 8 * 1024 * 1024;

    public const LINK_DAYS = 30;

    /** Carnets por envío de «solo carnets»: ~15 hojas, se dibujan en segundos. */
    public const MAX_CARNETS_PER_DELIVERY = 60;

    public const FILE_DIRECTORY = 'certificados-generador/envios';

    public function __construct(private AffiliationCertificateGeneratorService $generator) {}

    /**
     * Registra el envío y lo pone en cola.
     *
     * @param  list<int|string>  $carnetAffiliateIds
     * @param  iterable<mixed>  $recipients
     */
    public function request(
        AffiliationCertificateIssue $issue,
        string $content,
        array $carnetAffiliateIds,
        iterable $recipients,
        ?string $message,
        User $user,
    ): AffiliationCertificateDelivery {
        $emails = CertificateDeliveryRecipients::clean($recipients);

        if ($emails === []) {
            throw new InvalidArgumentException('Indique al menos un correo válido.');
        }

        if (count($emails) > CertificateDeliveryRecipients::MAX_RECIPIENTS) {
            throw new InvalidArgumentException('Puede enviar a un máximo de '.CertificateDeliveryRecipients::MAX_RECIPIENTS.' correos por envío.');
        }

        $carnets = [];

        if ($content === AffiliationCertificateDelivery::CONTENT_FULL) {
            if ($issue->isQueued() && $issue->pdf_status !== AffiliationCertificateIssue::PDF_READY) {
                throw new InvalidArgumentException('El certificado todavía se está generando. Podrá enviarlo cuando esté listo.');
            }
        } elseif ($content === AffiliationCertificateDelivery::CONTENT_CERTIFICATE) {
            // Se dibuja aparte, sin carnets: no depende de que el completo esté listo.
        } elseif ($content === AffiliationCertificateDelivery::CONTENT_CARNETS) {
            $allowed = array_flip(array_map('intval', $issue->carnet_affiliate_ids ?? []));
            $carnets = array_values(array_filter(
                array_values(array_unique(array_map('intval', $carnetAffiliateIds))),
                static fn (int $id): bool => isset($allowed[$id]),
            ));

            if ($carnets === []) {
                throw new InvalidArgumentException('Elija al menos un carnet para enviar.');
            }

            if (count($carnets) > self::MAX_CARNETS_PER_DELIVERY) {
                throw new InvalidArgumentException('Puede enviar hasta '.self::MAX_CARNETS_PER_DELIVERY.' carnets por correo. Para más, envíe el documento completo.');
            }
        } else {
            throw new InvalidArgumentException('Elija qué desea enviar.');
        }

        $message = filled($message) ? mb_substr(trim((string) $message), 0, 2000) : null;

        $delivery = DB::transaction(function () use ($issue, $content, $carnets, $emails, $message, $user): AffiliationCertificateDelivery {
            $delivery = AffiliationCertificateDelivery::query()->create([
                'affiliation_certificate_issue_id' => $issue->id,
                'content' => $content,
                'carnet_affiliate_ids' => $carnets === [] ? null : $carnets,
                'recipients' => $emails,
                'delivered_to' => [],
                'message' => $message,
                'status' => AffiliationCertificateDelivery::STATUS_PENDING,
                'sent_by' => $user->id,
                'sent_by_name' => $user->name,
            ]);

            SendAffiliationCertificateDeliveryJob::dispatch($delivery->id)->afterCommit();

            return $delivery;
        });

        SecurityAudit::log('AUDIT_AFFILIATION_CERTIFICATE_EMAIL_REQUESTED', 'business.affiliations.certificate-generator', [
            'affiliation_certificate_delivery_id' => $delivery->id,
            'affiliation_certificate_issue_id' => $issue->id,
            'affiliation_code' => $issue->affiliation_code,
            'content' => $content,
            'carnets' => count($carnets),
            'recipients' => $emails,
        ], $user);

        return $delivery;
    }

    /**
     * Arma el archivo y envía a cada destinatario pendiente. Idempotente ante
     * reintentos: quien ya recibió el correo queda en `delivered_to` y no se le
     * repite; el archivo de carnets tampoco se vuelve a dibujar.
     */
    public function deliver(AffiliationCertificateDelivery $delivery): void
    {
        if ($delivery->status === AffiliationCertificateDelivery::STATUS_SENT) {
            return;
        }

        $issue = $delivery->issue()->firstOrFail();
        $path = $this->filePath($delivery, $issue);
        $size = (int) (@filesize($path) ?: 0);
        $method = $size > self::MAX_ATTACHMENT_BYTES
            ? AffiliationCertificateDelivery::METHOD_LINK
            : AffiliationCertificateDelivery::METHOD_ATTACHMENT;

        $delivery->forceFill([
            'delivery_method' => $method,
            'file_size' => $size,
            'link_expires_at' => $method === AffiliationCertificateDelivery::METHOD_LINK
                ? ($delivery->link_expires_at ?? now()->addDays(self::LINK_DAYS))
                : null,
            'error' => null,
        ])->save();

        $downloadUrl = $method === AffiliationCertificateDelivery::METHOD_LINK ? self::downloadUrl($delivery) : null;
        $delivered = $delivery->delivered_to ?? [];

        foreach ($delivery->recipients ?? [] as $email) {
            if (in_array($email, $delivered, true)) {
                continue;
            }

            Mail::to($email)->send(new AffiliationCertificateDeliveryMail(
                deliveryId: $delivery->id,
                affiliationCode: (string) $issue->affiliation_code,
                planName: $issue->plan_name,
                carnetsOnly: $delivery->isCarnetsOnly(),
                carnetsCount: match (true) {
                    $delivery->isCarnetsOnly() => count($delivery->carnet_affiliate_ids ?? []),
                    $delivery->isCertificateOnly() => 0,
                    default => (int) $issue->carnets_count,
                },
                analystMessage: $delivery->message,
                attachmentPath: $downloadUrl === null ? $path : null,
                attachmentName: self::fileName($delivery, $issue),
                downloadUrl: $downloadUrl,
                linkExpiresAt: $delivery->link_expires_at?->format('d/m/Y'),
            ));

            $delivered[] = $email;
            $delivery->forceFill(['delivered_to' => $delivered])->save();
        }

        $delivery->forceFill([
            'status' => AffiliationCertificateDelivery::STATUS_SENT,
            'sent_at' => now(),
        ])->save();

        // El PDF propio del envío que viajó adjunto ya no hace falta; el que va
        // por enlace se conserva hasta que el enlace venza.
        if ($delivery->usesOwnFile() && $method === AffiliationCertificateDelivery::METHOD_ATTACHMENT && filled($delivery->file_path)) {
            Storage::disk('local')->delete($delivery->file_path);
        }
    }

    /**
     * Ruta absoluta del archivo que corresponde al envío.
     */
    public function filePath(AffiliationCertificateDelivery $delivery, ?AffiliationCertificateIssue $issue = null): string
    {
        $issue ??= $delivery->issue()->firstOrFail();

        if (! $delivery->usesOwnFile()) {
            return $this->generator->pdfFile($issue);
        }

        $relative = $delivery->file_path ?: self::FILE_DIRECTORY.'/'.$issue->verification_key.'-envio-'.$delivery->id.'.pdf';
        $path = $delivery->isCarnetsOnly()
            ? $this->generator->carnetsOnlyFile($issue, $delivery->carnet_affiliate_ids ?? [], $relative)
            : $this->generator->certificateOnlyFile($issue, $relative);

        if ($delivery->file_path !== $relative) {
            $delivery->forceFill(['file_path' => $relative])->save();
        }

        return $path;
    }

    public static function downloadUrl(AffiliationCertificateDelivery $delivery): string
    {
        return URL::temporarySignedRoute(
            'affiliation-certificate.delivery.download',
            $delivery->link_expires_at ?? now()->addDays(self::LINK_DAYS),
            ['delivery' => $delivery->id],
        );
    }

    public static function fileName(AffiliationCertificateDelivery $delivery, AffiliationCertificateIssue $issue): string
    {
        $base = AffiliationCertificateGeneratorService::fileName($issue);

        return $delivery->isCarnetsOnly() ? str_replace('Certificado-', 'Carnets-', $base) : $base;
    }
}
