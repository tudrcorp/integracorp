<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Models\CompanyAssociate;
use App\Support\RunReportMessageFormatter;

final class CompanyAssociateDocumentsDeliveryMessage
{
    public static function emailSubject(CompanyAssociate $associate): string
    {
        return 'Tu tarjeta de afiliado y QR · '.$associate->full_name.' · Tu Doctor Group';
    }

    public static function whatsappIntro(CompanyAssociate $associate): string
    {
        $validity = CompanyAssociateCarnetGenerator::cardValidityDates($associate);

        $lines = [
            '*TARJETA DE AFILIADO · TU DOCTOR GROUP*',
            '',
            'Hola, *'.$associate->full_name.'*.',
            '',
            'Adjuntamos su tarjeta de afiliado y el código QR del plan INCLUSIÓN.',
            'Vigencia: *'.$validity['desde'].'*'.($validity['hasta'] !== '' && $validity['hasta'] !== $validity['desde'] ? ' al *'.$validity['hasta'].'*' : '').'.',
            '',
            'Conserve ambos documentos para su atención.',
        ];

        return RunReportMessageFormatter::truncateForWhatsAppCaption(implode("\n", $lines));
    }

    public static function whatsappCarnetCaption(CompanyAssociate $associate): string
    {
        return RunReportMessageFormatter::truncateForWhatsAppCaption(
            'Tarjeta de afiliado · '.$associate->full_name.' · Plan INCLUSIÓN',
        );
    }

    public static function whatsappQrCaption(CompanyAssociate $associate): string
    {
        return RunReportMessageFormatter::truncateForWhatsAppCaption(
            'Código QR · Canales de comunicación · '.$associate->full_name,
        );
    }

    public static function emailLogoPath(): string
    {
        $primaryLogo = public_path('image/logoNewPdf.png');

        if (file_exists($primaryLogo)) {
            return $primaryLogo;
        }

        return public_path('image/logoNewTDG.png');
    }
}
