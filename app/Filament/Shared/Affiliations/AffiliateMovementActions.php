<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Models\AffiliateMovement;
use App\Models\User;
use App\Support\Affiliations\Movements\AffiliateMovements;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;

/**
 * Inclusión y exclusión de afiliados desde las tablas de afiliados (Negocios, Administración y
 * red comercial), siempre por {@see AffiliateMovements}: afiliación, cobranza y operaciones
 * cambian juntas, con vista previa y quedan en el historial con su reverso.
 */
final class AffiliateMovementActions
{
    public const FIELD_DATE = 'movement_effective_date';

    public const FIELD_REASON = 'movement_reason';

    /**
     * «Dar de baja» con fecha efectiva, motivo y vista previa de lo que cambia en la cobranza.
     *
     * @param  Closure(): Model  $owner  afiliación dueña (la del relation manager)
     */
    public static function exclude(Closure $owner, string $source, ?Closure $visible = null): Action
    {
        return Action::make('changet_status')
            ->label('Dar de baja')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->visible($visible ?? fn (): bool => true)
            ->hidden(fn (Model $record): bool => ! in_array(strtoupper(trim((string) $record->status)), ['ACTIVO', 'PRE-APROBADA'], true))
            ->modalHeading(fn (Model $record): string => 'Dar de baja a '.self::name($record))
            ->modalDescription('Queda INACTIVO (no se borra). La afiliación, sus avisos de cobro y su paciente de telemedicina se ajustan solos; el movimiento queda en el historial y se puede revertir.')
            ->modalIcon(Heroicon::UserMinus)
            ->modalIconColor('danger')
            ->modalWidth(Width::ThreeExtraLarge)
            ->schema(fn (Model $record): array => [
                ...self::fields($owner, fn (): Model => $record, AffiliateMovement::KIND_REMOVE),
            ])
            ->fillForm(fn (): array => [self::FIELD_DATE => CarbonImmutable::today()->toDateString()])
            ->modalSubmitActionLabel('Dar de baja')
            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
            ->action(function (array $data, Model $record, Action $action) use ($owner, $source): void {
                $user = Auth::user();
                abort_unless($user instanceof User, 403);

                try {
                    $movement = AffiliateMovements::exclude($owner(), $record, $data[self::FIELD_DATE] ?? null, (string) ($data[self::FIELD_REASON] ?? ''), $user, $source);
                } catch (RuntimeException $exception) {
                    Notification::make()->title('No se dio de baja')->body($exception->getMessage())->danger()->persistent()->send();
                    $action->halt();

                    return;
                } catch (Throwable $exception) {
                    if ($exception instanceof \App\Support\Affiliations\PersonIdentity\DuplicatePersonIdentityException) {
                        throw $exception;
                    }

                    report($exception);
                    Notification::make()->title('No se dio de baja')->body('Error inesperado. No se cambió nada.')->danger()->send();
                    $action->halt();

                    return;
                }

                self::notifyDone($movement);
            });
    }

    /**
     * Campos de fecha efectiva, motivo y vista previa para el formulario de alta. Solo al crear.
     *
     * @param  Closure(): Model  $owner
     * @param  Closure(Get): Model  $draft  afiliado aún no guardado con lo que lleva escrito el formulario
     * @return list<\Filament\Schemas\Components\Component>
     */
    public static function includeFields(Closure $owner, Closure $draft): array
    {
        return [
            Section::make('Inclusión en la afiliación')
                ->description('Desde cuándo cuenta la persona. La afiliación, sus avisos de cobro (prorrateo por días) y operaciones se ajustan solos, y el movimiento queda en el historial con su reverso.')
                ->icon(Heroicon::UserPlus)
                ->visible(fn (string $operation): bool => $operation === 'create')
                ->schema(self::fields($owner, $draft, AffiliateMovement::KIND_ADD))
                ->columnSpanFull(),
        ];
    }

