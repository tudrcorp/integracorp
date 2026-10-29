<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\ProviderNetwork;

use App\Enums\ProviderNetworkType;
use App\Support\Metrics\Corporate\CorporateGeography;
use App\Support\Operations\Providers\ProviderNetworkAnalytics;
use App\Support\Operations\Providers\ProviderNetworkCatalog;
use Livewire\Attributes\On;

/**
 * Listado de los proveedores detrás de cualquier número del dashboard. Se abre al hacer clic en
 * un estado del mapa, una barra o un indicador, y enlaza a la ficha de cada proveedor.
 */
class ProviderNetworkDetail extends ProviderNetworkWidget
{
    protected static ?int $sort = 9;

    protected string $view = 'filament.operations.providers.detail';

    protected int|string|array $columnSpan = 'full';

    public const LIMIT = 300;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $detailFilters = null;

    public string $detailTitle = '';

    /**
     * @param  array<string, mixed>  $filters
     */
    #[On('provider-network-detail')]
    public function openDetail(array $filters, string $title): void
    {
        $allowed = ['type', 'status', 'agreement', 'geo', 'letter', 'class', 'abroad', 'active', 'preferential', 'missing_location'];
        $this->detailFilters = array_intersect_key($filters, array_flip($allowed));
        $this->detailTitle = mb_substr($title, 0, 200);
        $this->dispatch('provider-network-detail-opened');
    }

    public function closeDetail(): void
    {
        $this->detailFilters = null;
        $this->detailTitle = '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        if ($this->detailFilters === null) {
            return ['rows' => [], 'total' => 0, 'open' => false];
        }

        $rows = array_map(static function (array $row): array {
            $type = ProviderNetworkType::from($row['type']);

            return [
                ...$row,
                'type_label' => $type->label(),
                'type_color' => $type->color(),
                'status_label' => $row['status'] === ProviderNetworkCatalog::STATUS_OTHER ? ProviderNetworkCatalog::label($row['status_raw']) : ProviderNetworkCatalog::statusLabel($row['status']),
                'status_color' => ProviderNetworkCatalog::STATUSES[$row['status']]['color'] ?? 'var(--ops-muted)',
                'agreement_label' => ProviderNetworkCatalog::agreementLabel($row['agreement']),
                'place' => $row['abroad'] ?? ($row['geo'] ?? CorporateGeography::UNKNOWN),
                'url' => $type->viewUrl($row['id']),
            ];
        }, ProviderNetworkAnalytics::detail($this->detailFilters, self::LIMIT));

        return [
            'rows' => $rows,
            'total' => ProviderNetworkAnalytics::count($this->detailFilters),
            'open' => true,
            'limit' => self::LIMIT,
        ];
    }
}
