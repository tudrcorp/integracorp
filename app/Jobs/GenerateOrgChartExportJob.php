<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OrgStructureVersion;
use App\Models\User;
use App\Support\OrgStructure\OrgChartPdfExporter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Genera el organigrama en PDF y avisa en la campana con el botón de descarga.
 *
 * Una generación a la vez por usuario: la pantalla toma el turno con
 * `claimSlot()` antes de despachar y el job lo libera al terminar o fallar.
 * Idempotente: el nombre del archivo se fija al despachar.
 */
class GenerateOrgChartExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    private const SLOT_SECONDS = 600;

    /**
     * @param  array{root_key?: string|null, depth?: int|null, show_contact?: bool, show_vacancies?: bool}  $options
     */
    public function __construct(
        public int $versionId,
        public array $options,
        public int $requestedByUserId,
        public string $filename,
    ) {
        $this->onQueue('system');
    }

    /**
     * Reserva el turno de exportación del usuario. Falso si ya tiene una en curso.
     */
    public static function claimSlot(int $userId): bool
    {
        return Cache::add(self::slotKey($userId), true, self::SLOT_SECONDS);
    }

    public static function releaseSlot(int $userId): void
    {
        Cache::forget(self::slotKey($userId));
    }

    private static function slotKey(int $userId): string
    {
        return 'org-chart-export:running:'.$userId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30];
    }

    public function handle(): void
    {
        $user = User::query()->find($this->requestedByUserId);
        $version = OrgStructureVersion::query()->find($this->versionId);

        if (! $user instanceof User || $version === null) {
            self::releaseSlot($this->requestedByUserId);

            return;
        }

        try {
            OrgChartPdfExporter::store($version, $this->options, (int) $user->id, $this->filename);
        } finally {
            self::releaseSlot($this->requestedByUserId);
        }

        Notification::make()
            ->title('Organigrama listo')
            ->body('El PDF de «'.$version->name.'» ya se puede descargar. Estará disponible '.OrgChartPdfExporter::RETENTION_DAYS.' días.')
            ->icon('heroicon-o-rectangle-group')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Descargar')
                    ->button()
                    ->url(route('org-structure.exports.download', ['file' => $this->filename]), shouldOpenInNewTab: true)
                    ->markAsRead(),
            ])
            ->sendToDatabase($user);
    }

    public function failed(?Throwable $exception): void
    {
        self::releaseSlot($this->requestedByUserId);

        Log::error('GenerateOrgChartExportJob falló', [
            'version_id' => $this->versionId,
            'user_id' => $this->requestedByUserId,
            'message' => $exception?->getMessage(),
        ]);

        $user = User::query()->find($this->requestedByUserId);

        if (! $user instanceof User) {
            return;
        }

        Notification::make()
            ->title('No se pudo generar el organigrama')
            ->body('Ocurrió un error al generar el PDF. Intenta de nuevo o exporta desde un área más pequeña.')
            ->danger()
            ->sendToDatabase($user);
    }
}
