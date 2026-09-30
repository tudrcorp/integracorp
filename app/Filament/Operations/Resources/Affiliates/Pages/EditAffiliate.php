<?php

namespace App\Filament\Operations\Resources\Affiliates\Pages;

use App\Filament\Operations\Concerns\EditsAffiliatePersonalData;
use App\Filament\Operations\Resources\Affiliates\AffiliateResource;
use App\Filament\Operations\Resources\Affiliates\Schemas\AffiliatePersonalDataForm;
use App\Models\Affiliate;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Edición de datos personales del afiliado individual desde Operaciones.
 */
class EditAffiliate extends EditRecord
{
    use EditsAffiliatePersonalData;

    protected static string $resource = AffiliateResource::class;

    /**
     * Misma línea visual que la ficha del afiliado (ViewAffiliate): nombre en
     * grande, estado y datos clave en chips.
     */
    public function getHeading(): string|Htmlable
    {
        /** @var Affiliate $affiliate */
        $affiliate = $this->getRecord();
        $affiliate->loadMissing('affiliation:id,code');

        return new HtmlString(view('filament.operations.affiliates.edit-header', [
            'name' => $affiliate->full_name,
            'status' => $affiliate->status,
            'chips' => [
                'C.I.' => $affiliate->nro_identificacion,
                'Afiliación' => $affiliate->affiliation?->code,
                'Parentesco' => $affiliate->relationship,
            ],
        ])->render());
    }

    public function form(Schema $schema): Schema
    {
        return AffiliatePersonalDataForm::configure($schema);
    }
}
