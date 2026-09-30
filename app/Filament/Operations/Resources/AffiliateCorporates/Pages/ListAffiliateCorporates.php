<?php

namespace App\Filament\Operations\Resources\AffiliateCorporates\Pages;

use App\Filament\Operations\Resources\AffiliateCorporates\AffiliateCorporateResource;
use App\Support\Operations\AffiliateListHeaderSummary;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListAffiliateCorporates extends ListRecords
{
    protected static string $resource = AffiliateCorporateResource::class;

    protected static ?string $title = 'Afiliados Corporativos';

    public function getHeading(): string|Htmlable
    {
        $summary = AffiliateListHeaderSummary::forAffiliates(
            AffiliateCorporateResource::getEloquentQuery(),
            companyColumn: 'affiliation_corporate_id',
        );

        return new HtmlString(view('filament.operations.partials.list-header', [
            'icon' => 'heroicon-o-building-office-2',
            'eyebrow' => 'Afiliados · Corporativos',
            'title' => 'Afiliados corporativos',
            'total' => $summary['total'],
            'totalHint' => 'Total de afiliados corporativos que puede consultar',
            'description' => 'Población cubierta por afiliaciones corporativas, del registro más reciente al más antiguo.',
            'stats' => [
                ['label' => 'Activos', 'value' => $summary['active'], 'icon' => 'heroicon-m-check-circle', 'tone' => 'success', 'hint' => 'Afiliados con cobertura vigente'],
                ['label' => 'Pre-aprobados', 'value' => $summary['pre_approved'], 'icon' => 'heroicon-m-clock', 'tone' => 'warning', 'hint' => 'Afiliados aún en revisión, sin cobertura activa'],
                ['label' => 'Excluidos', 'value' => $summary['excluded'], 'icon' => 'heroicon-m-no-symbol', 'tone' => 'danger', 'hint' => 'Afiliados retirados de su afiliación corporativa'],
                ['label' => 'Afiliaciones', 'value' => $summary['companies'], 'icon' => 'heroicon-m-briefcase', 'tone' => 'info', 'hint' => 'Afiliaciones corporativas distintas con población en esta lista'],
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
