<?php

namespace App\Filament\Operations\Resources\Affiliates\Pages;

use App\Filament\Operations\Resources\Affiliates\AffiliateResource;
use App\Models\Affiliate;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliate extends ViewRecord
{
    protected static string $resource = AffiliateResource::class;

    protected static ?string $title = 'Información Detallada del Afiliado';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(AffiliateResource::getUrl())
        ];
    }
}
