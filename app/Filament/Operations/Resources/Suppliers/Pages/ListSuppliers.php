<?php

namespace App\Filament\Operations\Resources\Suppliers\Pages;

use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use App\Filament\Operations\Resources\Suppliers\Widgets\StatsOverviewGeneralSupplier;
use App\Filament\Operations\Resources\Suppliers\Widgets\StatsOverviewPreferencialSupplier;
use App\Filament\Operations\Resources\Suppliers\Widgets\SupplierClasificationChart;
use App\Filament\Operations\Resources\Suppliers\Widgets\SupplierForState;
use App\Filament\Operations\Resources\Suppliers\Widgets\SupplierStatsOverviewFirts;
use App\Mail\SupplierReportMail;
use App\Models\Supplier;
use App\Services\SupplierReportDownloadZoneService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Throwable;

class ListSuppliers extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Lista de Proveedores';

    /**
     * Orden de pestañas por tipo de convenio (alineado con totales GENERAL / PREFERENCIAL en widgets).
     *
     * @var list<string>
     */
    private const CONVENIO_TAB_ORDER = [
        'GENERAL',
    ];

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public ?string $supplierReportEmail = null;

    public function sendSupplierReportPdf(): void
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $this->validate([
            'supplierReportEmail' => ['required', 'email', 'max:255'],
        ]);

        Mail::to($this->supplierReportEmail)->send(new SupplierReportMail);

        Notification::make()
            ->success()
            ->title('Correo enviado')
            ->body('Se adjuntó el PDF del reporte de proveedores al mensaje.')
            ->send();

        $this->supplierReportEmail = null;
    }

    public function moveSupplierReportToDownloadZone(): void
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        try {
            SupplierReportDownloadZoneService::publish();

            Notification::make()
                ->success()
                ->title('Zona de descarga actualizada')
                ->body('El PDF de proveedores se guardó en download-zone y se actualizó el registro.')
                ->send();
        } catch (ModelNotFoundException) {
            Notification::make()
                ->danger()
                ->title('Registro no encontrado')
                ->body('No existe la zona de descarga con ID '.SupplierReportDownloadZoneService::DOWNLOAD_ZONE_ID.'.')
                ->send();
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->danger()
                ->title('Error al publicar')
                ->body('No se pudo guardar el archivo. Intente de nuevo o contacte a sistemas.')
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo Proveedor')
                ->icon('heroicon-s-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
            Action::make('report_suppliers')
                ->label('Reporte de Proveedores')
                ->icon('heroicon-s-document-text')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ])
                ->modalHeading('Reporte de proveedores')
                ->modalDescription('Listado ordenado por estado y ciudad (A–Z). Vista previa, descarga o envío por correo.')
                ->modalWidth(Width::SevenExtraLarge)
                ->modalIcon('heroicon-o-document-text')
                ->modalContent(fn (): ViewContract => View::make('filament.operations.suppliers.suppliers-report-modal', [
                    'pdfPreviewUrl' => route('operations.suppliers.report.preview'),
                    'pdfDownloadUrl' => route('operations.suppliers.report.download'),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->action(fn () => null),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SupplierStatsOverviewFirts::class,
            StatsOverviewGeneralSupplier::class,
            StatsOverviewPreferencialSupplier::class,
            SupplierForState::class,
            SupplierClasificationChart::class,
        ];
    }

    public function getTabsContentComponent(): Component
    {
        $tabs = $this->getCachedTabs();

        return Tabs::make('Filtrar por tipo de convenio')
            ->livewireProperty('activeTab')
            ->contained(false)
            ->extraAttributes([
                'class' => 'fi-supplier-convenio-tabs-ios fi-supplier-status-tabs-ios',
            ])
            ->tabs($tabs)
            ->hidden(empty($tabs));
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        /** @var Collection<string|null, int> $counts */
        $counts = Supplier::query()
            ->selectRaw('status_convenio, COUNT(*) as aggregate')
            ->groupBy('status_convenio')
            ->pluck('aggregate', 'status_convenio');

        $total = (int) $counts->sum();

        $tabs = [
            'all' => Tab::make('Todos')
                ->badge((string) $total)
                ->badgeColor('primary')
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query),
        ];

        $usedKeys = ['all' => true];

        $sortedConvenios = $this->sortConveniosForTabs($counts->keys());

        foreach ($sortedConvenios as $convenio) {
            $count = (int) $counts->get($convenio, 0);
            $key = $this->uniqueTabKeyForConvenio($convenio, $usedKeys);
            $usedKeys[$key] = true;

            $label = $convenio === null
                ? 'Sin tipo de convenio'
                : (string) $convenio;

            $tabs[$key] = Tab::make($label)
                ->badge((string) $count)
                ->badgeColor($this->badgeColorForConvenio($convenio))
                ->extraAttributes([
                    'class' => 'fi-supplier-status-tab-pill',
                ])
                ->modifyQueryUsing(function (Builder $query) use ($convenio): Builder {
                    if ($convenio === null) {
                        return $query->whereNull('suppliers.status_convenio');
                    }

                    return $query->where('suppliers.status_convenio', $convenio);
                });
        }

        return $tabs;
    }

    private function badgeColorForConvenio(?string $convenio): string
    {
        if ($convenio === null) {
            return 'warning';
        }

        $u = strtoupper($convenio);

        if ($u === 'GENERAL' || str_contains($u, 'GENERAL')) {
            return 'info';
        }

        if (str_contains($u, 'PREFERENCIAL')) {
            return 'success';
        }

        return 'gray';
    }

    /**
     * @param  Collection<int, string|null>  $keys
     * @return Collection<int, string|null>
     */
    private function sortConveniosForTabs(Collection $keys): Collection
    {
        return $keys->sortBy(function (?string $convenio): array {
            if ($convenio === null) {
                return [2000, ''];
            }

            $upper = strtoupper($convenio);

            if ($upper === 'GENERAL') {
                return [0, $upper];
            }

            if (str_contains($upper, 'PREFERENCIAL')) {
                return [100, $upper];
            }

            if (str_contains($upper, 'GENERAL')) {
                return [40, $upper];
            }

            $pos = array_search($upper, self::CONVENIO_TAB_ORDER, true);

            return [
                $pos !== false ? 50 + $pos : 500,
                $upper,
            ];
        })->values();
    }

    /**
     * @param  array<string, bool>  $usedKeys
     */
    private function uniqueTabKeyForConvenio(?string $convenio, array &$usedKeys): string
    {
        $base = $convenio === null
            ? 'sin_convenio'
            : Str::slug(Str::ascii($convenio), '_');

        if ($base === '') {
            $base = 'convenio';
        }

        $key = $base;
        $i = 2;

        while (isset($usedKeys[$key])) {
            $key = $base.'_'.$i;
            $i++;
        }

        return $key;
    }
}
