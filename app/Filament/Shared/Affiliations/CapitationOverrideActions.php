<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Models\AffiliateCapitationAssignmentLog;
use App\Models\AffiliateCapitationOverrideLog;
use App\Models\CapitationPlan;
use App\Models\User;
use App\Support\Business\CapitationPlans\CapitationAssignmentWriter;
use App\Support\Business\CapitationPlans\CapitationCostResult;
use App\Support\Business\CapitationPlans\CapitationOverrideWriter;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
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
 * Capitado por afiliado desde la celda (uno) y como acciones masivas (varios):
 * - monto negociado: permiso {@see BusinessFilamentActionPermissionRegistry::EDIT_CAPITATION_PER_AFFILIATE};
 * - plan asignado directamente: permiso {@see BusinessFilamentActionPermissionRegistry::ASSIGN_CAPITATION_PLAN}.
 */
final class CapitationOverrideActions
{
    public const MODE_SET = 'set';

    public const MODE_REMOVE = 'remove';

    public const MODE_UNDO = 'undo';

    public const MODE_ASSIGN = 'assign';

    public const MODE_UNASSIGN = 'unassign';

    public const MODE_UNDO_ASSIGN = 'undo_assign';

    public static function canEdit(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::EDIT_CAPITATION_PER_AFFILIATE);
    }

    public static function canAssign(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::ASSIGN_CAPITATION_PLAN);
    }

    /**
     * @param  Closure(Model, mixed): CapitationCostResult  $result  mismo cálculo (y caché) que la columna
     */
    public static function cell(Closure $result): Action
    {
        return Action::make('adjustCapitationCost')
            ->label('Capitado del afiliado')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Model $record, mixed $livewire): bool => (self::canEdit() && $result($record, $livewire)->applies()) || self::canAssign())
            ->modalHeading('Capitado del afiliado')
            ->modalDescription('Asigne un plan de capitado directamente al afiliado (reemplaza el cálculo automático) o fije un monto negociado (reemplaza el monto del plan mientras le aplique).')
            ->modalIcon(Heroicon::OutlinedPencilSquare)
            ->modalWidth(Width::Large)
            ->fillForm(fn (Model $record, mixed $livewire): array => [
                'mode' => self::canEdit() && $result($record, $livewire)->applies() ? self::MODE_SET : self::MODE_ASSIGN,
                'amount' => number_format($result($record, $livewire)->amount, 2, '.', ''),
                'capitation_plan_id' => $result($record, $livewire)->assignment['capitation_plan_id'] ?? null,
                'reason' => null,
            ])
            ->schema(function (Model $record, mixed $livewire) use ($result): array {
                $current = $result($record, $livewire);
                $undoable = CapitationOverrideWriter::latestUndoable($record);
                $assignmentUndoable = CapitationAssignmentWriter::latestUndoable($record);
                $canEdit = self::canEdit();
                $canAssign = self::canAssign();

                $modes = array_filter([
                    self::MODE_SET => $canEdit && $current->applies() ? 'Fijar monto negociado' : null,
                    self::MODE_REMOVE => $canEdit && $current->isOverridden() ? 'Volver al monto del plan' : null,
                    self::MODE_UNDO => $canEdit && $undoable !== null ? 'Deshacer el último ajuste' : null,
                    self::MODE_ASSIGN => $canAssign ? ($current->isAssigned() ? 'Cambiar plan asignado' : 'Asignar plan de capitado') : null,
                    self::MODE_UNASSIGN => $canAssign && $current->isAssigned() ? 'Quitar plan asignado' : null,
                    self::MODE_UNDO_ASSIGN => $canAssign && $assignmentUndoable !== null ? 'Deshacer la última asignación' : null,
                ]);

                return [
                    Placeholder::make('capitation_current')
                        ->hiddenLabel()
                        ->content(self::currentState($record, $current)),
                    self::modeField($modes),
                    self::planField(fn (): array => CapitationAssignmentWriter::planOptionsFor($record)),
                    Placeholder::make('capitation_no_plans_hint')
                        ->hiddenLabel()
                        ->visible(fn (Get $get): bool => $get('mode') === self::MODE_ASSIGN && CapitationAssignmentWriter::planOptionsFor($record) === [])
                        ->content(new HtmlString('<div class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30">'
                            .'No hay planes para asignar: la afiliación no tiene entre sus proveedores de servicio un proveedor Outsourcing con plan de capitado activo. '
                            .'Agregue el proveedor en la afiliación y vuelva a intentarlo.</div>')),
                    ...self::amountAndReasonFields(),
                    Placeholder::make('capitation_undo_preview')
                        ->hiddenLabel()
                        ->visible(fn (Get $get): bool => $get('mode') === self::MODE_UNDO)
                        ->content(self::undoPreview($undoable)),
                    Placeholder::make('capitation_assignment_undo_preview')
                        ->hiddenLabel()
                        ->visible(fn (Get $get): bool => $get('mode') === self::MODE_UNDO_ASSIGN)
                        ->content(self::assignmentUndoPreview($assignmentUndoable)),
                ];
            })
            ->modalSubmitActionLabel('Guardar')
            ->action(fn (Model $record, array $data, Action $action): mixed => self::apply([$record], $data, $action));
    }

    /**
     * Asignar, quitar o deshacer el plan de capitado de varios afiliados a la vez.
     */
    public static function bulkAssign(): BulkAction
    {
        return BulkAction::make('assignCapitationPlanBulk')
            ->label('Asignar plan de capitado')
            ->icon(Heroicon::OutlinedLink)
            ->visible(fn (): bool => self::canAssign())
            ->modalHeading('Plan de capitado de los seleccionados')
            ->modalDescription(fn (Collection $records): string => 'Se aplicará a '.($records->count() === 1 ? 'el afiliado seleccionado' : 'los '.$records->count().' afiliados seleccionados').'. El proveedor del plan debe estar en los proveedores de servicio de la afiliación de cada uno; si alguno no cumple, no se asigna a ninguno.')
            ->modalIcon(Heroicon::OutlinedLink)
            ->modalWidth(Width::Large)
            ->fillForm(['mode' => self::MODE_ASSIGN, 'capitation_plan_id' => null, 'reason' => null])
            ->schema([
                self::modeField([
                    self::MODE_ASSIGN => 'Asignar plan de capitado',
                    self::MODE_UNASSIGN => 'Quitar plan asignado',
                    self::MODE_UNDO_ASSIGN => 'Deshacer la última asignación',
                ]),
                self::planField(fn (): array => CapitationAssignmentWriter::planOptions()),
                ...self::amountAndReasonFields(),
            ])
            ->modalSubmitActionLabel('Guardar')
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records, array $data, BulkAction $action): mixed => self::apply($records->all(), $data, $action));
    }

    /**
     * @param  array<string, string>  $modes
     */
    private static function modeField(array $modes): ToggleButtons
    {
        return ToggleButtons::make('mode')
            ->label('¿Qué desea hacer?')
            ->options($modes)
            ->icons([
                self::MODE_SET => Heroicon::OutlinedPencilSquare,
                self::MODE_REMOVE => Heroicon::OutlinedArrowPath,
                self::MODE_UNDO => Heroicon::OutlinedArrowUturnLeft,
                self::MODE_ASSIGN => Heroicon::OutlinedLink,
                self::MODE_UNASSIGN => Heroicon::OutlinedLinkSlash,
                self::MODE_UNDO_ASSIGN => Heroicon::OutlinedArrowUturnLeft,
            ])
            ->colors([
                self::MODE_SET => 'primary', self::MODE_REMOVE => 'gray', self::MODE_UNDO => 'warning',
                self::MODE_ASSIGN => 'info', self::MODE_UNASSIGN => 'gray', self::MODE_UNDO_ASSIGN => 'warning',
            ])
            ->inline()
            ->required()
            ->live()
            ->hidden(count($modes) <= 1);
    }

    /**
     * @param  Closure(): array<int, string>  $options
     */
    private static function planField(Closure $options): Select
    {
        return Select::make('capitation_plan_id')
            ->label('Plan de capitado')
            ->options($options)
            ->native(false)
            ->searchable()
            ->helperText('Planes activos y vigentes de los proveedores Outsourcing que están en la afiliación. No hace falta que cubran el plan del afiliado.')
            ->noSearchResultsMessage('Ningún plan coincide.')
            ->visible(fn (Get $get): bool => $get('mode') === self::MODE_ASSIGN)
            ->required(fn (Get $get): bool => $get('mode') === self::MODE_ASSIGN)
            ->validationMessages(['required' => 'Elija el plan de capitado.']);
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
     * Monto (solo al fijar) y razón (siempre), para la celda y las acciones masivas.
     *
     * @return array<int, mixed>
     */
    private static function amountAndReasonFields(): array
    {
        return array_slice(self::fields(true, true), 1);
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
                ->visible(fn (Get $get): bool => in_array($get('mode') ?? self::MODE_SET, [self::MODE_SET], true))
                ->required(fn (Get $get): bool => in_array($get('mode') ?? self::MODE_SET, [self::MODE_SET], true)),
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
        $mode = $data['mode'] ?? self::MODE_SET;
        $isAssignment = in_array($mode, [self::MODE_ASSIGN, self::MODE_UNASSIGN, self::MODE_UNDO_ASSIGN], true);

        if ($isAssignment ? ! self::canAssign() : ! self::canEdit()) {
            Notification::make()->title('Sin permiso')->body($isAssignment
                ? 'No tiene permiso para asignar planes de capitado a afiliados.'
                : 'No tiene permiso para ajustar el capitado por afiliado.')->danger()->send();
            $action->halt();
        }

        $user = Auth::user();
        $user = $user instanceof User ? $user : null;

        try {
            if ($mode === self::MODE_ASSIGN) {
                $count = CapitationAssignmentWriter::assign($records, $data['capitation_plan_id'] ?? null, (string) ($data['reason'] ?? ''), $user);
                $title = $count === 1 ? 'Plan de capitado asignado' : 'Plan de capitado asignado a '.$count.' afiliados';
            } elseif ($mode === self::MODE_UNASSIGN) {
                $count = CapitationAssignmentWriter::remove($records, (string) ($data['reason'] ?? ''), $user);
                $title = $count === 1 ? 'Plan asignado retirado: vuelve al cálculo automático' : $count.' afiliados vuelven al cálculo automático';
            } elseif ($mode === self::MODE_UNDO_ASSIGN) {
                $count = CapitationAssignmentWriter::revert($records, (string) ($data['reason'] ?? ''), $user);
                $title = $count === 1 ? 'Asignación deshecha' : 'Asignaciones deshechas en '.$count.' afiliados';
            } elseif ($mode === self::MODE_UNDO) {
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
                ->title('No se guardó el cambio')
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

    private static function assignmentUndoPreview(?AffiliateCapitationAssignmentLog $log): HtmlString
    {
        if ($log === null) {
            return new HtmlString('');
        }

        $names = CapitationPlan::withTrashed()->whereIn('id', array_filter([$log->new_capitation_plan_id, $log->previous_capitation_plan_id]))->pluck('name', 'id');
        $change = $log->action === AffiliateCapitationAssignmentLog::ACTION_ASSIGN
            ? 'la asignación del plan «'.($names[$log->new_capitation_plan_id] ?? '—').'»'
            : 'el retiro del plan asignado';
        $after = $log->previous_capitation_plan_id !== null
            ? 'Quedará otra vez con el plan «'.($names[$log->previous_capitation_plan_id] ?? '—').'».'
            : 'Quedará sin plan asignado (cálculo automático).';

        return new HtmlString('<div class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30">'
            .'Se deshará '.e($change).', registrado por '.e((string) $log->created_by_name).' el '.e((string) $log->created_at?->format('d/m/Y h:i A')).'. '.e($after).'</div>');
    }

    private static function currentState(Model $record, CapitationCostResult $result): HtmlString
    {
        $plan = $result->lines[0] ?? null;
        $rows = '';

        if ($result->assignment !== null) {
            $rows .= '<p class="mb-1 text-sm text-sky-700 dark:text-sky-300">Plan asignado directamente: <strong>'.e($result->assignment['plan'].' · '.$result->assignment['provider']).'</strong>'
                .' · '.e($result->assignment['by']).' · '.e($result->assignment['at']).'</p>';

            if (! $result->applies()) {
                $rows .= '<p class="mb-1 text-xs font-medium text-rose-700 dark:text-rose-300">'.e((string) $result->reason).'.</p>';
            }
        }

        $rows .= '<p class="text-sm text-gray-700 dark:text-gray-300">Monto del plan: <strong>'.e(CapitationCostResult::money($result->planAmount)).'</strong>'
            .($plan !== null ? ' · '.e($plan['plan'].' · '.$plan['provider'].' · '.mb_strtolower($plan['frequency'])) : ' · '.e((string) $result->reason)).'</p>';

        if ($result->override !== null) {
            $rows .= '<p class="mt-1 text-sm text-amber-700 dark:text-amber-300">Monto negociado actual: <strong>'.e(CapitationCostResult::money($result->override['amount'])).'</strong>'
                .' · '.e($result->override['by']).' · '.e($result->override['at']).'</p>';

            if ($result->planChangedSinceOverride()) {
                $rows .= '<p class="mt-1 text-xs text-amber-700 dark:text-amber-300">El plan costaba '.e(CapitationCostResult::money((float) $result->override['plan_amount'])).' cuando se negoció.</p>';
            }
        }

        $assignmentHistory = CapitationAssignmentWriter::history($record, 5);

        if ($assignmentHistory->isNotEmpty()) {
            $names = CapitationPlan::withTrashed()->whereIn('id', $assignmentHistory->pluck('new_capitation_plan_id')->filter()->unique()->all())->pluck('name', 'id');
            $items = $assignmentHistory->map(fn (AffiliateCapitationAssignmentLog $log): string => '<li>'
                .e($log->created_at?->format('d/m/Y h:i A').' · '.$log->created_by_name.' · ')
                .match ($log->action) {
                    AffiliateCapitationAssignmentLog::ACTION_ASSIGN => 'asignó «'.e((string) ($names[$log->new_capitation_plan_id] ?? '—')).'»',
                    AffiliateCapitationAssignmentLog::ACTION_REVERT => 'deshizo una asignación',
                    default => 'quitó el plan asignado',
                }
                .' — '.e($log->reason)
                .($log->reverted_at !== null ? ' <span class="text-amber-700 dark:text-amber-300">(deshecho por '.e((string) $log->reverted_by_name).')</span>' : '')
                .'</li>')->implode('');

            $rows .= '<div class="mt-3"><p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Asignaciones recientes</p>'
                .'<ul class="mt-1 space-y-1 text-xs text-gray-600 dark:text-gray-400">'.$items.'</ul></div>';
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
