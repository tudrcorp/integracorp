<?php

declare(strict_types=1);

namespace App\Filament\Business\Concerns;

use App\Support\Affiliations\PersonIdentity\DuplicatePersonIdentityException;
use App\Support\Upgrades\UpgradeAssignmentPreview;
use App\Support\Upgrades\UpgradeIlsVoucher;
use App\Support\Upgrades\UpgradePricing;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * «Agregar upgrade» con recálculo detallado y Voucher ILS obligatorio, igual en
 * afiliados individuales y corporativos.
 *
 * La modal guía en tres pasos: elegir el upgrade, revisar el recálculo (quién lo
 * recibe por su edad, tarifas antes y después, total de la afiliación y cuotas
 * por pagar) y cargar el voucher de cada afiliado que lo recibe. Nada se guarda
 * si hay bloqueos, si falta un voucher o si alguno no es válido: el gestor del
 * upgrade escribe todo en una sola transacción.
 *
 * El relation manager que lo usa resuelve el gestor de su tipo de afiliado.
 */
trait AssignsUpgradesWithIlsVoucher
{
    /** Tope de afiliados por lote: cada uno carga su propio voucher. */
    public const UPGRADE_BULK_LIMIT = 20;

    /**
     * Vistas previas ya calculadas en este request (la modal se pinta varias veces).
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $upgradePreviewMemo = [];

    /**
     * @param  list<int>  $affiliateIds
     * @return array<string, mixed>
     */
    abstract protected function upgradeManagerPreview(array $affiliateIds, mixed $coverageId): array;

    /**
     * @param  list<int>  $affiliateIds
     * @param  array<int, array<string, mixed>>  $vouchers
     * @return array{affiliates_updated: int, upgrades_created: int, annual_delta: float, collections_adjusted: int, skipped: list<array<string, string>>}
     */
    abstract protected function upgradeManagerAdd(array $affiliateIds, mixed $coverageId, array $vouchers): array;

    abstract protected function upgradeAffiliateLabel(Model $affiliate): string;

    /**
     * Acción por fila.
     */
    protected function addUpgradeWithVoucherAction(Closure $visible): Action
    {
        return Action::make('add_upgrade')
            ->label('Agregar upgrade')
            ->icon(Heroicon::Sparkles)
            ->color('success')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalIcon(Heroicon::Sparkles)
            ->modalHeading(fn (Model $record): string => 'Agregar upgrade a '.$this->upgradeAffiliateLabel($record))
            ->modalDescription('1) Elija el upgrade. 2) Revise el recálculo: el precio sale de la edad del afiliado. 3) Cargue su Voucher ILS, obligatorio. Nada se guarda hasta confirmar.')
            ->modalSubmitActionLabel('Agregar upgrade y guardar voucher')
            ->schema(fn (Model $record): array => [
                $this->upgradeCoverageSelect(),
                $this->upgradePreviewView(fn (): array => [(int) $record->getKey()]),
                Section::make('Paso 3 · Voucher ILS')
                    ->description('Obligatorio. Queda guardado con este upgrade; no reemplaza el voucher del plan.')
                    ->icon(Heroicon::Ticket)
                    ->statePath('voucher')
                    ->visible(fn (Get $get): bool => filled($get('upgrade_coverage_id')))
                    ->schema($this->upgradeVoucherFields(fn (): bool => true))
                    ->columnSpanFull(),
            ])
            ->action(function (Action $action, Model $record, array $data): void {
                $this->runUpgradeAssignment(
                    $action,
                    [(int) $record->getKey()],
                    $data['upgrade_coverage_id'] ?? null,
                    [(int) $record->getKey() => (array) ($data['voucher'] ?? [])],
                );
            })
            ->visible($visible);
    }

