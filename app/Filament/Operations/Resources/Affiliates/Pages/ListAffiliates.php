<?php

namespace App\Filament\Operations\Resources\Affiliates\Pages;

use App\Filament\Operations\Resources\Affiliates\AffiliateResource;
use App\Support\Operations\AffiliateListHeaderSummary;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListAffiliates extends ListRecords
{
    protected static string $resource = AffiliateResource::class;

    protected static ?string $title = 'Afiliados Individuales';

    public function getHeading(): string|Htmlable
    {
        $summary = AffiliateListHeaderSummary::forAffiliates(AffiliateResource::getEloquentQuery());

        return new HtmlString(view('filament.operations.partials.list-header', [
            'icon' => 'heroicon-o-user',
            'eyebrow' => 'Afiliados · TDEC',
            'title' => 'Afiliados individuales',
            'total' => $summary['total'],
            'totalHint' => 'Total de afiliados individuales que puede consultar',
            'description' => 'Titulares y beneficiarios de afiliaciones individuales, del más reciente al más antiguo. El nombre se resalta en verde cuando el afiliado quedó ACTIVO hoy.',
            'stats' => [
                ['label' => 'Activos', 'value' => $summary['active'], 'icon' => 'heroicon-m-check-circle', 'tone' => 'success', 'hint' => 'Afiliados con cobertura vigente'],
                ['label' => 'Pre-aprobados', 'value' => $summary['pre_approved'], 'icon' => 'heroicon-m-clock', 'tone' => 'warning', 'hint' => 'Afiliaciones aún en revisión, sin cobertura activa'],
                ['label' => 'Excluidos', 'value' => $summary['excluded'], 'icon' => 'heroicon-m-no-symbol', 'tone' => 'danger', 'hint' => 'Afiliados retirados de su afiliación'],
                ['label' => 'Nuevos hoy', 'value' => $summary['today'], 'icon' => 'heroicon-m-sparkles', 'tone' => 'primary', 'hint' => 'Afiliados que quedaron activos hoy'],
            ],
        ])->render());
    }

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
