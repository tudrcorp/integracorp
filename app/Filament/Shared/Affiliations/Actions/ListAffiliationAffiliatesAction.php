<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations\Actions;

use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Support\Filament\CommercialNetworkAccess;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

/**
 * Acción de fila de las tablas de afiliaciones de la red comercial (agentes, agencias
 * MASTER y GENERAL): abre la población de la afiliación y permite descargar, por cada
 * persona, el condicionado de su plan junto con su carnet.
 *
 * Sirve para afiliaciones individuales y corporativas: el tipo se deduce del registro.
 */
final class ListAffiliationAffiliatesAction
{
    public static function make(): Action
    {
        return Action::make('list_affiliates')
            ->label('Listar Afiliados')
            ->icon('heroicon-o-users')
            ->color('info')
            ->modalHeading(fn (Model $record): string => 'Afiliados de '.($record->code ?? ''))
            ->modalDescription('Población cubierta por esta afiliación. Descargue la documentación de cada afiliado: el condicionado de su plan y su carnet.')
            ->modalIcon('heroicon-o-users')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->visible(fn (): bool => self::isCommercialNetworkUser())
            ->modalContent(fn (Model $record): ViewContract => View::make(
                'filament.shared.affiliations.affiliation-affiliates-modal',
                [
                    'affiliationId' => (int) $record->getKey(),
                    'corporate' => $record instanceof AffiliationCorporate,
                ],
            ))
            ->action(fn () => null);
    }

    private static function isCommercialNetworkUser(): bool
    {
        $user = Auth::user();

        return $user instanceof User && CommercialNetworkAccess::isCommercialNetworkUser($user);
    }
}
