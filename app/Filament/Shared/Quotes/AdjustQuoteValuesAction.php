<?php

declare(strict_types=1);

namespace App\Filament\Shared\Quotes;

use App\Models\User;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Quotes\Adjustments\QuoteValueAdjustments;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;

/**
 * «Ajustar valores»: tarifa y personas de cada línea de la cotización, con vista previa de lo
 * que cambia en la afiliación enlazada y en sus avisos de cobro ({@see QuoteValueAdjustments}).
 */
final class AdjustQuoteValuesAction
{
    public static function canUse(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::ADJUST_QUOTE_VALUES);
    }

    public static function make(): Action
    {
        return Action::make('adjustQuoteValues')
            ->label('Ajustar valores')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('warning')
            ->button()
            ->visible(fn (): bool => self::canUse())
            ->modalHeading(fn (Model $record): string => 'Ajustar valores · '.$record->code)
            ->modalDescription('Corrija la tarifa por persona y la cantidad de personas. Lo que cambie llega solo a la afiliación de esta cotización y a sus avisos POR PAGAR del año en curso; lo ya pagado no se toca. Queda en el historial y se puede revertir.')
            ->modalIcon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->modalIconColor('warning')
            ->modalWidth(Width::FiveExtraLarge)
            ->fillForm(fn (Model $record): array => [
                'lines' => array_map(static fn (array $line): array => [
                    'detail_id' => $line['detail_id'],
                    'label' => $line['label'],
                    'contracted' => $line['contracted'],
                    'fee_before' => $line['fee'],
                    'persons_before' => $line['persons'],
                    'fee' => $line['fee'],
                    'persons' => $line['persons'],
                ], QuoteValueAdjustments::lines($record)),
            ])
            ->schema(fn (Model $record): array => [
                Repeater::make('lines')
                    ->label('Líneas de la cotización')
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columns(12)
                    ->schema([
                        Hidden::make('detail_id'),
                        Hidden::make('label'),
                        Hidden::make('contracted'),
                        Hidden::make('fee_before'),
                        Hidden::make('persons_before'),
                        Placeholder::make('line_label')
                            ->hiddenLabel()
                            ->columnSpan(6)
                            ->content(fn (Get $get): HtmlString => new HtmlString(
                                '<span class="font-medium text-gray-950 dark:text-white">'.e((string) $get('label')).'</span>'
                                .($get('contracted') ? ' <span class="ms-1 rounded-md bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300">En la afiliación</span>' : '')
                                .'<br><span class="text-xs text-gray-500 dark:text-gray-400">Hoy: US$ '.e((string) $get('fee_before')).' × '.e((string) $get('persons_before')).'</span>'
                            )),
                        TextInput::make('fee')
                            ->label('Tarifa anual por persona')
                            ->prefix('US$')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(QuoteValueAdjustments::MAX_FEE)
                            ->step('0.01')
                            ->required()
                            ->live(onBlur: true)
                            ->columnSpan(3),
                        TextInput::make('persons')
                            ->label('Personas')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(QuoteValueAdjustments::MAX_PERSONS)
                            ->required()
                            ->live(onBlur: true)
                            ->columnSpan(3),
                    ]),
                Placeholder::make('adjustment_preview')
                    ->label('Qué va a pasar')
                    ->content(function (Get $get) use ($record): HtmlString {
                        $changes = self::changes((array) $get('lines'));
                        if (! self::hasChanges($changes)) {
                            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Cambie una tarifa o una cantidad de personas para ver el efecto.</p>');
                        }

                        try {
                            $preview = QuoteValueAdjustments::preview($record, $changes);
                        } catch (Throwable $exception) {
                            report($exception);

                            return new HtmlString('<p class="text-sm text-rose-600">No se pudo calcular la vista previa. Revise los valores.</p>');
                        }

                        return new HtmlString(view('filament.shared.quotes.value-adjustment-preview', ['preview' => $preview])->render());
                    }),
                Textarea::make('reason')
                    ->label('Motivo del ajuste')
                    ->placeholder('Ej.: El cliente negoció una tarifa menor para la cobertura de 5.000 al firmar el contrato.')
                    ->rows(2)
                    ->required()
                    ->minLength(QuoteValueAdjustments::REASON_MIN_LENGTH)
                    ->maxLength(1000),
            ])
            ->modalSubmitActionLabel('Aplicar ajuste')
            ->action(function (array $data, Model $record, Action $action): void {
                abort_unless(self::canUse(), 403);
                $user = Auth::user();
                abort_unless($user instanceof User, 403);

                try {
                    $adjustment = QuoteValueAdjustments::apply($record, self::changes((array) ($data['lines'] ?? [])), (string) ($data['reason'] ?? ''), $user);
                } catch (RuntimeException $exception) {
                    Notification::make()->title('No se aplicó el ajuste')->body($exception->getMessage())->danger()->persistent()->send();
                    $action->halt();

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title('No se aplicó el ajuste')->body('Error inesperado. No se cambió nada.')->danger()->send();
                    $action->halt();

                    return;
                }

                $affiliation = (array) ($adjustment->effects['affiliation'] ?? []);
                $body = count((array) $adjustment->lines).' línea(s) de la cotización actualizada(s).';
                if ($affiliation['cascade'] ?? false) {
                    $body .= ' Afiliación '.$affiliation['code'].': cuota US$ '.$affiliation['totals']['before']['total_amount'].' → '.$affiliation['totals']['after']['total_amount'].'; '.count((array) $affiliation['collections']).' aviso(s) POR PAGAR ajustado(s).';
                } else {
                    $body .= ' La afiliación no cambió.';
                }

                Notification::make()->title('Ajuste aplicado')->body($body.' Queda en «Historial de ajustes».')->success()->persistent()->send();
            });
    }

    /**
     * @param  array<int|string, mixed>  $lines
     * @return list<array<string, mixed>>
     */
    public static function changes(array $lines): array
    {
        $changes = [];
        foreach ($lines as $line) {
            if (! is_array($line) || ! isset($line['detail_id'])) {
                continue;
            }

            $changes[] = [
                'detail_id' => (int) $line['detail_id'],
                'fee' => $line['fee'] ?? null,
                'persons' => $line['persons'] ?? null,
                'fee_before' => $line['fee_before'] ?? null,
                'persons_before' => $line['persons_before'] ?? null,
            ];
        }

        return $changes;
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     */
    private static function hasChanges(array $changes): bool
    {
        foreach ($changes as $change) {
            if (QuoteValueAdjustments::cents($change['fee']) !== QuoteValueAdjustments::cents($change['fee_before'])
                || trim((string) $change['persons']) !== trim((string) $change['persons_before'])) {
                return true;
            }
        }

        return false;
    }
}
