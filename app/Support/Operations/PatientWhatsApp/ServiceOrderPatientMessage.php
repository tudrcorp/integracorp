<?php

declare(strict_types=1);

namespace App\Support\Operations\PatientWhatsApp;

use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use App\Support\Operations\OperationServiceOrderProviderSummary;
use Illuminate\Support\Str;

/**
 * Textos del WhatsApp al paciente: el pie del documento de la orden (detalle + pregunta)
 * y las respuestas automáticas.
 *
 * El pie de un documento en WhatsApp admite 1.024 caracteres. Si el detalle no cabe se
 * recortan los ítems («y N más»), nunca la pregunta: sin ella no hay respuesta que guardar.
 */
final class ServiceOrderPatientMessage
{
    public const MAX_CAPTION_LENGTH = 1000;

    public const QUESTION = "¿Recibió su orden de servicio y puede ver el documento adjunto?\n\n👉 *Responda solo con SI o con NO.*";

    /**
     * @return array<string, string>
     */
    public static function serviceTypeLabels(): array
    {
        return [
            'MEDICAMENTOS' => 'Medicamentos',
            'LABORATORIOS' => 'Laboratorios',
            'IMAGENOLOGIA' => 'Estudios de imagenología',
            'ESPECIALISTA' => 'Consulta con especialista',
        ];
    }

    public const TEST_BANNER = '🧪 *MENSAJE DE PRUEBA*: no corresponde a ninguna gestión real. Responda SI o NO para probar que su respuesta llega.';

    public static function caption(OperationServiceOrder $order, string $recipientName, string $patientName, bool $toRepresentative, bool $isTest = false): string
    {
        $greeting = 'Hola, '.self::firstName($recipientName).'.';
        $intro = $toRepresentative
            ? 'Le informamos que la gestión del servicio de *'.self::clean($patientName).'* ya se encuentra *en proceso*.'
            : 'Le informamos que la gestión de su servicio ya se encuentra *en proceso*.';

        $header = [
            '*Tu Dr. En Casa*',
            ...($isTest ? ['', self::TEST_BANNER] : []),
            '',
            $greeting,
            $intro,
            '',
            '*Orden de servicio:* '.self::clean((string) $order->order_number),
            '*Paciente:* '.self::clean($patientName),
            '*Servicio:* '.(self::serviceTypeLabels()[(string) $order->service_type] ?? Str::title(Str::lower((string) $order->service_type))),
        ];

        $provider = OperationServiceOrderProviderSummary::name($order);
        $address = OperationServiceOrderProviderSummary::address($order);
        $phone = OperationServiceOrderProviderSummary::phone($order);
        $footer = [];

        if (filled($provider)) {
            $footer[] = '*Proveedor:* '.self::clean((string) $provider);
        }

        if (filled($address)) {
            $footer[] = '*Dirección:* '.self::clean((string) $address);
        }

        if (filled($phone)) {
            $footer[] = '*Teléfono del proveedor:* '.self::clean((string) $phone);
        }

        if ($order->appointment_at !== null) {
            $footer[] = '*Cita:* '.$order->appointment_at->format('d/m/Y h:i A');
        }

        $footer[] = '';
        $footer[] = 'Le adjuntamos el documento de su orden de servicio.';
        $footer[] = '';
        $footer[] = self::QUESTION;

        $items = self::itemLines($order);
        $fixedLength = mb_strlen(implode("\n", [...$header, '', '*Detalle:*', ...$footer])) + 2;

        return implode("\n", [...$header, '', '*Detalle:*', ...self::fitItems($items, self::MAX_CAPTION_LENGTH - $fixedLength), '', ...$footer]);
    }

    public static function answerConfirmation(string $answer): string
    {
        return $answer === 'SI'
            ? "*Tu Dr. En Casa*\n\nGracias. Registramos su respuesta: *SI*.\nSeguimos gestionando su servicio y le avisaremos cualquier novedad."
            : "*Tu Dr. En Casa*\n\nGracias por avisarnos. Registramos su respuesta: *NO*.\nUn analista se comunicará con usted para ayudarle con su orden de servicio.";
    }

    public static function invalidReplyReminder(string $orderNumber): string
    {
        return "*Tu Dr. En Casa*\n\nNo pudimos registrar su respuesta sobre la orden de servicio *".self::clean($orderNumber)."*.\n\n".self::QUESTION;
    }

    /**
     * @return list<string>
     */
    public static function itemLines(OperationServiceOrder $order): array
    {
        $order->loadMissing('operationServiceOrderItems');

        $lines = $order->operationServiceOrderItems
            ->map(function (OperationServiceOrderItem $item) use ($order): ?string {
                $name = self::clean((string) $item->item_name);

                if ($name === '') {
                    return null;
                }

                if ($order->service_type === 'MEDICAMENTOS' && (int) $item->quantity > 0) {
                    $unit = filled($item->item_unit) ? ' '.Str::lower(self::clean((string) $item->item_unit)) : '';

                    return '• '.$name.' ('.(int) $item->quantity.$unit.')';
                }

                return '• '.$name;
            })
            ->filter()
            ->values()
            ->all();

        return $lines !== [] ? $lines : ['• '.self::clean((string) ($order->description ?: 'Según el documento adjunto'))];
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private static function fitItems(array $items, int $room): array
    {
        $fitted = [];
        $used = 0;

        foreach ($items as $index => $line) {
            $remaining = count($items) - $index - 1;
            $suffixLength = $remaining > 0 ? mb_strlen("\n• y ".$remaining.' más (ver documento)') : 0;

            if ($used + mb_strlen($line) + 1 + $suffixLength > $room) {
                $left = count($items) - $index;
                $fitted[] = '• y '.$left.' más (ver documento)';

                return $fitted;
            }

            $fitted[] = $line;
            $used += mb_strlen($line) + 1;
        }

        return $fitted;
    }

    private static function firstName(string $name): string
    {
        $first = Str::of(self::clean($name))->explode(' ')->first();

        return $first !== null && $first !== '' ? Str::title(Str::lower($first)) : 'estimado(a)';
    }

    /**
     * Una sola línea, sin los símbolos de formato de WhatsApp que romperían las negritas.
     */
    private static function clean(string $text): string
    {
        return Str::squish(str_replace(['*', '_', '~', '`'], '', $text));
    }
}
