<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Filament\Telemedicina\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Models\TelemedicineConsultationPatient;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;

final class TelemedicineCaseDocumentReadyNotification
{
    public const TAB_QUERY_PARAMETER = 'tab';

    public const EXPEDIENTE_DOCUMENTAL_TAB_QUERY = 'expediente-documental::tab';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function send(User|Authenticatable $user, array $data, string $documentName): void
    {
        $notification = Notification::make()
            ->title('¡Documento generado!')
            ->body(self::body($documentName))
            ->success();

        $url = self::caseExpedienteDocumentalUrl($data);

        if (filled($url)) {
            $notification->actions([
                Action::make('openCaseDocuments')
                    ->label('Ir al expediente documental')
                    ->button()
                    ->url($url)
                    ->markAsRead(),
            ]);
        }

        $notification->sendToDatabase($user);
    }

    public static function body(string $documentName): string
    {
        return "El documento «{$documentName}» ya está listo. Ve al detalle del caso, pestaña Expediente documental, para descargarlo o reenviarlo.";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function caseExpedienteDocumentalUrl(array $data): ?string
    {
        $caseId = self::resolveCaseId($data);

        if ($caseId === null) {
            return null;
        }

        $url = TelemedicineCaseResource::getUrl('view', ['record' => $caseId], panel: 'telemedicina');

        return $url.'?'.self::TAB_QUERY_PARAMETER.'='.rawurlencode(self::EXPEDIENTE_DOCUMENTAL_TAB_QUERY);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolveCaseId(array $data): ?int
    {
        $caseId = (int) ($data['telemedicine_case_id'] ?? 0);

        if ($caseId > 0) {
            return $caseId;
        }

        $consultationId = (int) ($data['telemedicine_consultation_id'] ?? 0);

        if ($consultationId <= 0) {
            return null;
        }

        $fromConsultation = TelemedicineConsultationPatient::query()
            ->whereKey($consultationId)
            ->value('telemedicine_case_id');

        if ($fromConsultation === null) {
            return null;
        }

        return (int) $fromConsultation;
    }
}
