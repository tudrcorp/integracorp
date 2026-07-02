<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Companies\Pages;

use App\Filament\Business\Resources\Companies\CompanyResource;
use App\Filament\Business\Resources\CompanyAssociates\CompanyAssociateResource;
use App\Filament\Business\Resources\PlanGenerators\PlanGeneratorResource;
use App\Models\Company;
use App\Models\CompanyAssociate;
use App\Models\CompanyResponsible;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    protected static ?string $title = 'Empresas';

    public function getSubheading(): string|Htmlable|null
    {
        $stats = $this->overviewStats();

        return new HtmlString(
            '<div class="space-y-2">'
            .'<p class="text-sm text-gray-600 dark:text-gray-300">Empresas registradas desde cotizaciones aprobadas. Revise población del plan, responsables y días contratados en un solo vistazo.</p>'
            .'<div class="flex flex-wrap items-center gap-2">'
            .self::statBadge('Empresas', $stats['companies'], 'primary')
            .self::statBadge('Responsables', $stats['responsibles'], 'info')
            .self::statBadge('Asociados', $stats['associates'], 'success')
            .'</div>'
            .'</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('goToPlanGenerators')
                ->label('Generador de planes')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('success')
                ->url(PlanGeneratorResource::getUrl('index', panel: 'business'))
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ]),
            Action::make('viewAssociates')
                ->label('Ver asociados')
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('info')
                ->url(CompanyAssociateResource::getUrl('index', panel: 'business'))
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                ]),
        ];
    }

    /**
     * @return array{companies: int, responsibles: int, associates: int}
     */
    private function overviewStats(): array
    {
        return [
            'companies' => Company::query()->count(),
            'responsibles' => CompanyResponsible::query()->count(),
            'associates' => CompanyAssociate::query()->count(),
        ];
    }

    private static function statBadge(string $label, int $count, string $tone): string
    {
        $classes = match ($tone) {
            'info' => 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-200',
            'success' => 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-200',
            default => 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-200',
        };

        return '<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold '.$classes.'">'
            .e($label).': <strong>'.number_format($count).'</strong>'
            .'</span>';
    }
}
