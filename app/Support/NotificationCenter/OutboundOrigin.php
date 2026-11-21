<?php

declare(strict_types=1);

namespace App\Support\NotificationCenter;

use Throwable;

/**
 * Quién arma un mensaje: el primer método de la aplicación en la pila que no sea
 * un «transporte» genérico (los jobs de WhatsApp, los envoltorios de UltraMsg del
 * NotificationController, el repartidor interno).
 *
 * Se toma al despachar (en el constructor de los jobs), no al ejecutar en la cola,
 * donde la pila ya no tiene a quien lo pidió. Cuesta microsegundos: no copia argumentos.
 */
final class OutboundOrigin
{
    /** Clases que solo transportan el mensaje. */
    private const TRANSPORT_CLASSES = [
        'App\Jobs\SendNotificacionWhatsApp',
        'App\Jobs\SendNotificacionWhatsAppDocument',
        'App\Jobs\SendNotificacionWhatsAppVideo',
        'App\Support\InternalNotifications\InternalNotificationDispatcher',
        'App\Support\InternalNotifications\InternalNotificationFallback',
        'App\Support\InternalNotifications\InternalMessaging',
        OutboundOrigin::class,
        OutboundGuard::class,
        OutboundWhatsApp::class,
    ];

    /** Métodos del NotificationController que solo envuelven la llamada a UltraMsg. */
    private const TRANSPORT_METHODS = [
        'App\Http\Controllers\NotificationController' => [
            'sendWhatsAppChat', 'sendWhatsAppDocument', 'sendWhatsAppDocumentUrl', 'sendPublicStorageDocumentWhatsApp',
            'sendTelemedicineDocumentWhatsApp', 'sendDocumentsToPatient', 'sendIntegracorpBrandWhatsAppCaption',
            'sendWhatsAppBrandImageCaption', 'sendQuoteDocumentWhatsApp', 'sendQuotePdfToAnalyst',
            'sendIndividualQuotePdfToAnalyst', 'sendCorporateQuotePdfToAnalyst', 'sendInternalNotice', 'notificationBirthday',
        ],
    ];

    public static function capture(): ?string
    {
        try {
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40) as $frame) {
                $class = $frame['class'] ?? null;
                $function = (string) ($frame['function'] ?? '');

                if (! is_string($class) || ! str_starts_with($class, 'App\\')) {
                    continue;
                }

                if (in_array($class, self::TRANSPORT_CLASSES, true) || in_array($function, self::TRANSPORT_METHODS[$class] ?? [], true)) {
                    continue;
                }

                if (str_contains($function, '{closure')) {
                    $function = '{closure}';
                }

                return mb_substr($class.'::'.$function, 0, 255);
            }
        } catch (Throwable) {
        }

        return null;
    }
}
