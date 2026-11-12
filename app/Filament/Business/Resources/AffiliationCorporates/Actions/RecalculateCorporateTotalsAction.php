<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Actions;

use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Support\AffiliationCorporates\ActivePopulationTotals;
use App\Support\Affiliations\ActiveBilling\ActivePopulationBilling;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;

/**
 * «Recalcular totales»: guarda en la afiliación la población, la tarifa anual
 * y la cuota de sus afiliados activos (ver {@see ActivePopulationTotals}).
 */
final class RecalculateCorporateTotalsAction
{
    public static function make(): Action
    {
        return self::configure(null);
    }

    /**
     * Para RelationManagers: la acción no tiene fila de tabla, solo el dueño de la relación.
     */
    public static function forOwner(Closure $ownerResolver): Action
    {
        return self::configure($ownerResolver);
    }

    private static function configure(?Closure $ownerResolver): Action
    {
        $resolve = static function (?AffiliationCorporate $record = null) use ($ownerResolver): AffiliationCorporate {
            if ($ownerResolver !== null) {
                $owner = $ownerResolver();

                abort_unless($owner instanceof AffiliationCorporate, 404, 'Afiliación corporativa no encontrada.');

                return $owner;
            }

            abort_unless($record instanceof AffiliationCorporate, 404, 'Afiliación corporativa no encontrada.');

            return $record;
        };

        return Action::make('recalculateTotals')
            ->label('Recalcular totales')
            ->icon(Heroicon::OutlinedCalculator)
            ->color('warning')
            ->visible(function (?AffiliationCorporate $record = null) use ($resolve): bool {
                $target = $resolve($record);

                return ! $target->trashed()
                    && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::RECALCULATE_CORPORATE_TOTALS);
            })
            ->modalHeading(function (?AffiliationCorporate $record = null) use ($resolve): string {
                return 'Recalcular totales · '.$resolve($record)->code;
            })
            ->modalDescription(
                'Guarda en la afiliación la población, la tarifa anual, la cuota y los subtotales de cada fila de plan según los afiliados activos (ACTIVO y PRE-APROBADA). '
                .'También alinea los avisos POR PAGAR del año en Administración/Cobranza; los avisos pagados o con abono no cambian. '
                .'Solo analistas autorizados por un SUPERADMIN ven esta acción (permiso en usuarios).',
            )
            ->modalIcon(Heroicon::OutlinedCalculator)
            ->modalIconColor('warning')
            ->modalWidth(Width::ThreeExtraLarge)
            ->schema([
                Placeholder::make('preview')
                    ->hiddenLabel()
                    ->content(function (?AffiliationCorporate $record = null) use ($resolve): HtmlString {
                        $target = $resolve($record);

                        return new HtmlString(view(
                            'filament.business.affiliation-corporates.recalculate-totals-preview',
                            [
                                'preview' => ActivePopulationTotals::preview($target),
                                'record' => $target,
                                'billing' => ActivePopulationBilling::plan($target),
                            ],
                        )->render());
                    }),
                Textarea::make('reason')
                    ->label('Motivo del recálculo')
                    ->placeholder('Ej.: Los totales no cuadraban con la población activa después de varias bajas manuales.')
                    ->rows(2)
                    ->required()
                    ->minLength(ActivePopulationTotals::REASON_MIN_LENGTH)
                    ->maxLength(1000),
            ])
            ->modalSubmitActionLabel('Guardar totales')
            ->action(function (array $data, Action $action, ?AffiliationCorporate $record = null) use ($resolve): void {
                abort_unless(BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::RECALCULATE_CORPORATE_TOTALS), 403);
                $user = Auth::user();
                abort_unless($user instanceof User, 403);

                $target = $resolve($record);

                try {
                    $result = ActivePopulationTotals::recalculate($target, $user, (string) ($data['reason'] ?? ''));
                } catch (RuntimeException $exception) {
                    Notification::make()->title('No se recalculó')->body($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title('No se recalculó')->body('Error inesperado. No se cambió nada.')->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title('Totales recalculados')
                    ->body('Población '.$result['before']['poblation'].' → '.$result['after']['poblation']
                        .' · Tarifa anual US$ '.number_format($result['before']['fee_anual'], 2, ',', '.').' → '.number_format($result['after']['fee_anual'], 2, ',', '.')
                        .' · Cuota US$ '.number_format($result['before']['total_amount'], 2, ',', '.').' → '.number_format($result['after']['total_amount'], 2, ',', '.')
                        .($result['rows_updated'] ? '. Filas de plan y subtotales actualizados.' : '. Las filas de plan no se tocaron (hay afiliados sin fila de plan).')
                        .' '.count($result['collections']).' aviso(s) POR PAGAR alineado(s) en Administración.')
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