    /**
     * Registra el alta de un afiliado recién creado (en la misma transacción que lo creó).
     *
     * @param  array<string, mixed>  $data
     */
    public static function registerInclusion(Model $owner, Model $affiliate, array $data, string $source): AffiliateMovement
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new RuntimeException('Sesión no válida.');
        }

        return AffiliateMovements::include($owner, $affiliate, $data[self::FIELD_DATE] ?? null, (string) ($data[self::FIELD_REASON] ?? ''), $user, $source);
    }

    /**
     * Quita del formulario los campos del movimiento (no son columnas del afiliado).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function strip(array $data): array
    {
        unset($data[self::FIELD_DATE], $data[self::FIELD_REASON], $data['movement_preview']);

        return $data;
    }

    public static function notifyDone(AffiliateMovement $movement): void
    {
        $lines = [];
        foreach ((array) ($movement->effects['collections'] ?? []) as $change) {
            $lines[] = 'Aviso '.$change['invoice'].': '.AffiliateMovements::decimal((int) $change['before_cents']).' → '.AffiliateMovements::decimal((int) $change['after_cents']);
        }

        if ((float) $movement->pending_credit > 0) {
            $lines[] = 'Crédito pendiente US$ '.$movement->pending_credit.' (se descuenta en el próximo aviso que se emita)';
        }

        if (isset($movement->effects['charge'])) {
            $lines[] = 'Se emitió el '.$movement->effects['charge'];
        }

        Notification::make()
            ->title(($movement->isAddition() ? 'Inclusión' : 'Exclusión').' registrada')
            ->body($movement->affiliate_name.' desde el '.$movement->effective_date?->format('d/m/Y').'. Totales de la afiliación recalculados.'.($lines !== [] ? ' '.implode(' · ', $lines).'.' : ' Sin avisos de cobro que ajustar.'))
            ->success()
            ->persistent()
            ->send();
    }

    /**
     * @param  Closure(): Model  $owner
     * @param  Closure(Get): Model  $affiliate
     * @return list<\Filament\Schemas\Components\Component>
     */
    private static function fields(Closure $owner, Closure $affiliate, string $kind): array
    {
        $today = CarbonImmutable::today();

        return [
            DatePicker::make(self::FIELD_DATE)
                ->label($kind === AffiliateMovement::KIND_ADD ? 'Fecha efectiva de la inclusión' : 'Fecha efectiva de la baja')
                ->helperText('Hasta '.AffiliateMovements::EFFECTIVE_WINDOW_DAYS.' días antes o después de hoy.')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->format('Y-m-d')
                ->default($today->toDateString())
                ->minDate($today->subDays(AffiliateMovements::EFFECTIVE_WINDOW_DAYS))
                ->maxDate($today->addDays(AffiliateMovements::EFFECTIVE_WINDOW_DAYS))
                ->required()
                ->live(),
            Placeholder::make('movement_preview')
                ->label('Qué va a pasar')
                ->content(function (Get $get) use ($owner, $affiliate, $kind): HtmlString {
                    try {
                        $preview = AffiliateMovements::preview($owner(), $affiliate($get), $kind, $get(self::FIELD_DATE));
                    } catch (Throwable $exception) {
                        report($exception);

                        return new HtmlString('<p class="text-sm text-gray-500">Complete los datos para ver el cálculo.</p>');
                    }

                    return new HtmlString(view('filament.shared.affiliations.movement-preview', ['preview' => $preview, 'kind' => $kind])->render());
                }),
            Textarea::make(self::FIELD_REASON)
                ->label('Motivo')
                ->placeholder($kind === AffiliateMovement::KIND_ADD ? 'Ej.: Nuevo colaborador contratado por la empresa el 10/11.' : 'Ej.: La empresa notificó el retiro del colaborador el 10/11.')
                ->rows(2)
                ->required()
                ->minLength(AffiliateMovements::REASON_MIN_LENGTH)
                ->maxLength(1000),
        ];
    }

    private static function name(Model $affiliate): string
    {
        return $affiliate instanceof \App\Models\AffiliateCorporate
            ? trim($affiliate->first_name.' '.$affiliate->last_name)
            : (string) $affiliate->full_name;
    }
}