    /**
     * Acción en lote: una fila por afiliado con su recálculo y su voucher.
     */
    protected function addUpgradeWithVoucherBulkAction(Closure $visible): BulkAction
    {
        return BulkAction::make('add_upgrade_bulk')
            ->label('Agregar upgrade')
            ->icon(Heroicon::Sparkles)
            ->color('success')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalIcon(Heroicon::Sparkles)
            ->modalHeading(fn (Collection $records): string => 'Agregar upgrade a '.$records->count().' '.($records->count() === 1 ? 'afiliado' : 'afiliados'))
            ->modalDescription('1) Elija el upgrade. 2) Revise el recálculo de cada afiliado: el precio sale de su edad y quien no tenga la edad no lo recibe. 3) Cargue el Voucher ILS de cada afiliado que lo recibe. Nada se guarda hasta confirmar.')
            ->modalSubmitActionLabel('Agregar upgrade y guardar vouchers')
            ->mountUsing(function (BulkAction $action, ?Schema $schema, Collection $records): void {
                if ($records->count() > self::UPGRADE_BULK_LIMIT) {
                    Notification::make()
                        ->warning()
                        ->title('Demasiados afiliados seleccionados')
                        ->body('Cada afiliado carga su propio Voucher ILS: seleccione hasta '.self::UPGRADE_BULK_LIMIT.' por vez.')
                        ->send();

                    $action->halt();
                }

                $schema?->fill([
                    'upgrade_coverage_id' => null,
                    'vouchers' => $records->mapWithKeys(fn (Model $record): array => ['affiliate-'.$record->getKey() => [
                        'affiliate_id' => (int) $record->getKey(),
                        'name' => $this->upgradeAffiliateLabel($record),
                    ]])->all(),
                ]);
            })
            ->schema(fn (Collection $records): array => [
                $this->upgradeCoverageSelect(),
                $this->upgradePreviewView(fn (): array => array_map('intval', $records->modelKeys())),
                Section::make('Paso 3 · Voucher ILS de cada afiliado')
                    ->description('Obligatorio para cada afiliado que recibe el upgrade. Cada voucher queda con su upgrade; el número no se puede repetir.')
                    ->icon(Heroicon::Ticket)
                    ->visible(fn (Get $get): bool => filled($get('upgrade_coverage_id')))
                    ->schema([
                        Repeater::make('vouchers')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(fn (array $state): string => (string) ($state['name'] ?? 'Afiliado'))
                            ->schema(function () use ($records): array {
                                $receives = function (Get $get) use ($records): bool {
                                    $preview = $this->memoizedUpgradePreview(array_map('intval', $records->modelKeys()), $get('../../upgrade_coverage_id'));

                                    return in_array((int) $get('affiliate_id'), UpgradeAssignmentPreview::receivingIds($preview), true);
                                };

                                return [
                                    Hidden::make('affiliate_id'),
                                    Hidden::make('name'),
                                    Placeholder::make('not_receiving')
                                        ->hiddenLabel()
                                        ->content('No recibe el upgrade (vea el motivo en el recálculo): no necesita voucher.')
                                        ->visible(fn (Get $get): bool => ! $receives($get)),
                                    Grid::make(1)
                                        ->visible($receives)
                                        ->schema($this->upgradeVoucherFields($receives)),
                                ];
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (BulkAction $action, Collection $records, array $data): void {
                $vouchers = [];

                foreach ((array) ($data['vouchers'] ?? []) as $item) {
                    if (is_array($item) && (int) ($item['affiliate_id'] ?? 0) > 0) {
                        $vouchers[(int) $item['affiliate_id']] = $item;
                    }
                }

                $this->runUpgradeAssignment($action, array_map('intval', $records->modelKeys()), $data['upgrade_coverage_id'] ?? null, $vouchers);
            })
            ->visible($visible);
    }

    private function upgradeCoverageSelect(): Select
    {
        return Select::make('upgrade_coverage_id')
            ->label('Paso 1 · Upgrade y cobertura')
            ->helperText('Se administran en Configuración → Upgrades.')
            ->placeholder('Buscar upgrade…')
            ->options(fn (): array => UpgradePricing::coverageOptions())
            ->searchable()
            ->required()
            ->live()
            ->noSearchResultsMessage('No hay upgrades activos con ese nombre.')
            ->validationMessages(['required' => 'Elija el upgrade y su cobertura.'])
            ->columnSpanFull();
    }

    /**
     * @param  Closure(): list<int>  $affiliateIds
     */
    private function upgradePreviewView(Closure $affiliateIds): View
    {
        return View::make('filament.business.upgrades.assignment-preview')
            ->visible(fn (Get $get): bool => filled($get('upgrade_coverage_id')))
            ->viewData(fn (Get $get): array => [
                'preview' => $this->memoizedUpgradePreview($affiliateIds(), $get('upgrade_coverage_id')),
            ])
            ->columnSpanFull();
    }

    /**
     * Campos del voucher (con su `statePath` relativo a la fila o a `voucher`).
     *
     * @param  Closure  $required  cuándo son obligatorios
     * @return list<\Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    private function upgradeVoucherFields(Closure $required): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 4])->schema([
                TextInput::make('code')
                    ->label('Número de voucher')
                    ->placeholder('Ej. ILS-000123')
                    ->maxLength(UpgradeIlsVoucher::MAX_CODE_LENGTH)
                    ->required($required)
                    ->live(onBlur: true)
                    ->validationMessages(['required' => 'Falta el número de voucher.']),
                DatePicker::make('date_init')
                    ->label('Desde')
                    ->displayFormat('d/m/Y')
                    ->live(onBlur: true)
                    ->required($required)
                    ->validationMessages(['required' => 'Falta la fecha desde.']),
                DatePicker::make('date_end')
                    ->label('Hasta')
                    ->displayFormat('d/m/Y')
                    ->live(onBlur: true)
                    ->afterOrEqual('date_init')
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $end = UpgradeIlsVoucher::parseDate($value);

                        if ($end !== null && $end->lt(now()->startOfDay())) {
                            $fail('El voucher venció el '.$end->format('d/m/Y').': cargue uno vigente.');
                        }
                    })
                    ->required($required)
                    ->validationMessages([
                        'required' => 'Falta la fecha hasta.',
                        'after_or_equal' => 'No puede ser anterior a la fecha desde.',
                    ]),
                Placeholder::make('days')
                    ->label('Vigencia restante')
                    ->content(function (Get $get): string {
                        $days = UpgradeIlsVoucher::remainingDays($get('date_end'));

                        return match (true) {
                            $days === null => '—',
                            $days === 0 => 'Vence hoy o ya venció',
                            default => $days.' '.($days === 1 ? 'día' : 'días'),
                        };
                    }),
            ]),
            FileUpload::make('document_path')
                ->label('Comprobante del voucher')
                ->helperText('PDF o imagen, máximo 10 MB.')
                ->disk(UpgradeIlsVoucher::DOCUMENT_DISK)
                ->directory(UpgradeIlsVoucher::DOCUMENT_DIRECTORY)
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(10240)
                ->openable()
                ->required($required)
                ->validationMessages(['required' => 'Falta el comprobante.']),
        ];
    }

    /**
     * @param  list<int>  $affiliateIds
     * @return array<string, mixed>
     */
    private function memoizedUpgradePreview(array $affiliateIds, mixed $coverageId): array
    {
        if (blank($coverageId)) {
            return UpgradeAssignmentPreview::empty();
        }

        $key = implode(',', $affiliateIds).'|'.(string) $coverageId;

        return $this->upgradePreviewMemo[$key] ??= $this->upgradeManagerPreview($affiliateIds, $coverageId);
    }

    /**
     * Guarda el upgrade y los vouchers, o deja la modal abierta con el motivo.
     *
     * @param  list<int>  $affiliateIds
     * @param  array<int, array<string, mixed>>  $vouchers
     */
    private function runUpgradeAssignment(Action $action, array $affiliateIds, mixed $coverageId, array $vouchers): void
    {
        $this->upgradePreviewMemo = [];
        $preview = $this->upgradeManagerPreview($affiliateIds, $coverageId);
        $blocking = UpgradeAssignmentPreview::blockingMessage($preview);

        if ($blocking !== null) {
            Notification::make()->danger()->title('No se puede agregar el upgrade')->body($blocking)->persistent()->send();
            $action->halt();
        }

        $receiving = UpgradeAssignmentPreview::receivingIds($preview);
        $vouchers = array_intersect_key($vouchers, array_flip($receiving));

        try {
            $result = $this->upgradeManagerAdd($receiving, $coverageId, $vouchers);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            Notification::make()->danger()->title('No se guardó nada')->body($exception->getMessage())->persistent()->send();
            $action->halt();
        } catch (DuplicatePersonIdentityException $duplicatePerson) {
            throw $duplicatePerson;
        } catch (Throwable $throwable) {
            Log::error('NEGOCIOS-UPGRADES: Error al agregar upgrade con voucher.', [
                'affiliate_ids' => $affiliateIds,
                'upgrade_coverage_id' => $coverageId,
                'error' => $throwable->getMessage(),
            ]);

            Notification::make()->danger()->title('No se pudo agregar el upgrade')->body('No se aplicó ningún cambio. Intente de nuevo o contacte a soporte.')->persistent()->send();
            $action->halt();
        }

        if ($result['upgrades_created'] === 0) {
            Notification::make()
                ->warning()
                ->title('No se agregó ningún upgrade')
                ->body(implode(' ', array_map(
                    static fn (array $skipped): string => $skipped['name'].': '.UpgradePricing::skipReasonLabel($skipped['reason']).'.',
                    $result['skipped'],
                )))
                ->persistent()
                ->send();
            $action->halt();
        }

        $lines = [
            $result['upgrades_created'].' '.($result['upgrades_created'] === 1 ? 'upgrade agregado' : 'upgrades agregados').' con su Voucher ILS.',
        ];

        if (abs($result['annual_delta']) >= 0.01) {
            $lines[] = 'Total anual de la afiliación +'.UpgradePricing::money($result['annual_delta']).'.';
        }

        if ($result['collections_adjusted'] > 0) {
            $lines[] = $result['collections_adjusted'].' '.($result['collections_adjusted'] === 1 ? 'cuota por pagar ajustada' : 'cuotas por pagar ajustadas').'.';
        }

        Notification::make()->success()->title('Upgrade agregado')->body(implode(' ', $lines))->send();
    }
}
