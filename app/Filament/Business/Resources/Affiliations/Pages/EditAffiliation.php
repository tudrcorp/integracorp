<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliations\Pages;

use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Models\Affiliation;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class EditAffiliation extends EditRecord
{
    protected static string $resource = AffiliationResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Affiliation $affiliation */
        $affiliation = $this->getRecord();
        $affiliation->loadMissing(['plan']);

        $titularName = trim((string) ($affiliation->full_name_ti ?? ''));
        if ($titularName === '') {
            $titularName = 'Sin nombre';
        }

        $document = trim((string) ($affiliation->nro_identificacion_ti ?? ''));
        $documentLabel = $document !== '' ? $document : 'Sin RIF/CI';

        $planName = trim((string) ($affiliation->plan?->description ?? ''));
        $plansLabel = $planName !== '' ? $planName : 'Sin plan afiliado';

        $affiliatesCount = $affiliation->affiliates()->count();
        $affiliatesLabel = $affiliatesCount === 1
            ? '1 afiliado'
            : "{$affiliatesCount} afiliados";

        return new HtmlString(
            '<div style="display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; gap: 4px; padding: 4px 0;">'
            .'<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">'
            .e($titularName)
            .'</span>'
            .'<span class="text-sm font-medium tracking-tight text-gray-600 dark:text-gray-300">'
            .'RIF/CI: '.e($documentLabel)
            .' · Plan(es): '.e($plansLabel)
            .' · '.e($affiliatesLabel)
            .'</span>'
            .'</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [

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
