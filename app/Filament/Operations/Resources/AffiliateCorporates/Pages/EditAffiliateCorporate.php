<?php

namespace App\Filament\Operations\Resources\AffiliateCorporates\Pages;

use App\Filament\Operations\Concerns\EditsAffiliatePersonalData;
use App\Filament\Operations\Resources\AffiliateCorporates\AffiliateCorporateResource;
use App\Filament\Operations\Resources\AffiliateCorporates\Schemas\AffiliateCorporatePersonalDataForm;
use App\Models\AffiliateCorporate;
use App\Support\Operations\AffiliateUpdateNotificationMessage;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Edición de datos personales del afiliado corporativo desde Operaciones.
 */
class EditAffiliateCorporate extends EditRecord
{
    use EditsAffiliatePersonalData;

    protected static string $resource = AffiliateCorporateResource::class;

    public function getHeading(): string|Htmlable
    {
        /** @var AffiliateCorporate $affiliate */
        $affiliate = $this->getRecord();
        $affiliate->loadMissing('affiliationCorporate:id,code,name_corporate');

        return new HtmlString(view('filament.operations.affiliates.edit-header', [
            'eyebrow' => 'Editar datos del afiliado corporativo',
            'name' => AffiliateUpdateNotificationMessage::displayName($affiliate),
            'status' => $affiliate->status,
            'chips' => [
                'C.I.' => $affiliate->nro_identificacion,
                'Empresa' => $affiliate->affiliationCorporate?->name_corporate,
                'Afiliación' => $affiliate->affiliationCorporate?->code,
            ],
        ])->render());
    }

    public function form(Schema $schema): Schema
    {
        return AffiliateCorporatePersonalDataForm::configure($schema);
    }
}
