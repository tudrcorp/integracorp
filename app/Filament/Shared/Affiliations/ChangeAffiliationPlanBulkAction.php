<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Exceptions\IndividualPlanChangeBlockedException;
use App\Models\Affiliation;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use App\Support\Affiliations\PlanChange\IndividualAffiliationPlanChanger;
use App\Support\Affiliations\PlanChange\IndividualPlanChangeCatalog;
use App\Support\Affiliations\PlanChange\IndividualPlanChangeRequest;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * Acción masiva «Cambiar plan» de afiliaciones individuales (Negocios).
 *
 * Paso 1: plan, cobertura y frecuencia. Paso 2: vista previa real (el servicio ejecuta el cambio y
 * lo revierte), con el rango de edad de cada afiliado editable, el motivo y la confirmación.
 * La vista previa se calcula una vez al avanzar y solo se recalcula la afiliación cuyo rango cambia.
 *
 * Si alguna afiliación queda bloqueada por la regla de edad del Plan Ideal, aparece la casilla
 * «Autorizar excepción» con una nota obligatoria; el servicio deja columnas, bitácora y auditoría.
 */
final class ChangeAffiliationPlanBulkAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('change_plan')
            ->label('Cambiar plan')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('warning')
            ->visible(fn (): bool => self::userCan())
            ->authorize(fn (): bool => self::userCan())
            ->modalHeading('Cambiar plan de las afiliaciones seleccionadas')
            ->modalDescription('Cambia plan, cobertura, frecuencia y rango de edad de cada afiliado, y recalcula las cuotas POR PAGAR. Lo pagado no se toca.')
            ->modalIcon(Heroicon::OutlinedArrowsRightLeft)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Aplicar cambio de plan')
            ->deselectRecordsAfterCompletion()
            ->fillForm(fn (EloquentCollection $records): array => [
                'affiliation_ids' => $records->modelKeys(),
                'frequency' => null,
                'affiliates' => [],
                'previews' => [],
                'confirmed' => false,
                'allow_ideal_exception' => false,
                'exception_note' => null,
            ])
            ->steps([
                Step::make('Plan nuevo')
                    ->description('Plan, cobertura y frecuencia')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->schema([
                        Hidden::make('affiliation_ids'),
                        Placeholder::make('selection')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => self::selectionSummary((array) $get('affiliation_ids')))
                            ->columnSpanFull(),
                        Select::make('plan_id')
                            ->label('Plan')
                            ->options(fn (): array => IndividualPlanChangeCatalog::planOptions())
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('coverage_id', null))
                            ->helperText('Solo planes activos con tarifas y disponibles para afiliaciones individuales.'),
                        Select::make('coverage_id')
                            ->label('Cobertura')
                            ->options(fn (Get $get): array => filled($get('plan_id')) ? IndividualPlanChangeCatalog::coverageOptions((int) $get('plan_id')) : [])
                            ->visible(fn (Get $get): bool => filled($get('plan_id')) && IndividualPlanChangeCatalog::usesCoverages((int) $get('plan_id')))
                            ->required(fn (Get $get): bool => filled($get('plan_id')) && IndividualPlanChangeCatalog::usesCoverages((int) $get('plan_id')))
                            ->native(false)
                            ->helperText('El plan se cobra por cobertura: elija el monto cubierto.'),
                        ToggleButtons::make('frequency')
                            ->label('Frecuencia de pago')
                            ->options(CorporatePaymentFrequency::options())
                            ->inline()
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->afterValidation(function (Get $get, Set $set): void {
                        $ids = self::affiliationIds($get);

                        if ($ids === []) {
                            Notification::make()->title('Seleccione al menos una afiliación')->warning()->send();

                            throw new Halt;
                        }

                        if (count($ids) > IndividualAffiliationPlanChanger::MAX_BATCH) {
                            Notification::make()
                                ->title('Demasiadas afiliaciones a la vez')
                                ->body('Seleccione hasta '.IndividualAffiliationPlanChanger::MAX_BATCH.' afiliaciones: así la vista previa se puede revisar con calma.')
                                ->warning()
                                ->send();

                            throw new Halt;
                        }

                        $previews = self::previews($ids, self::requestFromState($get));
                        $set('previews', $previews);
                        $set('affiliates', self::affiliateRows($previews));
                    }),
                Step::make('Vista previa')
                    ->description('Revisar, ajustar rangos y confirmar')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->schema([
                        Hidden::make('previews'),
                        Placeholder::make('preview')
                            ->label('Así quedará cada afiliación (todavía no se guardó nada)')
                            ->content(fn (Get $get): HtmlString => new HtmlString(view('filament.business.affiliations.plan-change-preview', [
                                'previews' => (array) $get('previews'),
                            ])->render()))
                            ->columnSpanFull(),
                        Section::make('Rango de edad de cada afiliado')
                            ->description('El sistema propone el rango según la edad. Cámbielo solo si corresponde otro: la tarifa sale de ese rango.')
                            ->icon(Heroicon::OutlinedUsers)
                            ->collapsible()
                            ->visible(fn (Get $get): bool => (array) $get('affiliates') !== [])
                            ->schema([
                                Repeater::make('affiliates')
                                    ->hiddenLabel()
                                    ->schema([
                                        Hidden::make('affiliation_id'),
                                        Hidden::make('affiliate_id'),
                                        Hidden::make('suggested_age_range_id'),
                                        TextInput::make('label')->label('Afiliado')->disabled()->dehydrated(false)->columnSpan(2),
                                        Select::make('age_range_id')
                                            ->label('Rango de edad')
                                            ->options(fn (Get $get): array => filled($get('../../plan_id'))
                                                ? IndividualPlanChangeCatalog::ageRangeOptions((int) $get('../../plan_id'), filled($get('../../coverage_id')) ? (int) $get('../../coverage_id') : null)
                                                : [])
                                            ->native(false)
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                                self::refreshPreview($get, $set, (int) $get('affiliation_id'));
                                            }),
                                    ])
                                    ->columns(3)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                        Section::make('Excepción a la regla de edad del Plan Ideal')
                            ->description('Use esta opción solo si la excepción está autorizada. Los afiliados fuera de rango toman la tarifa del rango más cercano (o el que elija arriba) y la nota queda en la bitácora y en la auditoría de seguridad.')
                            ->icon(Heroicon::OutlinedExclamationTriangle)
                            ->iconColor('warning')
                            ->visible(fn (Get $get): bool => (bool) $get('allow_ideal_exception') || self::hasIdealAgeIssue((array) $get('previews')))
                            ->schema([
                                Checkbox::make('allow_ideal_exception')
                                    ->label('Autorizar excepción: aplicar el cambio aunque la edad esté fuera de los rangos del Plan Ideal')
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshAllPreviews($get, $set)),
                                Textarea::make('exception_note')
                                    ->label('¿Por qué se hace la excepción?')
                                    ->visible(fn (Get $get): bool => (bool) $get('allow_ideal_exception'))
                                    ->required(fn (Get $get): bool => (bool) $get('allow_ideal_exception'))
                                    ->minLength(IndividualAffiliationPlanChanger::EXCEPTION_NOTE_MIN_LENGTH)
                                    ->maxLength(1000)
                                    ->rows(2)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshAllPreviews($get, $set))
                                    ->placeholder('Ejemplo: aprobado por la Gerencia de Negocios el 15/10; el titular renueva en el Plan Ideal por acuerdo comercial.')
                                    ->helperText('Obligatoria, mínimo '.IndividualAffiliationPlanChanger::EXCEPTION_NOTE_MIN_LENGTH.' caracteres. Quién lo autorizó y por qué.'),
                            ])
                            ->columnSpanFull(),
                        Textarea::make('reason')
                            ->label('Motivo del cambio')
                            ->required()
                            ->minLength(8)
                            ->maxLength(500)
                            ->rows(2)
                            ->placeholder('Ejemplo: el cliente solicitó pasar al Plan Especial con cobertura de US$ 30.000 y pago mensual.')
                            ->columnSpanFull(),
                        Checkbox::make('confirmed')
                            ->label('Revisé la vista previa. Las afiliaciones bloqueadas no se cambiarán; en las demás se recalcularán las cuotas POR PAGAR.')
                            ->accepted()
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (array $data): void {
                $ids = array_values(array_unique(array_map('intval', (array) ($data['affiliation_ids'] ?? []))));

                if ($ids === [] || count($ids) > IndividualAffiliationPlanChanger::MAX_BATCH) {
                    Notification::make()->title('Selección inválida')->body('Seleccione entre 1 y '.IndividualAffiliationPlanChanger::MAX_BATCH.' afiliaciones.')->danger()->send();

                    return;
                }

                $overrides = self::overridesFrom((array) ($data['affiliates'] ?? []));
                $user = Auth::user();
                $summary = app(IndividualAffiliationPlanChanger::class)->changeMany(
                    Affiliation::query()->whereKey($ids)->orderBy('id')->get(),
                    fn (Affiliation $affiliation): IndividualPlanChangeRequest => new IndividualPlanChangeRequest(
                        (int) $data['plan_id'],
                        filled($data['coverage_id'] ?? null) ? (int) $data['coverage_id'] : null,
                        (string) $data['frequency'],
                        $overrides[(int) $affiliation->id] ?? [],
                        (string) ($data['reason'] ?? ''),
                        (bool) ($data['allow_ideal_exception'] ?? false),
                        trim((string) ($data['exception_note'] ?? '')),
                    ),
                    $user instanceof User ? $user : null,
                );

                self::notifySummary($summary);
            });
    }

    public static function userCan(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::CHANGE_INDIVIDUAL_AFFILIATION_PLAN);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int|string, array<string, mixed>>
     */
    public static function previews(array $ids, callable $requestFor): array
    {
        $changer = app(IndividualAffiliationPlanChanger::class);
        $previews = [];

        foreach (Affiliation::query()->whereKey($ids)->orderBy('id')->get() as $affiliation) {
            $previews[(string) $affiliation->id] = $changer->preview($affiliation, $requestFor($affiliation));
        }

        return $previews;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $previews
     * @return list<array{affiliation_id: int, affiliate_id: int, label: string, age_range_id: int|null, suggested_age_range_id: int|null}>
     */
    public static function affiliateRows(array $previews): array
    {
        $rows = [];

        foreach ($previews as $preview) {
            if (($preview['blocked'] ?? null) !== null) {
                continue;
            }

            foreach ((array) ($preview['affiliates'] ?? []) as $line) {
                $rows[] = [
                    'affiliation_id' => (int) $preview['affiliation_id'],
                    'affiliate_id' => (int) $line['id'],
                    'label' => $preview['code'].' · '.$line['name'].($line['age'] !== null ? ' ('.$line['age'].' años)' : ''),
                    'age_range_id' => $line['new_age_range_id'],
                    'suggested_age_range_id' => $line['new_age_range_id'],
                ];
            }
        }

        return $rows;
    }

    /**
     * Solo los rangos que el analista cambió: el resto lo resuelve el servicio (por edad, o
     * conservando la tarifa actual si solo cambia la frecuencia).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, array<int, int>> id de afiliación => [id de afiliado => id de rango]
     */
    public static function overridesFrom(array $rows): array
    {
        $overrides = [];

        foreach ($rows as $row) {
            $affiliationId = (int) ($row['affiliation_id'] ?? 0);
            $affiliateId = (int) ($row['affiliate_id'] ?? 0);
            $rangeId = (int) ($row['age_range_id'] ?? 0);

            if ($affiliationId > 0 && $affiliateId > 0 && $rangeId > 0 && $rangeId !== (int) ($row['suggested_age_range_id'] ?? 0)) {
                $overrides[$affiliationId][$affiliateId] = $rangeId;
            }
        }

        return $overrides;
    }

    private static function refreshPreview(Get $get, Set $set, int $affiliationId): void
    {
        $affiliation = Affiliation::query()->find($affiliationId);

        if ($affiliation === null) {
            return;
        }

        $rows = (array) $get('../../affiliates');
        $overrides = self::overridesFrom($rows)[$affiliationId] ?? [];
        $previews = (array) $get('../../previews');
        $previews[(string) $affiliationId] = app(IndividualAffiliationPlanChanger::class)->preview(
            $affiliation,
            self::requestFromState($get, [$affiliationId => $overrides], '../../')($affiliation),
        );

        $set('../../previews', $previews);
    }

    /**
     * Al autorizar (o retirar) la excepción cambian las afiliaciones bloqueadas: se recalculan todas
     * y se agregan las filas de rango de las que se destrabaron, sin perder los rangos ya elegidos.
     */
    private static function refreshAllPreviews(Get $get, Set $set): void
    {
        $ids = self::affiliationIds($get);

        if ($ids === []) {
            return;
        }

        $rows = (array) $get('affiliates');
        $previews = self::previews($ids, self::requestFromState($get, self::overridesFrom($rows)));
        $set('previews', $previews);
        $set('affiliates', self::mergeAffiliateRows($rows, self::affiliateRows($previews)));
    }

    /**
     * @param  list<array<string, mixed>>  $current
     * @param  list<array<string, mixed>>  $fresh
     * @return list<array<string, mixed>>
     */
    public static function mergeAffiliateRows(array $current, array $fresh): array
    {
        $chosen = [];

        foreach ($current as $row) {
            $rangeId = (int) ($row['age_range_id'] ?? 0);

            if ($rangeId > 0 && $rangeId !== (int) ($row['suggested_age_range_id'] ?? 0)) {
                $chosen[(int) ($row['affiliate_id'] ?? 0)] = $rangeId;
            }
        }

        return array_map(function (array $row) use ($chosen): array {
            if (isset($chosen[$row['affiliate_id']])) {
                $row['age_range_id'] = $chosen[$row['affiliate_id']];
            }

            return $row;
        }, $fresh);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $previews
     */
    public static function hasIdealAgeIssue(array $previews): bool
    {
        foreach ($previews as $preview) {
            if (($preview['blocked_rule'] ?? null) === IndividualPlanChangeBlockedException::RULE_IDEAL_AGE_RANGE || ($preview['exception'] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, int>>  $overrides  id de afiliación => [id de afiliado => id de rango]
     */
    private static function requestFromState(Get $get, array $overrides = [], string $prefix = ''): callable
    {
        return fn (Affiliation $affiliation): IndividualPlanChangeRequest => new IndividualPlanChangeRequest(
            (int) $get($prefix.'plan_id'),
            filled($get($prefix.'coverage_id')) ? (int) $get($prefix.'coverage_id') : null,
            (string) $get($prefix.'frequency'),
            $overrides[(int) $affiliation->id] ?? [],
            '',
            (bool) $get($prefix.'allow_ideal_exception'),
            trim((string) $get($prefix.'exception_note')),
        );
    }

    /**
     * @return list<int>
     */
    private static function affiliationIds(Get $get): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $get('affiliation_ids')))));
    }

    /**
     * @param  list<int|string>  $ids
     */
    private static function selectionSummary(array $ids): HtmlString
    {
        $count = count($ids);
        $over = $count > IndividualAffiliationPlanChanger::MAX_BATCH;

        return new HtmlString(
            '<p style="margin:0;font-size:14px;'.($over ? 'color:rgb(185 28 28);' : '').'">'
            .($count === 1 ? '1 afiliación seleccionada.' : $count.' afiliaciones seleccionadas.')
            .($over ? ' El máximo por vez es '.IndividualAffiliationPlanChanger::MAX_BATCH.': reduzca la selección.' : ' El cambio se aplica a todas; las que tengan un bloqueo se omiten y se informan.')
            .'</p>'
        );
    }

    /**
     * @param  array{changed: list<array<string, mixed>>, blocked: list<array{label: string, reason: string}>, batch_uuid: string}  $summary
     */
    public static function notifySummary(array $summary): void
    {
        $changed = count($summary['changed']);
        $blocked = $summary['blocked'];
        $created = array_sum(array_map(fn (array $item): int => count($item['billing']['created'] ?? []), $summary['changed']));
        $cancelled = array_sum(array_map(fn (array $item): int => count($item['billing']['cancelled'] ?? []), $summary['changed']));
        $updated = array_sum(array_map(fn (array $item): int => count($item['billing']['updated'] ?? []), $summary['changed']));

        if ($changed > 0) {
            Notification::make()
                ->title($changed === 1 ? 'Plan cambiado en 1 afiliación' : 'Plan cambiado en '.$changed.' afiliaciones')
                ->body(trim(
                    ($updated > 0 ? $updated.' cuota(s) POR PAGAR recalculadas. ' : '')
                    .($cancelled > 0 ? $cancelled.' cuota(s) anuladas y '.$created.' nuevas con la frecuencia elegida. ' : '')
                    .'El certificado y los carnets se regeneran en cola.'
                ))
                ->success()
                ->persistent()
                ->send();
        }

        if ($blocked !== []) {
            Notification::make()
                ->title(count($blocked) === 1 ? '1 afiliación no se cambió' : count($blocked).' afiliaciones no se cambiaron')
                ->body(implode('<br>', array_map(
                    fn (array $item): string => '<strong>'.e($item['label']).'</strong>: '.e($item['reason']),
                    $blocked,
                )))
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
