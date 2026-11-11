<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Actions;

use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Support\AffiliationCorporates\ActivePopulationTotals;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
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
        return Action::make('recalculateTotals')
            ->label('Recalcular totales')
            ->icon(Heroicon::OutlinedCalculator)
            ->color('warning')
            ->visible(fn (AffiliationCorporate $record): bool => ! $record->trashed()
                && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::RECALCULATE_CORPORATE_TOTALS))
            ->modalHeading(fn (AffiliationCorporate $record): string => 'Recalcular totales · '.$record->code)
            ->modalDescription('Guarda en la afiliación la población, la tarifa anual y la cuota que suman sus afiliados activos (ACTIVO y PRE-APROBADA). Los avisos de cobro ya emitidos no cambian: si hay diferencia por cobrar o devolver, gestiónela en Administración → «Ajustes por población».')
            ->modalIcon(Heroicon::OutlinedCalculator)
            ->modalIconColor('warning')
            ->modalWidth(Width::ThreeExtraLarge)
            ->schema([
                Placeholder::make('preview')
                    ->hiddenLabel()
                    ->content(fn (AffiliationCorporate $record): HtmlString => new HtmlString(view(
                        'filament.business.affiliation-corporates.recalculate-totals-preview',
                        ['preview' => ActivePopulationTotals::preview($record), 'record' => $record],
                    )->render())),
                Textarea::make('reason')
                    ->label('Motivo del recálculo')
                    ->placeholder('Ej.: Los totales no cuadraban con la población activa después de varias bajas manuales.')
                    ->rows(2)
                    ->required()
                    ->minLength(ActivePopulationTotals::REASON_MIN_LENGTH)
                    ->maxLength(1000),
            ])
            ->modalSubmitActionLabel('Guardar totales')
            ->action(function (array $data, AffiliationCorporate $record, Action $action): void {
                abort_unless(BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::RECALCULATE_CORPORATE_TOTALS), 403);
                $user = Auth::user();
                abort_unless($user instanceof User, 403);

                try {
                    $result = ActivePopulationTotals::recalculate($record, $user, (string) ($data['reason'] ?? ''));
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
                        .($result['rows_updated'] ? '. Filas de plan actualizadas.' : '. Las filas de plan no se tocaron (hay afiliados sin fila de plan).'))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
