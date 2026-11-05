<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\OperationReportFormat;
use App\Models\User;
use App\Support\Administration\CapitationBilling\CapitationBillingReport;
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
 * Genera en cola la pre-facturación de capitado (el PDF) y avisa al analista en la campana.
 * Idempotente: el nombre del archivo se fija al despachar; un reintento lo reescribe.
 */
class GenerateCapitationBillingReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 900;

    /**
     * @param  list<array{concept: string, amount: float}>  $discounts
     */
    public function __construct(
        public string $format,
        public int $supplierId,
        public string $period,
        public array $discounts,
        public ?float $bcvRate,
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
        $supplier = CapitationBillingReport::supplier($this->supplierId);
        $snapshot = CapitationBillingReport::store($format, $supplier, CapitationBillingReport::period($this->period), $this->discounts, $this->bcvRate, (int) $user->id, $this->filename);

        Notification::make()
            ->title('Pre-facturación de capitado lista')
            ->body($supplier->name.' · '.$snapshot->periodLabel().' en '.$format->label().' ya se puede descargar. Estará disponible 7 días.')
            ->icon('heroicon-o-receipt-percent')
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
        Log::error('GenerateCapitationBillingReportJob falló', [
            'supplier_id' => $this->supplierId,
            'period' => $this->period,
            'user_id' => $this->requestedByUserId,
            'message' => $exception?->getMessage(),
        ]);

        $user = User::query()->find($this->requestedByUserId);

        if ($user instanceof User) {
            Notification::make()
                ->title('No se pudo generar la pre-facturación de capitado')
                ->body('Intente nuevamente. Si el problema continúa, descárguela en Excel.')
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
