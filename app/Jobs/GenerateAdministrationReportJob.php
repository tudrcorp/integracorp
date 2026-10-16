<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\OperationReportFormat;
use App\Models\User;
use App\Support\Administration\Reports\AdministrationReportGenerator;
use App\Support\Administration\Reports\CollectionReportFilters;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Genera en cola los reportes del Centro de Reportes de Administración que son
 * demasiado grandes para la petición HTTP y avisa al analista en la campana con
 * el botón de descarga.
 *
 * Idempotente: el nombre del archivo se fija al despachar, así que un reintento
 * reescribe el mismo archivo en lugar de crear otro.
 */
class GenerateAdministrationReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 900;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public string $format,
        public array $filters,
        public int $requestedByUserId,
        public string $filename,
    ) {
        $this->onQueue('system');
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

        if (! $user instanceof User) {
            return;
        }

        $format = OperationReportFormat::from($this->format);
        $filters = CollectionReportFilters::fromArray($this->filters);

        AdministrationReportGenerator::store($format, $filters, (int) $user->id, $this->filename);

        Notification::make()
            ->title('Reporte listo')
            ->body('«'.$filters->type->label().'» en '.$format->label().' ya se puede descargar. Estará disponible 7 días.')
            ->icon('heroicon-o-document-chart-bar')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Descargar')
                    ->button()
                    ->url(route('administration.reports.download', ['file' => $this->filename]), shouldOpenInNewTab: true)
                    ->markAsRead(),
            ])
            ->sendToDatabase($user);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('GenerateAdministrationReportJob falló', [
            'format' => $this->format,
            'filters' => $this->filters,
            'user_id' => $this->requestedByUserId,
            'message' => $exception?->getMessage(),
        ]);

        $user = User::query()->find($this->requestedByUserId);

        if (! $user instanceof User) {
            return;
        }

        Notification::make()
            ->title('No se pudo generar el reporte')
            ->body('Ocurrió un error al generar el reporte de cobranza. Intente nuevamente o acote el periodo.')
            ->danger()
            ->sendToDatabase($user);
    }
}
