<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Pages;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Models\AffiliationCorporate;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class EditAffiliationCorporate extends EditRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var AffiliationCorporate $affiliationCorporate */
        $affiliationCorporate = $this->getRecord();
        $affiliationCorporate->loadMissing(['affiliationCorporatePlans.plan']);

        $corporateName = trim((string) ($affiliationCorporate->name_corporate ?? ''));
        if ($corporateName === '') {
            $corporateName = 'Sin nombre';
        }

        $rif = trim((string) ($affiliationCorporate->rif ?? ''));
        $rifLabel = $rif !== '' ? $rif : 'Sin RIF/CI';

        $planNames = $affiliationCorporate->affiliationCorporatePlans
            ->pluck('plan.description')
            ->filter(fn (mixed $description): bool => filled($description))
            ->map(fn (mixed $description): string => (string) $description)
            ->unique()
            ->values();

        $plansLabel = $planNames->isEmpty()
            ? 'Sin plan afiliado'
            : $planNames->implode(', ');

        $affiliatesCount = $affiliationCorporate->corporateAffiliates()->count();
        $affiliatesLabel = $affiliatesCount === 1
            ? '1 afiliado corporativo'
            : "{$affiliatesCount} afiliados corporativos";

        return new HtmlString(
            '<div style="display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; gap: 4px; padding: 4px 0;">'
            .'<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">'
            .e($corporateName)
            .'</span>'
            .'<span class="text-sm font-medium tracking-tight text-gray-600 dark:text-gray-300">'
            .'RIF/CI: '.e($rifLabel)
            .' · Plan(es): '.e($plansLabel)
            .' · '.e($affiliatesLabel)
            .'</span>'
            .'</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Afiliación Actualizada Correctamente';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
