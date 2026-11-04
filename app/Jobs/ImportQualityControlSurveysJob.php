<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\QualityControlAffiliateType;
use App\Models\User;
use App\Support\Operations\QualityControl\QualityControlSurveyImporter;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Importa el Excel de respuestas de Quality Control ya analizado y confirmado.
 *
 * Idempotente: el importador escribe con `insertOrIgnore` sobre la huella única de cada
 * respuesta, así que un reintento no duplica encuestas.
 */
class ImportQualityControlSurveysJob implements ShouldQueue
{
    use Queueable;

    public const DISK = 'local';

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public string $path,
        public string $affiliateType,
        public int $userId,
        public string $batch,
        public string $fileName,
    ) {}

    public function handle(): void
    {
        $type = QualityControlAffiliateType::from($this->affiliateType);
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($this->path)) {
            Log::warning('ImportQualityControlSurveysJob: el archivo ya no existe', ['path' => $this->path, 'batch' => $this->batch]);

            return;
        }

        $result = QualityControlSurveyImporter::import(
            $disk->path($this->path),
            $type,
            $this->userId,
            $this->batch,
            pathinfo($this->fileName, PATHINFO_EXTENSION) ?: pathinfo($this->path, PATHINFO_EXTENSION),
        );

        $disk->delete($this->path);

        $user = User::query()->find($this->userId);

        SecurityAudit::log('AUDIT_QUALITY_CONTROL_SURVEYS_IMPORTED', 'operations.quality-control.import', [
            'affiliate_type' => $type->value,
            'batch' => $this->batch,
            'file_name' => $this->fileName,
            ...$result,
        ], $user);

        if ($user === null) {
            return;
        }

        $body = $result['inserted'] === 1 ? 'Se cargó 1 encuesta' : 'Se cargaron '.$result['inserted'].' encuestas';
        $body .= ' de «'.$this->fileName.'» en Quality Control '.$type->label().'.';

        if ($result['linked'] > 0) {
            $body .= ' '.$result['linked'].' quedaron enlazadas a su afiliado.';
        }

        if ($result['skipped'] > 0) {
            $body .= ' '.$result['skipped'].' ya estaban cargadas y no se repitieron.';
        }

        if ($result['errors'] > 0) {
            $body .= ' '.$result['errors'].' filas con errores no se cargaron.';
        }

        Notification::make()
            ->title('Importación de Quality Control lista')
            ->body($body)
            ->success()
            ->sendToDatabase($user);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ImportQualityControlSurveysJob: falló la importación', [
            'batch' => $this->batch,
            'path' => $this->path,
            'message' => $exception?->getMessage(),
        ]);

        Storage::disk(self::DISK)->delete($this->path);

        $user = User::query()->find($this->userId);

        if ($user !== null) {
            Notification::make()
                ->title('No se pudo importar Quality Control')
                ->body('La importación de «'.$this->fileName.'» falló y no se cargó nada nuevo. Intente de nuevo o reporte el caso a sistemas.')
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
