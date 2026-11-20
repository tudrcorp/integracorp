<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\ApiPartners\Pages;

use App\Filament\Operations\Resources\ApiPartners\Actions\ApiPartnerTokenActions;
use App\Filament\Operations\Resources\ApiPartners\ApiPartnerResource;
use App\Models\ApiPartner;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewApiPartner extends ViewRecord
{
    protected static string $resource = ApiPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ApiPartnerTokenActions::issue(function (string $token): void {
                $this->getRecord()->refresh();
                $this->replaceMountedAction(ApiPartnerTokenActions::SHOW_TOKEN_ACTION, ['token' => $token]);
            }),
            ApiPartnerTokenActions::revoke()->after(fn () => $this->getRecord()->refresh()),
            Action::make('documentation')
                ->label('Ver documentación')
                ->icon(Heroicon::OutlinedBookOpen)
                ->color('gray')
                ->url(fn (): string => ApiPartner::documentationUrl(), shouldOpenInNewTab: true),
            EditAction::make(),
        ];
    }

    /**
     * Modal del token recién generado. Se abre solo desde «Generar token»; no tiene botón propio.
     */
    public function showIssuedTokenAction(): Action
    {
        return ApiPartnerTokenActions::show();
    }
}
