<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Jobs\GenerateTelemedicineCaseRecordJob;
use App\Models\TelemedicineCase;
use App\Models\User;
use App\Services\TelemedicineCaseRecordPdfService;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\FilamentIosButton;
use App\Support\Filament\UserNavigationAccess;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * «Generar expediente»: une en un solo PDF la portada del paciente, un índice y
 * todos los documentos del caso. Se genera en cola y llega como notificación con
 * el botón de descarga; si el expediente vigente ya existe, se entrega al instante.
 */
final class TelemedicineCaseRecordAction
{
    public const PANEL_ID = 'operations';

    public const MODULE = 'OPERACIONES';

    public static function make(): Action
    {
        return Action::make('generateCaseRecord')
            ->label('Generar expediente')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('primary')
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
            ])
            ->visible(fn (): bool => self::isOperationsPanel() && self::userCanGenerate(Auth::user()))
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedDocumentDuplicate)
            ->modalIconColor('primary')
            ->modalHeading('Generar expediente del caso')
            ->modalDescription('Se unirán en un solo PDF la portada con los datos del paciente, un índice y todos los documentos del caso, en orden cronológico. Se prepara en segundo plano: puedes seguir trabajando y te avisaremos en las notificaciones con el botón para descargarlo.')
            ->modalSubmitActionLabel('Generar expediente')
            ->action(function (TelemedicineCase $record): void {
                $user = Auth::user();

                abort_unless($user instanceof User && self::isOperationsPanel() && self::userCanGenerate($user), 403);

                self::request($record, $user);
            });
    }

    /**
     * Entrega el expediente vigente o lo pone en cola.
     */
    public static function request(TelemedicineCase $case, User $user): void
    {
        try {
            $documents = TelemedicineCaseRecordPdfService::documents($case);
        } catch (Throwable $exception) {
            Log::error('TelemedicineCaseRecordAction: no se pudieron leer los documentos del caso', ['case_id' => $case->getKey(), 'error' => $exception->getMessage()]);

            Notification::make()
                ->title('No se pudo preparar el expediente')
                ->body('No pudimos leer los documentos del caso. Inténtalo de nuevo en unos minutos.')
                ->danger()
                ->send();

            return;
        }

        if ($documents === []) {
            Notification::make()
                ->title('El caso no tiene documentos')
                ->body('Todavía no hay documentos en el Centro de documentos del caso para armar el expediente.')
                ->warning()
                ->send();

            return;
        }

        $fingerprint = TelemedicineCaseRecordPdfService::fingerprint($case, $documents);
        $summary = TelemedicineCaseRecordPdfService::readSummary($case, $fingerprint);

        if ($summary !== null) {
            SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_CASE_RECORD_REUSED', 'operations.telemedicine-cases.record', ['telemedicine_case_id' => $case->getKey()], $user);

            self::readyNotification($case, $fingerprint, $summary['included'], count($summary['skipped']), $summary['pages'])->send();

            return;
        }

        GenerateTelemedicineCaseRecordJob::dispatch((int) $case->getKey(), (int) $user->getKey());

        SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_CASE_RECORD_REQUESTED', 'operations.telemedicine-cases.record', ['telemedicine_case_id' => $case->getKey(), 'documents' => count($documents)], $user);

        Notification::make()
            ->title('Generando el expediente')
            ->body('Estamos uniendo '.count($documents).' '.(count($documents) === 1 ? 'documento' : 'documentos').' del caso. Te avisaremos en las notificaciones cuando esté listo para descargar.')
            ->info()
            ->send();
    }

    public static function readyNotification(TelemedicineCase $case, string $fingerprint, int $included, int $skipped, int $pages): Notification
    {
        $caseLabel = filled($case->code) ? (string) $case->code : 'Caso #'.$case->getKey();
        $body = "Expediente del caso {$caseLabel}: {$included} ".($included === 1 ? 'documento' : 'documentos')." en {$pages} páginas.";

        if ($skipped > 0) {
            $body .= " {$skipped} ".($skipped === 1 ? 'documento no se pudo unir' : 'documentos no se pudieron unir').'; el índice indica cuáles y por qué.';
        }

        return Notification::make()
            ->title('Expediente listo')
            ->body($body)
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color($skipped > 0 ? 'warning' : 'success')
            ->iconColor($skipped > 0 ? 'warning' : 'success')
            ->persistent()
            ->actions([
                Action::make('downloadCaseRecord')
                    ->label('Descargar expediente')
                    ->button()
                    ->url(route('operations.telemedicine-cases.record.download', [
                        'telemedicineCase' => $case->getKey(),
                        'fingerprint' => $fingerprint,
                    ]), shouldOpenInNewTab: true),
            ]);
    }

    /**
     * Permiso «Generar expediente del caso» en el módulo de Operaciones.
     */
    public static function userCanGenerate(mixed $user): bool
    {
        return $user instanceof User
            && UserNavigationAccess::canPerformModuleAction($user, self::MODULE, BusinessFilamentActionPermissionRegistry::GENERATE_TELEMEDICINE_CASE_RECORD);
    }

    private static function isOperationsPanel(): bool
    {
        try {
            return Filament::getCurrentPanel()?->getId() === self::PANEL_ID;
        } catch (Throwable) {
            return false;
        }
    }
}
