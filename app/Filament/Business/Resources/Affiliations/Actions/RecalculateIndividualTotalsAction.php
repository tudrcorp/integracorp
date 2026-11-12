<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliations\Actions;

use App\Models\Affiliation;
use App\Models\User;
use App\Support\Affiliations\ActivePopulationIndividualTotals;
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
 * Recalcula población, tarifa anual y cuota según afiliados activos; alinea avisos en Administración.
 */
final class RecalculateIndividualTotalsAction
{
    public static function make(): Action
    {
        return self::configure(null);
    }

    public static function forOwner(Closure $ownerResolver): Action
    {
        return self::configure($ownerResolver);
    }

    private static function configure(?Closure $ownerResolver): Action
    {
        $resolve = static function (?Affiliation $record = null) use ($ownerResolver): Affiliation {
            if ($ownerResolver !== null) {
                $owner = $ownerResolver();

                abort_unless($owner instanceof Affiliation, 404, 'Afiliación no encontrada.');

                return $owner;
            }

            abort_unless($record instanceof Affiliation, 404, 'Afiliación no encontrada.');

            return $record;
        };

        return Action::make('recalculateTotals')
            ->label('Recalcular totales')
            ->icon(Heroicon::OutlinedCalculator)
            ->color('warning')
            ->visible(function (?Affiliation $record = null) use ($resolve): bool {
                $target = $resolve($record);

                return ! $target->trashed()
                    && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::RECALCULATE_INDIVIDUAL_TOTALS);
            })
            ->modalHeading(function (?Affiliation $record = null) use ($resolve): string {
                return 'Recalcular totales · '.$resolve($record)->code;
            })
            ->modalDescription(
                'Guarda en la afiliación la población, la tarifa anual y la cuota que suman sus afiliados activos (ACTIVO y PRE-APROBADA). '
                .'También actualiza los avisos POR PAGAR del año en curso en Administración/Cobranza; los avisos ya pagados o con abono no cambian. '
                .'Solo analistas autorizados por un SUPERADMIN ven esta acción (permiso en usuarios).',
            )
            ->modalIcon(Heroicon::OutlinedCalculator)
            ->modalIconColor('warning')
            ->modalWidth(Width::ThreeExtraLarge)
            ->schema([
                Placeholder::make('preview')
                    ->hiddenLabel()
                    ->content(function (?Affiliation $record = null) use ($resolve): HtmlString {
                        $target = $resolve($record);

                        return new HtmlString(view(
                            'filament.business.affiliations.recalculate-totals-preview',
                            ['preview' => ActivePopulationIndividualTotals::preview($target), 'record' => $target],
                        )->render());
                    }),
                Textarea::make('reason')
                    ->label('Motivo del recálculo')
                    ->placeholder('Ej.: Los totales no cuadraban con los afiliados activos después de altas manuales.')
                    ->rows(2)
                    ->required()
                    ->minLength(ActivePopulationIndividualTotals::REASON_MIN_LENGTH)
                    ->maxLength(1000),
            ])
            ->modalSubmitActionLabel('Guardar totales')
            ->action(function (array $data, Action $action, ?Affiliation $record = null) use ($resolve): void {
                abort_unless(BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::RECALCULATE_INDIVIDUAL_TOTALS), 403);
                $user = Auth::user();
                abort_unless($user instanceof User, 403);

                $target = $resolve($record);

                try {
                    $result = ActivePopulationIndividualTotals::recalculate($target, $user, (string) ($data['reason'] ?? ''));
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
                    ->body('Población '.$result['before']['family_members'].' → '.$result['after']['family_members']
                        .' · Tarifa anual US$ '.number_format($result['before']['fee_anual'], 2, ',', '.').' → '.number_format($result['after']['fee_anual'], 2, ',', '.')
                        .' · Cuota US$ '.number_format($result['before']['total_amount'], 2, ',', '.').' → '.number_format($result['after']['total_amount'], 2, ',', '.')
                        .' · '.count($result['collections']).' aviso(s) POR PAGAR alineado(s) en Administración.')
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
