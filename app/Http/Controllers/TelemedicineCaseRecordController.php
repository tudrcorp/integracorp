<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Operations\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Models\TelemedicineCase;
use App\Models\User;
use App\Services\TelemedicineCaseRecordPdfService;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicineCaseRecordAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Descarga del expediente del caso (disco privado). Solo para quien entra al panel
 * de Operaciones y tiene el permiso «Generar expediente del caso»; los demás
 * reciben 404, para no revelar que el expediente existe.
 */
class TelemedicineCaseRecordController extends Controller
{
    public function download(Request $request, TelemedicineCase $telemedicineCase, string $fingerprint): Response|RedirectResponse
    {
        $user = $request->user();

        if (! self::userCanDownload($user)) {
            SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_CASE_RECORD_DENIED', 'operations.telemedicine-cases.record.download', ['telemedicine_case_id' => $telemedicineCase->getKey()]);

            abort(404);
        }

        $path = TelemedicineCaseRecordPdfService::storedPath($telemedicineCase, $fingerprint);
        $disk = Storage::disk(TelemedicineCaseRecordPdfService::DISK);

        if (! $disk->exists($path)) {
            Notification::make()
                ->title('Este expediente ya no está disponible')
                ->body('Los documentos del caso cambiaron y el expediente se reemplazó. Pulsa «Generar expediente» de nuevo para obtener la versión actual.')
                ->warning()
                ->persistent()
                ->send();

            return redirect()->to(TelemedicineCaseResource::getUrl('view', ['record' => $telemedicineCase], panel: TelemedicineCaseRecordAction::PANEL_ID));
        }

        SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_CASE_RECORD_DOWNLOADED', 'operations.telemedicine-cases.record.download', ['telemedicine_case_id' => $telemedicineCase->getKey()]);

        return $disk->download($path, TelemedicineCaseRecordPdfService::downloadName($telemedicineCase), [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public static function userCanDownload(mixed $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        try {
            $panel = Filament::getPanel(TelemedicineCaseRecordAction::PANEL_ID);
        } catch (Throwable) {
            return false;
        }

        return $user->canAccessPanel($panel) && TelemedicineCaseRecordAction::userCanGenerate($user);
    }
}
