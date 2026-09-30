<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\CompanyAssociates\Pages;

use App\Filament\Operations\Resources\CompanyAssociates\NuevosNegociosAssociateResource;
use App\Support\Operations\AffiliateListHeaderSummary;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListCompanyAssociates extends ListRecords
{
    protected static string $resource = NuevosNegociosAssociateResource::class;

    protected static ?string $title = 'Asociados de Nuevos Negocios';

    public function getHeading(): string|Htmlable
    {
        $summary = AffiliateListHeaderSummary::forCompanyAssociates(NuevosNegociosAssociateResource::getEloquentQuery());

        return new HtmlString(view('filament.operations.partials.list-header', [
            'icon' => 'heroicon-o-user-plus',
            'eyebrow' => 'Afiliados · Nuevos Negocios',
            'title' => 'Asociados de nuevos negocios',
            'total' => $summary['total'],
            'totalHint' => 'Total de asociados que puede consultar',
            'description' => 'Personas registradas por las empresas y responsables de Nuevos Negocios desde su enlace de alta.',
            'stats' => [
                ['label' => 'Activos', 'value' => $summary['active'], 'icon' => 'heroicon-m-check-circle', 'tone' => 'success', 'hint' => 'Asociados activos con voucher ILS'],
                ['label' => 'Sin voucher ILS', 'value' => $summary['without_voucher'], 'icon' => 'heroicon-m-exclamation-triangle', 'tone' => 'warning', 'hint' => 'Asociados activos que aún no tienen voucher ILS'],
                ['label' => 'Anulados', 'value' => $summary['cancelled'], 'icon' => 'heroicon-m-no-symbol', 'tone' => 'danger', 'hint' => 'Registros anulados'],
                ['label' => 'Empresas', 'value' => $summary['companies'], 'icon' => 'heroicon-m-building-office', 'tone' => 'info', 'hint' => 'Empresas distintas con asociados en esta lista'],
            ],
        ])->render());
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
