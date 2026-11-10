<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Support\Metrics\System\SystemDatabaseSnapshot;
use App\Support\Metrics\System\SystemEvolutionComparison;
use App\Support\Metrics\System\SystemEvolutionReport;
use App\Support\Metrics\System\SystemGitHistory;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Sistema: cómo evoluciona IntegraCorp como producto de software. El trabajo
 * de un mes sobre el código y la base de datos (entregas, líneas, archivos,
 * módulos, capas, migraciones y piezas nuevas) frente a los meses anteriores.
 *
 * Los datos salen del historial de git de la rama desplegada y de
 * `information_schema`; se calculan una vez por commit y se guardan en caché.
 * La página se pinta primero vacía y carga los datos al abrirse (wire:init)
 * para no bloquear la navegación.
 */
class Sistema extends Page
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $navigationLabel = 'Sistema';

    protected static ?string $title = 'Evolución del sistema';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'sistema';

    protected string $view = 'filament.metrics.pages.sistema';

    #[Url(as: 'mes')]
    public string $month = '';

    #[Url(as: 'comparar')]
    public int $window = SystemEvolutionComparison::DEFAULT_WINDOW;

    public bool $ready = false;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return null;
    }

    public function mount(): void
    {
        $this->month = SystemEvolutionComparison::normalizeMonth($this->month);
        $this->window = SystemEvolutionComparison::normalizeWindow($this->window);
    }

    public function loadReport(): void
    {
        $this->ready = true;
    }

    public function updatedMonth(): void
    {
        $this->month = SystemEvolutionComparison::normalizeMonth($this->month);
    }

    public function updatedWindow(): void
    {
        $this->window = SystemEvolutionComparison::normalizeWindow($this->window);
    }

    public function getSubheading(): ?string
    {
        return 'Trabajo realizado sobre el código y la base de datos, mes a mes, comparado con los meses anteriores.';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToSummary')
                ->label('Volver al resumen')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(Dashboard::getUrl()),
            Action::make('refresh')
                ->label('Recalcular')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->tooltip('Vuelve a leer el historial de código y la base de datos. Solo hace falta si acaba de subir cambios.')
                ->action(function (): void {
                    $lock = Cache::lock('metrics:system:evolution:refresh', 30);

                    if (! $lock->get()) {
                        Notification::make()
                            ->title('Ya se está recalculando')
                            ->body('Espere unos segundos y vuelva a intentar.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        SystemEvolutionReport::flush();
                        $this->ready = true;
                    } finally {
                        $lock->release();
                    }

                    Notification::make()
                        ->title('Estadísticas actualizadas')
                        ->body('Se volvió a leer el historial de código y la foto de la base de datos.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        if (! $this->ready) {
            return ['ready' => false];
        }

        $payload = (new SystemEvolutionReport)->payload();

        if (! ($payload['available'] ?? false)) {
            return ['ready' => true, 'available' => false];
        }

        $report = SystemEvolutionComparison::build(
            $payload,
            $this->month,
            $this->window,
            SystemDatabaseSnapshot::current(),
        );

        return [
            'ready' => true,
            'available' => true,
            'report' => $report,
            'monthOptions' => SystemEvolutionComparison::monthOptions($payload),
            'windowOptions' => SystemEvolutionComparison::WINDOWS,
            'uncommitted' => Cache::remember('metrics:system:uncommitted', 300, static fn (): array => (new SystemGitHistory)->uncommitted()),
        ];
    }
}
