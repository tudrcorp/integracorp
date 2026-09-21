<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\IndividualQuote;
use App\Models\IndividualQuotePaymentReceipt;
use App\Models\User;
use App\Support\RunReportMessageFormatter;

final class StorefrontQuotePaymentReceiptNotificationMessage
{
    /**
     * @var list<string>
     */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public static function emailSubject(IndividualQuote $quote): string
    {
        return 'Comprobante PWA · '.$quote->code.' · INTEGRACORP';
    }

    public static function whatsappBody(
        IndividualQuote $quote,
        IndividualQuotePaymentReceipt $receipt,
        User $uploader,
    ): string {
        $lines = [
            '*COMPROBANTE CARGADO · PWA*',
            '',
            'El comprobante de la cotización nro. *'.$quote->code.'* fue cargado por el usuario *'.self::userLabel($uploader).'* desde el canal PWA.',
            '',
            '*Cotización*',
            '• Código: '.$quote->code,
            '• Titular: '.self::value($quote->full_name),
            '• Teléfono: '.self::value($quote->phone),
            '• Correo: '.self::value($quote->email),
            '• Estado: '.self::value($quote->status),
            '',
            '*Usuario PWA*',
            '• Nombre: '.self::userLabel($uploader),
            '• Cédula: '.self::value($uploader->nro_identification ?: $uploader->identity_card),
            '• Correo: '.self::value($uploader->email),
            '• Teléfono: '.self::value($uploader->phone),
            '',
            '• Canal: PWA',
            '• Cargado el: '.self::when($receipt->created_at),
            '',
            'Se adjunta el comprobante.',
        ];

        return RunReportMessageFormatter::truncateForWhatsAppCaption(implode("\n", $lines));
    }

    public static function whatsappCaption(IndividualQuote $quote): string
    {
        return RunReportMessageFormatter::truncateForWhatsAppCaption(
            'Comprobante · '.$quote->code.' · canal PWA'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function emailPayload(
        IndividualQuote $quote,
        IndividualQuotePaymentReceipt $receipt,
        User $uploader,
    ): array {
        return [
            'quoteCode' => (string) $quote->code,
            'quoteClient' => self::value($quote->full_name),
            'quotePhone' => self::value($quote->phone),
            'quoteEmail' => self::value($quote->email),
            'quoteStatus' => self::value($quote->status),
            'userName' => self::userLabel($uploader),
            'userIdNumber' => self::value($uploader->nro_identification ?: $uploader->identity_card),
            'userEmail' => self::value($uploader->email),
            'userPhone' => self::value($uploader->phone),
            'channel' => 'PWA',
            'uploadedAt' => self::when($receipt->created_at),
            'generatedAt' => self::when($receipt->created_at) !== '—'
                ? self::when($receipt->created_at)
                : date('d/m/Y H:i'),
        ];
    }

    public static function isImage(IndividualQuotePaymentReceipt $receipt): bool
    {
        if ($receipt->isImage()) {
            $extension = strtolower(pathinfo((string) $receipt->original_name, PATHINFO_EXTENSION));

            return in_array($extension, self::IMAGE_EXTENSIONS, true)
                || str_starts_with(strtolower((string) $receipt->mime), 'image/');
        }

        return false;
    }

    public static function userLabel(User $user): string
    {
        $name = trim((string) $user->name);

        return $name !== '' ? $name : 'Usuario PWA';
    }

    private static function value(mixed $value): string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : '—';
    }

    private static function when(mixed $date): string
    {
        if (! $date instanceof \DateTimeInterface) {
            return '—';
        }

        try {
            if (method_exists($date, 'timezone')) {
                return $date->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
            }
        } catch (\Throwable) {
            //
        }

        return $date->format('d/m/Y H:i');
    }
}
