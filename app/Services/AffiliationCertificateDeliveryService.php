<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendAffiliationCertificateDeliveryJob;
use App\Mail\AffiliationCertificateDeliveryMail;
use App\Models\AffiliationCertificateDelivery;
use App\Models\AffiliationCertificateIssue;
use App\Models\User;
use App\Support\Affiliations\Certificates\CertificateDeliveryParts;
use App\Support\Affiliations\Certificates\CertificateDeliveryRecipients;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Envío por correo de un certificado del generador: el documento completo o
 * solo las partes elegidas (certificado, lista de afiliados y/o carnets,
 * todos o algunos). Hasta {@see self::MAX_ATTACHMENT_BYTES} va adjunto; por
 * encima (corporativos grandes), un enlace de descarga firmado que vence a los
 * {@see self::LINK_DAYS} días, porque la mayoría de los buzones rechaza adjuntos
 * de más de 10–25 MB.
 *
 * Cada destinatario recibe su propio correo. Los testigos (CC) y las copias
 * ocultas (CCO, siempre con {@see CertificateDeliveryRecipients::MANDATORY_BCC})
 * viajan una sola vez, en el primer correo que sale, para que no reciban una
 * copia por cada destinatario.
 */
final class AffiliationCertificateDeliveryService
{
    /** 8 MB: con la codificación del correo (+33 %) queda bajo el límite de casi todos los buzones. */
    public const MAX_ATTACHMENT_BYTES = 8 * 1024 * 1024;

    public const LINK_DAYS = 30;

    /** Carnets elegidos uno a uno por envío: ~15 hojas, se dibujan en segundos. */
    public const MAX_CARNETS_PER_DELIVERY = 60;

    public const FILE_DIRECTORY = 'certificados-generador/envios';

    public function __construct(private AffiliationCertificateGeneratorService $generator) {}

