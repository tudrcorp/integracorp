<?php

declare(strict_types=1);

namespace App\Enums;

use App\Http\Controllers\NotificationController;

/**
 * Avisos de WhatsApp del módulo de cotizaciones.
 *
 * Cada caso sabe a quién avisa, cómo se envía y qué se le cuenta después al
 * analista en el panel. Todos salen por `SendQuoteWhatsAppNotificationJob`, de
 * modo que la pantalla nunca espera a UltraMsg.
 */
enum QuoteWhatsAppNotification: string
{
    case IndividualQuoteCreated = 'individual_quote_created';

    case CorporateQuoteCreated = 'corporate_quote_created';

    case CorporateDataUploaded = 'corporate_data_uploaded';

    case InteractiveLinkSent = 'interactive_link_sent';

    case CorporateObservationAdded = 'corporate_observation_added';

    /**
     * Ejecuta el envío.
     *
     * Devuelve `false` cuando **ningún** destinatario recibió el mensaje, que
     * es justo la condición que hace seguro reintentar: un reintento tras un
     * envío parcial duplicaría mensajes a quienes ya lo recibieron.
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(array $payload): bool
    {
        $code = (string) ($payload['code'] ?? '');
        $agent = (string) ($payload['agent'] ?? '');

        return match ($this) {
            self::IndividualQuoteCreated => (bool) NotificationController::createdIndividualQuote($code, $agent),
            self::CorporateQuoteCreated => (bool) NotificationController::createdCorporateQuote($code, $agent),
            self::CorporateDataUploaded => (bool) NotificationController::sendUploadDataCorporate($agent, $code),
            self::InteractiveLinkSent => (bool) NotificationController::sendLinkIndividualQuote(
                (string) ($payload['phone'] ?? ''),
                (string) ($payload['link'] ?? ''),
            ),
            self::CorporateObservationAdded => (bool) NotificationController::saddObervationToCorporateQuote(
                $code,
                $agent,
                (string) ($payload['observation'] ?? ''),
            ),
        };
    }

    /**
     * Qué se está enviando, en palabras del negocio.
     */
    public function label(): string
    {
        return match ($this) {
            self::IndividualQuoteCreated => 'Aviso de cotización individual',
            self::CorporateQuoteCreated => 'Aviso de cotización corporativa',
            self::CorporateDataUploaded => 'Aviso de carga de población',
            self::InteractiveLinkSent => 'Link de cotización interactiva',
            self::CorporateObservationAdded => 'Aviso de observación registrada',
        };
    }

    /**
     * A quién le llega el WhatsApp.
     */
    public function audience(): string
    {
        return match ($this) {
            self::InteractiveLinkSent => 'al cliente',
            default => 'al equipo de análisis',
        };
    }

    public function successTitle(): string
    {
        return 'WhatsApp enviado';
    }

    public function failureTitle(): string
    {
        return 'No se pudo enviar el WhatsApp';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function successBody(array $payload): string
    {
        $referencia = $this->reference($payload);

        return match ($this) {
            self::InteractiveLinkSent => 'El link de la cotización '.$referencia.' llegó al cliente ('
                .$this->maskedPhone((string) ($payload['phone'] ?? '')).').',
            self::CorporateDataUploaded => 'El equipo de análisis fue avisado de la carga de población de la cotización '.$referencia.'.',
            self::CorporateObservationAdded => 'El equipo de análisis recibió la observación registrada en la cotización '.$referencia.'.',
            default => 'El equipo de análisis recibió el aviso de la cotización '.$referencia
                .', con la propuesta económica adjunta.',
        };
    }

    /**
     * El mensaje de error dice qué pasó, qué NO pasó y qué hacer ahora.
     *
     * @param  array<string, mixed>  $payload
     */
    public function failureBody(array $payload): string
    {
        $referencia = $this->reference($payload);

        $destinatario = $this === self::InteractiveLinkSent
            ? 'el cliente ('.$this->maskedPhone((string) ($payload['phone'] ?? '')).')'
            : 'el equipo de análisis';

        return 'La cotización '.$referencia.' se guardó correctamente, pero '.$destinatario
            .' no recibió el WhatsApp después de varios intentos. '
            .'Avise por otra vía y reporte la incidencia a soporte: el mensaje puede reenviarse desde el sistema.';
    }

    /**
     * Clave del candado que evita enviar dos veces lo mismo.
     *
     * @param  array<string, mixed>  $payload
     */
    public function lockKey(array $payload): string
    {
        $partes = [
            $this->value,
            (string) ($payload['code'] ?? ''),
            (string) ($payload['phone'] ?? ''),
            md5((string) ($payload['observation'] ?? '')),
        ];

        return 'quote-whatsapp:'.md5(implode('|', $partes));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function reference(array $payload): string
    {
        $code = trim((string) ($payload['code'] ?? ''));

        return $code === '' ? 'solicitada' : $code;
    }

    /**
     * El teléfono del cliente no se muestra completo en el panel.
     */
    private function maskedPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (mb_strlen($digits) <= 4) {
            return $digits === '' ? 'sin teléfono' : $digits;
        }

        return str_repeat('•', max(0, mb_strlen($digits) - 4)).mb_substr($digits, -4);
    }
}
