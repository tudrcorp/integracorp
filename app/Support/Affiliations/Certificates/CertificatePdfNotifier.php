<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Models\AffiliationCertificateIssue;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Aviso en el panel a quien pidió un certificado grande, cuando termina de
 * dibujarse o cuando falla definitivamente (tras agotar los reintentos).
 */
final class CertificatePdfNotifier
{
    public static function ready(AffiliationCertificateIssue $issue): void
    {
        $user = self::requester($issue);

        if ($user === null) {
            return;
        }

        Notification::make()
            ->title('Certificado listo')
            ->body('El certificado de '.$issue->affiliation_code.' con '.number_format((int) $issue->carnets_count, 0, ',', '.').' carnets ya se puede descargar.')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Descargar PDF')
                    ->url(route('business.affiliation-certificate.pdf', ['issue' => $issue->id, 'descargar' => 1]))
                    ->markAsRead(),
            ])
            ->sendToDatabase($user);
    }

    public static function failed(AffiliationCertificateIssue $issue): void
    {
        $user = self::requester($issue);

        if ($user === null) {
            return;
        }

        Notification::make()
            ->title('No se pudo generar el certificado')
            ->body('El certificado de '.$issue->affiliation_code.' falló al dibujarse. Ábralo en el generador y pulse «Reintentar»: se conservan las partes ya listas.')
            ->danger()
            ->sendToDatabase($user);
    }

    private static function requester(AffiliationCertificateIssue $issue): ?User
    {
        return $issue->issued_by ? User::query()->find($issue->issued_by) : null;
    }
}
