<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TelemedicineCase;
use App\Models\User;
use App\Services\TelemedicineCaseRecordPdfService;
use App\Support\Telemedicine\TelemedicineCaseRecordAction;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Genera el expediente del caso y avisa a quien lo pidió con el botón de descarga.
 *
 * Es idempotente: si el expediente vigente ya existe se reutiliza. Un mismo
 * analista no encola dos veces el mismo caso mientras se está generando.
 */
class GenerateTelemedicineCaseRecordJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public function __construct(
        public int $caseId,
        public int $userId,
    ) {}

    public function uniqueId(): string
    {
        return 'telemedicine-case-record:'.$this->caseId.':'.$this->userId;
    }

    public function handle(): void
    {
        $case = TelemedicineCase::query()->find($this->caseId);
        $user = User::query()->find($this->userId);

        if (! $case instanceof TelemedicineCase || ! $user instanceof User) {
            return;
        }

        @ini_set('memory_limit', '1024M');
        @set_time_limit($this->timeout);

        $result = TelemedicineCaseRecordPdfService::generate($case, (string) $user->name);

        TelemedicineCaseRecordAction::readyNotification(
            $case,
            $result['fingerprint'],
            $result['included'],
            count($result['skipped']),
            $result['pages'],
        )->sendToDatabase($user);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('GenerateTelemedicineCaseRecordJob: no se pudo generar el expediente', [
            'case_id' => $this->caseId,
            'user_id' => $this->userId,
            'error' => $exception?->getMessage(),
        ]);

        $user = User::query()->find($this->userId);

        if (! $user instanceof User) {
            return;
        }

        $case = TelemedicineCase::query()->find($this->caseId);
        $caseLabel = $case instanceof TelemedicineCase && filled($case->code) ? (string) $case->code : 'Caso #'.$this->caseId;

        Notification::make()
            ->title('No se pudo generar el expediente')
            ->body("Ocurrió un error al armar el expediente del caso {$caseLabel}. Inténtalo de nuevo; si se repite, avisa a soporte.")
            ->danger()
            ->sendToDatabase($user);
    }
}
