<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CompanyAssociates\Pages;

use App\Filament\Business\Resources\CompanyAssociates\CompanyAssociateResource;
use App\Models\CompanyAssociate;
use App\Support\Companies\CompanyAssociatePageHeader;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ViewCompanyAssociate extends ViewRecord
{
    protected static string $resource = CompanyAssociateResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        /** @var CompanyAssociate $record */
        $record = parent::resolveRecord($key);
        $record->loadMissing([
            'company',
            'responsible',
            'state',
            'city',
        ]);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar información')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('primary')
                ->visible(fn (CompanyAssociate $record): bool => ! $record->isAnnulled())
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
            Action::make('back')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(CompanyAssociateResource::getUrl())
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('gray'),
                ]),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        /** @var CompanyAssociate $associate */
        $associate = $this->getRecord();

        return CompanyAssociatePageHeader::make($associate);
    }
}