    /**
     * Registra el envío y lo pone en cola.
     *
     * `$what` son las partes ({@see CertificateDeliveryParts}) o, por compatibilidad,
     * un `CONTENT_*`. Con carnets, `$carnetAffiliateIds` nulo envía todos los de la
     * emisión y una lista, solo esos.
     *
     * @param  string|list<string>  $what
     * @param  list<int|string>|null  $carnetAffiliateIds
     * @param  iterable<mixed>  $recipients
     * @param  iterable<mixed>  $ccRecipients  correos testigos (copia visible)
     * @param  iterable<mixed>  $bccRecipients  copias ocultas adicionales a la fija
     */
    public function request(
        AffiliationCertificateIssue $issue,
        string|array $what,
        ?array $carnetAffiliateIds,
        iterable $recipients,
        ?string $message,
        User $user,
        iterable $ccRecipients = [],
        iterable $bccRecipients = [],
    ): AffiliationCertificateDelivery {
        $emails = CertificateDeliveryRecipients::clean($recipients);

        if ($emails === []) {
            throw new InvalidArgumentException('Indique al menos un correo válido.');
        }

        if (count($emails) > CertificateDeliveryRecipients::MAX_RECIPIENTS) {
            throw new InvalidArgumentException('Puede enviar a un máximo de '.CertificateDeliveryRecipients::MAX_RECIPIENTS.' correos por envío.');
        }

        $copies = CertificateDeliveryRecipients::copies($emails, $ccRecipients, $bccRecipients);

        if (count($copies['cc']) > CertificateDeliveryRecipients::MAX_COPIES) {
            throw new InvalidArgumentException('Puede agregar hasta '.CertificateDeliveryRecipients::MAX_COPIES.' correos testigos (CC) por envío.');
        }

        if (count($copies['bcc']) > CertificateDeliveryRecipients::MAX_COPIES) {
            throw new InvalidArgumentException('Puede agregar hasta '.CertificateDeliveryRecipients::MAX_COPIES.' copias ocultas (CCO) por envío, contando la fija.');
        }

        [$parts, $carnetAffiliateIds] = self::resolveParts($issue, $what, $carnetAffiliateIds);

        if ($parts === []) {
            throw new InvalidArgumentException('Elija qué desea enviar.');
        }

        $carnets = [];
        $allCarnets = array_values(array_map('intval', $issue->carnet_affiliate_ids ?? []));

        if (in_array(CertificateDeliveryParts::CARNETS, $parts, true)) {
            if ($carnetAffiliateIds === null) {
                $carnets = $allCarnets;
            } else {
                $allowed = array_flip($allCarnets);
                $carnets = array_values(array_filter(
                    array_values(array_unique(array_map('intval', $carnetAffiliateIds))),
                    static fn (int $id): bool => isset($allowed[$id]),
                ));

                if ($carnets === []) {
                    throw new InvalidArgumentException('Elija al menos un carnet para enviar.');
                }

                if (count($carnets) > self::MAX_CARNETS_PER_DELIVERY) {
                    throw new InvalidArgumentException('Puede elegir hasta '.self::MAX_CARNETS_PER_DELIVERY.' carnets uno a uno. Para más, marque «Todos los carnets».');
                }
            }
        }

        // Todas las partes con todos los carnets es el documento ya dibujado de la emisión.
        $isFull = $parts === CertificateDeliveryParts::availableFor($issue) && array_diff($allCarnets, $carnets) === [];
        $content = $isFull ? AffiliationCertificateDelivery::CONTENT_FULL : AffiliationCertificateDelivery::CONTENT_CUSTOM;

        if ($isFull) {
            if ($issue->isQueued() && $issue->pdf_status !== AffiliationCertificateIssue::PDF_READY) {
                throw new InvalidArgumentException('El certificado todavía se está generando. Podrá enviarlo cuando esté listo.');
            }

            $carnets = [];
        }

        $message = filled($message) ? mb_substr(trim((string) $message), 0, 2000) : null;

        $delivery = DB::transaction(function () use ($issue, $content, $parts, $carnets, $emails, $copies, $message, $user): AffiliationCertificateDelivery {
            $delivery = AffiliationCertificateDelivery::query()->create([
                'affiliation_certificate_issue_id' => $issue->id,
                'content' => $content,
                'parts' => $parts,
                'carnet_affiliate_ids' => $carnets === [] ? null : $carnets,
                'recipients' => $emails,
                'cc_recipients' => $copies['cc'],
                'bcc_recipients' => $copies['bcc'],
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
            'parts' => $parts,
            'carnets' => count($carnets),
            'recipients' => $emails,
            'cc' => $copies['cc'],
            'bcc' => $copies['bcc'],
        ], $user);

        return $delivery;
    }

    /**
     * Arma el archivo y envía a cada destinatario pendiente. Idempotente ante
     * reintentos: quien ya recibió el correo queda en `delivered_to` y no se le
     * repite; las copias (CC/CCO) van en el primer correo que sale y quedan
     * marcadas en `copies_sent_at`; el archivo de carnets tampoco se vuelve a dibujar.
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

            $withCopies = $delivery->copies_sent_at === null;
            $pending = Mail::to($email);

            if ($withCopies) {
                $cc = $delivery->cc_recipients ?? [];
                // Un envío registrado antes de existir las copias igual lleva la oculta fija.
                $bcc = $delivery->bcc_recipients
                    ?? CertificateDeliveryRecipients::copies($delivery->recipients ?? [], $cc, [])['bcc'];

                if ($cc !== []) {
                    $pending->cc($cc);
                }

                if ($bcc !== []) {
                    $pending->bcc($bcc);
                }
            }

            $pending->send(new AffiliationCertificateDeliveryMail(
                deliveryId: $delivery->id,
                affiliationCode: (string) $issue->affiliation_code,
                planName: $issue->plan_name,
                carnetsOnly: $delivery->isCarnetsOnly(),
                carnetsCount: self::carnetsCount($delivery, $issue),
                analystMessage: $delivery->message,
                attachmentPath: $downloadUrl === null ? $path : null,
                attachmentName: self::fileName($delivery, $issue),
                downloadUrl: $downloadUrl,
                linkExpiresAt: $delivery->link_expires_at?->format('d/m/Y'),
                parts: $delivery->documentParts(),
            ));

            $delivered[] = $email;
            $delivery->forceFill([
                'delivered_to' => $delivered,
                'copies_sent_at' => $withCopies ? now() : $delivery->copies_sent_at,
            ])->save();
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
        $path = $this->generator->partialFile($issue, $delivery->documentParts(), $delivery->carnet_affiliate_ids ?? [], $relative);

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

        return match ($delivery->documentParts()) {
            [CertificateDeliveryParts::CARNETS] => str_replace('Certificado-', 'Carnets-', $base),
            [CertificateDeliveryParts::AFFILIATES] => str_replace('Certificado-', 'Relacion-afiliados-', $base),
            default => $base,
        };
    }

    /**
     * Carnets que lleva el envío: los elegidos, todos los de la emisión o ninguno.
     */
    public static function carnetsCount(AffiliationCertificateDelivery $delivery, AffiliationCertificateIssue $issue): int
    {
        if (! $delivery->includesCarnets()) {
            return 0;
        }

        return $delivery->usesOwnFile() ? count($delivery->carnet_affiliate_ids ?? []) : (int) $issue->carnets_count;
    }

    /**
     * Partes pedidas y carnets elegidos, desde la lista de partes o desde un
     * `CONTENT_*` de los envíos anteriores a la elección por partes.
     *
     * @param  string|list<string>  $what
     * @param  list<int|string>|null  $carnetAffiliateIds
     * @return array{0: list<string>, 1: list<int|string>|null}
     */
    private static function resolveParts(AffiliationCertificateIssue $issue, string|array $what, ?array $carnetAffiliateIds): array
    {
        if (is_array($what)) {
            return [CertificateDeliveryParts::clean($what, $issue), $carnetAffiliateIds];
        }

        return match ($what) {
            AffiliationCertificateDelivery::CONTENT_FULL => [CertificateDeliveryParts::availableFor($issue), null],
            AffiliationCertificateDelivery::CONTENT_CERTIFICATE => [CertificateDeliveryParts::clean([CertificateDeliveryParts::CERTIFICATE, CertificateDeliveryParts::AFFILIATES], $issue), null],
            AffiliationCertificateDelivery::CONTENT_CARNETS => [CertificateDeliveryParts::clean([CertificateDeliveryParts::CARNETS], $issue), $carnetAffiliateIds ?? []],
            default => [[], null],
        };
    }
}
