<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Models\AffiliateCapitationOverrideLog;
use App\Models\User;
use App\Support\Business\CapitationPlans\CapitationCostResult;
use App\Support\Business\CapitationPlans\CapitationOverrideWriter;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Ajuste del capitado por afiliado: desde la celda (uno) y como acción masiva (varios).
 * Exige el permiso {@see BusinessFilamentActionPermissionRegistry::EDIT_CAPITATION_PER_AFFILIATE}.
 */
final class CapitationOverrideActions
{
    public const MODE_SET = 'set';

    public const MODE_REMOVE = 'remove';

    public const MODE_UNDO = 'undo';

    public static function canEdit(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::EDIT_CAPITATION_PER_AFFILIATE);
    }

    /**
     * @param  Closure(Model, mixed): CapitationCostResult  $result  mismo cálculo (y caché) que la columna
     */
    public static function cell(Closure $result): Action
    {
        return Action::make('adjustCapitationCost')
            ->label('Ajustar capitado')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Model $record, mixed $livewire): bool => self::canEdit() && $result($record, $livewire)->applies())
            ->modalHeading('Ajustar capitado del afiliado')
            ->modalDescription('El monto negociado reemplaza al del plan de capitado solo mientras el afiliado esté activo y el plan le aplique. Se conserva aunque el plan cambie de precio.')
            ->modalIcon(Heroicon::OutlinedPencilSquare)
            ->modalWidth(Width::Large)
            ->fillForm(fn (Model $record, mixed $livewire): array => [
                'mode' => self::MODE_SET,
                'amount' => number_format($result($record, $livewire)->amount, 2, '.', ''),
                'reason' => null,
            ])
            ->schema(function (Model $record, mixed $livewire) use ($result): array {
                $current = $result($record, $livewire);
                $undoable = CapitationOverrideWriter::latestUndoable($record);

                return [
                    Placeholder::make('capitation_current')
                        ->hiddenLabel()
                        ->content(self::currentState($record, $current)),
                    ...self::fields($current->isOverridden(), $undoable !== null),
                    Placeholder::make('capitation_undo_preview')
                        ->hiddenLabel()
                        ->visible(fn (Get $get): bool => $get('mode') === self::MODE_UNDO)
                        ->content(self::undoPreview($undoable)),
                ];
            })
            ->modalSubmitActionLabel('Guardar ajuste')
            ->action(fn (Model $record, array $data, Action $action): mixed => self::apply([$record], $data, $action));
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('adjustCapitationCostBulk')
            ->label('Ajustar capitado')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (): bool => self::canEdit())
            ->modalHeading('Ajustar capitado de los seleccionados')
            ->modalDescription(fn (Collection $records): string => 'Se aplicará el mismo monto a '.($records->count() === 1 ? 'el afiliado seleccionado' : 'los '.$records->count().' afiliados seleccionados').'. Si alguno no está activo o no le aplica un plan de capitado, no se ajusta ninguno.')
            ->modalIcon(Heroicon::OutlinedPencilSquare)
            ->modalWidth(Width::Large)
            ->fillForm(['mode' => self::MODE_SET, 'amount' => null, 'reason' => null])
            ->schema([
                ...self::fields(true, true),
                Placeholder::make('capitation_undo_preview')
                    ->hiddenLabel()
                    ->visible(fn (Get $get): bool => $get('mode') === self::MODE_UNDO)
                    ->content(new HtmlString('<p class="text-sm text-gray-600 dark:text-gray-400">Cada afiliado seleccionado vuelve al estado anterior a su último cambio (monto negociado, razón y monto del plan de ese momento, o sin ajuste). Si alguno no tiene cambios por deshacer, o su monto cambió después, no se deshace ninguno.</p>')),
            ])
            ->modalSubmitActionLabel('Guardar ajuste')
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records, array $data, BulkAction $action): mixed => self::apply($records->all(), $data, $action));
    }

    /**
     * @return array<int, mixed>
     */
    private static function fields(bool $canRemove, bool $canUndo): array
    {
        return [
            ToggleButtons::make('mode')
                ->label('¿Qué desea hacer?')
                ->options(array_filter([
                    self::MODE_SET => 'Fijar monto negociado',
                    self::MODE_REMOVE => $canRemove ? 'Volver al monto del plan' : null,
                    self::MODE_UNDO => $canUndo ? 'Deshacer el último cambio' : null,
                ]))
                ->icons([
                    self::MODE_SET => Heroicon::OutlinedPencilSquare,
                    self::MODE_REMOVE => Heroicon::OutlinedArrowPath,
                    self::MODE_UNDO => Heroicon::OutlinedArrowUturnLeft,
                ])
                ->colors([self::MODE_SET => 'primary', self::MODE_REMOVE => 'gray', self::MODE_UNDO => 'warning'])
                ->default(self::MODE_SET)
                ->inline()
                ->required()
                ->live()
                ->hidden(! $canRemove && ! $canUndo),
            TextInput::make('amount')
                ->label('Monto negociado por afiliado')
                ->prefix('US$')
                ->numeric()
                ->minValue(0)
                ->maxValue(CapitationOverrideWriter::MAX_AMOUNT)
                ->step(0.01)
                ->placeholder('0.00')
                ->helperText('Mismo período de cobro que el plan de capitado. Use 0,00 si quedó exonerado.')
                ->visible(fn (Get $get): bool => ! in_array($get('mode'), [self::MODE_REMOVE, self::MODE_UNDO], true))
                ->required(fn (Get $get): bool => ! in_array($get('mode'), [self::MODE_REMOVE, self::MODE_UNDO], true)),
            Textarea::make('reason')
                ->label('Razón')
                ->placeholder('Ej.: negociación directa con la empresa por volumen, acta del 30/10/2026.')
                ->helperText('Obligatoria. Queda en el historial junto con su nombre, la fecha y el monto del plan de ese momento.')
                ->required()
                ->minLength(CapitationOverrideWriter::MIN_REASON)
                ->maxLength(CapitationOverrideWriter::MAX_REASON)
                ->rows(3),
        ];
    }

    /**
     * @param  array<int, Model>  $records
     * @param  array<string, mixed>  $data
     */
    private static function apply(array $records, array $data, Action $action): null
    {
        if (! self::canEdit()) {
            Notification::make()->title('Sin permiso')->body('No tiene permiso para ajustar el capitado por afiliado.')->danger()->send();
            $action->halt();
        }

        $user = Auth::user();
        $user = $user instanceof User ? $user : null;

        try {
            $mode = $data['mode'] ?? self::MODE_SET;

            if ($mode === self::MODE_UNDO) {
                $count = CapitationOverrideWriter::revert($records, (string) ($data['reason'] ?? ''), $user);
                $title = $count === 1 ? 'Cambio deshecho' : 'Cambios deshechos en '.$count.' afiliados';
            } elseif ($mode === self::MODE_REMOVE) {
                $count = CapitationOverrideWriter::remove($records, (string) ($data['reason'] ?? ''), $user);
                $title = $count === 1 ? 'Volvió al monto del plan' : $count.' afiliados volvieron al monto del plan';
            } else {
                $count = CapitationOverrideWriter::set($records, $data['amount'] ?? null, (string) ($data['reason'] ?? ''), $user);
                $title = $count === 1 ? 'Capitado ajustado' : $count.' afiliados ajustados';
            }
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('No se guardó el ajuste')
                ->body((string) collect($exception->errors())->flatten()->first())
                ->danger()
                ->persistent()
                ->send();
            $action->halt();
        }

        Notification::make()->title($title)->body('La razón quedó registrada en el historial.')->success()->send();

        return null;
    }

    private static function undoPreview(?AffiliateCapitationOverrideLog $log): HtmlString
    {
        if ($log === null) {
            return new HtmlString('');
        }

        $change = $log->action === AffiliateCapitationOverrideLog::ACTION_SET
            ? 'el monto negociado de '.CapitationCostResult::money((float) $log->new_amount_usd)
            : 'el regreso al monto del plan';

        $after = $log->previous_amount_usd !== null
            ? 'Quedará otra vez con el monto negociado de '.CapitationCostResult::money((float) $log->previous_amount_usd).($log->previous_reason ? ' («'.$log->previous_reason.'»)' : '').'.'
            : 'Quedará otra vez con el monto del plan, sin ajuste.';

        return new HtmlString('<div class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30">'
            .'Se deshará '.e($change).', registrado por '.e((string) $log->created_by_name).' el '.e((string) $log->created_at?->format('d/m/Y h:i A')).'. '
            .e($after).'</div>');
    }

    private static function currentState(Model $record, CapitationCostResult $result): HtmlString
    {
        $plan = $result->lines[0] ?? null;
        $rows = '<p class="text-sm text-gray-700 dark:text-gray-300">Monto del plan: <strong>'.e(CapitationCostResult::money($result->planAmount)).'</strong>'
            .($plan !== null ? ' · '.e($plan['plan'].' · '.$plan['provider'].' · '.mb_strtolower($plan['frequency'])) : '').'</p>';

        if ($result->override !== null) {
            $rows .= '<p class="mt-1 text-sm text-amber-700 dark:text-amber-300">Monto negociado actual: <strong>'.e(CapitationCostResult::money($result->override['amount'])).'</strong>'
                .' · '.e($result->override['by']).' · '.e($result->override['at']).'</p>';

            if ($result->planChangedSinceOverride()) {
                $rows .= '<p class="mt-1 text-xs text-amber-700 dark:text-amber-300">El plan costaba '.e(CapitationCostResult::money((float) $result->override['plan_amount'])).' cuando se negoció.</p>';
            }
        }

        $history = CapitationOverrideWriter::history($record, 5);

        if ($history->isNotEmpty()) {
            $items = $history->map(fn (AffiliateCapitationOverrideLog $log): string => '<li>'
                .e($log->created_at?->format('d/m/Y h:i A').' · '.$log->created_by_name.' · ')
                .match ($log->action) {
                    AffiliateCapitationOverrideLog::ACTION_SET => 'fijó '.e(CapitationCostResult::money((float) $log->new_amount_usd)),
                    AffiliateCapitationOverrideLog::ACTION_REVERT => 'deshizo un cambio ('.($log->new_amount_usd !== null ? 'quedó en '.e(CapitationCostResult::money((float) $log->new_amount_usd)) : 'quedó con el monto del plan').')',
                    default => 'volvió al plan',
                }
                .' — '.e($log->reason)
                .($log->reverted_at !== null ? ' <span class="text-amber-700 dark:text-amber-300">(deshecho por '.e((string) $log->reverted_by_name).')</span>' : '')
                .'</li>')->implode('');

            $rows .= '<div class="mt-3"><p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Historial reciente</p>'
                .'<ul class="mt-1 space-y-1 text-xs text-gray-600 dark:text-gray-400">'.$items.'</ul></div>';
        }

        return new HtmlString('<div class="rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">'.$rows.'</div>');
    }
}
