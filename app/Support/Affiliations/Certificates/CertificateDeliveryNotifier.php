<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Models\AffiliationCertificateDelivery;
use App\Models\User;
use App\Services\AffiliationCertificateDeliveryService;
use Filament\Notifications\Notification;

/**
 * Aviso en el panel al analista que pidió el envío de un certificado por correo.
 */
final class CertificateDeliveryNotifier
{
    public static function sent(AffiliationCertificateDelivery $delivery): void
    {
        $user = self::requester($delivery);

        if ($user === null) {
            return;
        }

        $recipients = $delivery->delivered_to ?? [];
        $issue = $delivery->issue;
        $what = 'Se envió '.CertificateDeliveryParts::describe(
            $delivery->documentParts(),
            $issue !== null ? AffiliationCertificateDeliveryService::carnetsCount($delivery, $issue) : 0,
        ).' de ';
        $how = $delivery->delivery_method === AffiliationCertificateDelivery::METHOD_LINK
            ? ' Por su tamaño se envió como enlace de descarga, válido hasta el '.$delivery->link_expires_at?->format('d/m/Y').'.'
            : '';

        $copies = '';

        if ($delivery->copies_sent_at !== null && ($delivery->cc_recipients ?? []) !== []) {
            $copies .= ' Con copia a '.implode(', ', $delivery->cc_recipients).'.';
        }

        if ($delivery->copies_sent_at !== null && ($delivery->bcc_recipients ?? []) !== []) {
            $copies .= ' Copia oculta a '.implode(', ', $delivery->bcc_recipients).'.';
        }

        Notification::make()
            ->title('Correo enviado')
            ->body($what.self::code($delivery).' a '.implode(', ', $recipients).'.'.$copies.$how)
            ->success()
            ->sendToDatabase($user);
    }

    public static function failed(AffiliationCertificateDelivery $delivery): void
    {
        $user = self::requester($delivery);

        if ($user === null) {
            return;
        }

        $pending = array_values(array_diff($delivery->recipients ?? [], $delivery->delivered_to ?? []));

        Notification::make()
            ->title('No se pudo enviar el correo')
            ->body('El envío de '.self::code($delivery).' no llegó a: '.implode(', ', $pending).'. Revise los correos y vuelva a intentarlo desde el Generador de Certificado.')
            ->danger()
            ->sendToDatabase($user);
    }

    private static function code(AffiliationCertificateDelivery $delivery): string
    {
        return (string) ($delivery->issue?->affiliation_code ?? 'la afiliación');
    }

    private static function requester(AffiliationCertificateDelivery $delivery): ?User
    {
        return $delivery->sent_by !== null ? User::query()->find($delivery->sent_by) : null;
    }
}
