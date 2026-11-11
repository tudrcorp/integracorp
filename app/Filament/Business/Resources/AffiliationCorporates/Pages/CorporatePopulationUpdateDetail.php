<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Pages;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Forms\Components\OtpBoxesInput;
use App\Jobs\AnalyzeCorporatePopulationUpdateJob;
use App\Jobs\ApplyCorporatePopulationUpdateJob;
use App\Jobs\RollbackCorporatePopulationUpdateJob;
use App\Jobs\SyncCorporatePopulationItemsJob;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\CorporatePopulationOtpChallenge;
use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem as Item;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;
use App\Support\AffiliationCorporates\Population\CorporatePopulationOtp;
use App\Support\AffiliationCorporates\Population\PopulationFieldComparison as Field;
use App\Support\AffiliationCorporates\Population\PopulationItemCorrection;
use App\Support\AffiliationCorporates\Population\PopulationItemExplainer;
use App\Support\AffiliationCorporates\Population\PopulationUpdateAnalyzer;
use App\Support\AffiliationCorporates\Population\PopulationUpdateApplier;
use App\Support\AffiliationCorporates\Population\PopulationUpdateRollback;
use App\Support\AffiliationCorporates\Population\PopulationUpdateUrls;
use App\Support\Otp\SelfAuthorizedOtp;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Locked;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Detalle de una carga de población: resumen, filas por tipo de acción,
 * selección del analista, aprobación con OTP y rollback con OTP.
 */
class CorporatePopulationUpdateDetail extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    /** Pestaña «Cambios»: todo lo que modifica datos si se aprueba. */
    public const TAB_CHANGES = 'CHANGES';

    /** Pestaña «Coincide con diferencias»: misma persona, datos distintos al archivo. */
    public const TAB_MATCH_DIFFERENCES = 'MATCH_DIFFERENCES';

    /** @var array<int, array<string, mixed>> explicación por fila, solo durante el render */
    protected array $explanationMemo = [];

    /** @var array<int, AffiliateCorporate>|null fichas «originales» de los duplicados en la base */
    protected ?array $originalsMemo = null;

    /** @var \Illuminate\Support\Collection<int, \App\Models\AfilliationCorporatePlan>|null */
    protected ?\Illuminate\Support\Collection $planRowsMemo = null;

    protected static string $resource = AffiliationCorporateResource::class;

    protected static ?string $title = 'Carga de población';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.business.resources.affiliation-corporates.pages.population-update-detail';

    protected Width|string|null $maxContentWidth = Width::Full;

    #[Locked]
    public int $updateId = 0;

    public string $activeTab = self::TAB_CHANGES;

    #[Locked]
    public ?string $pendingApplyChallenge = null;

    #[Locked]
    public ?string $pendingRollbackChallenge = null;

    public static function getRoutePath(Panel $panel): string
    {
        return '/{record}/poblacion/{update}';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return CorporatePopulationUpdates::canAccess();
    }

    public function mount(int|string $record, string $update): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::canAccess(), 403);

        $found = CorporatePopulationUpdate::query()
            ->where('public_id', $update)
            ->where('affiliation_corporate_id', $this->getRecord()->getKey())
            ->first();

        abort_if($found === null, 404);
        $this->updateId = (int) $found->getKey();

        $counts = (array) ($found->summary['counts'] ?? []);
        if (array_sum(array_intersect_key($counts, array_flip([...Item::CHANGING_ACTIONS, Item::ACTION_DUPLICATE_BASE]))) === 0 && ($counts[Item::ACTION_REVIEW] ?? 0) > 0) {
            $this->activeTab = Item::ACTION_REVIEW;
        }
    }

    public function populationUpdate(): CorporatePopulationUpdate
    {
        return CorporatePopulationUpdate::query()->findOrFail($this->updateId);
    }

    public function owner(): AffiliationCorporate
    {
        /** @var AffiliationCorporate $owner */
        $owner = $this->getRecord();

        return $owner;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Carga de población · '.($this->owner()->name_corporate ?: $this->owner()->code);
    }

    public function getSubheading(): string|Htmlable|null
    {
        $update = $this->populationUpdate();

        return 'Archivo «'.$update->file_name.'» · cargado el '.$update->created_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i').($update->analyzed_by_name ? ' por '.$update->analyzed_by_name : '');
    }

    public function isEditable(): bool
    {
        return $this->populationUpdate()->status === CorporatePopulationUpdate::STATUS_ANALYZED;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetTable();
    }

    /**
     * @return list<array{key: string, label: string, count: int, color: string}>
     */
    public function tabs(): array
    {
        $counts = (array) ($this->populationUpdate()->summary['counts'] ?? []);
        $changes = array_sum(array_intersect_key($counts, array_flip([...Item::CHANGING_ACTIONS, Item::ACTION_DUPLICATE_BASE])));

        $tabs = [['key' => self::TAB_CHANGES, 'label' => 'Cambios propuestos', 'count' => $changes, 'color' => 'primary']];
        foreach ([Item::ACTION_ADD, Item::ACTION_REACTIVATE, Item::ACTION_DEACTIVATE, Item::ACTION_FIX_DOCUMENT, Item::ACTION_DUPLICATE_BASE, Item::ACTION_REVIEW, Item::ACTION_BLOCKED_OPEN_CASE, Item::ACTION_DUPLICATE_FILE, Item::ACTION_INVALID, self::TAB_MATCH_DIFFERENCES, Item::ACTION_MATCH] as $action) {
            if ($action === self::TAB_MATCH_DIFFERENCES) {
                $withDifferences = (int) ($this->populationUpdate()->summary['match_with_differences'] ?? 0);
                if ($withDifferences > 0) {
                    $tabs[] = ['key' => $action, 'label' => 'Coincide con diferencias', 'count' => $withDifferences, 'color' => 'warning'];
                }

                continue;
            }

            if (($counts[$action] ?? 0) > 0 || in_array($action, [Item::ACTION_ADD, Item::ACTION_DEACTIVATE], true)) {
                $tabs[] = ['key' => $action, 'label' => Item::actionLabel($action), 'count' => (int) ($counts[$action] ?? 0), 'color' => Item::actionColor($action)];
            }
        }

        return $tabs;
    }

    /**
     * Seleccionado por acción (en vivo, para el resumen y la aprobación).
     *
     * @return array<string, int>
     */
    public function selectedCounts(): array
    {
        return PopulationUpdateApplier::selectedCounts($this->populationUpdate());
    }

    /**
     * Efecto estimado sobre los totales de la afiliación si se aprueba lo seleccionado.
     *
     * @return array{persons: int, fee: float, deactivations: int, mass: bool}
     */
    public function preview(): array
    {
        $items = Item::query()
            ->where('corporate_population_update_id', $this->updateId)
            ->where('selected', true)
            ->whereIn('action', [...Item::CHANGING_ACTIONS, Item::ACTION_DUPLICATE_BASE])
            ->get(['action', 'affiliate_corporate_id', 'details']);

        $fees = AffiliateCorporate::query()->whereIn('id', $items->pluck('affiliate_corporate_id')->filter()->all() ?: [0])->pluck('fee', 'id');
        $persons = 0;
        $fee = 0.0;
        $deactivations = 0;

        foreach ($items as $item) {
            switch ($item->action) {
                case Item::ACTION_ADD:
                    $persons++;
                    $fee += (float) ($item->details['fee'] ?? 0);
                    break;
                case Item::ACTION_REACTIVATE:
                    $persons++;
                    $fee += (float) ($fees[$item->affiliate_corporate_id] ?? 0);
                    break;
                case Item::ACTION_DEACTIVATE:
                case Item::ACTION_DUPLICATE_BASE:
                    $persons--;
                    $deactivations++;
                    $fee -= (float) ($fees[$item->affiliate_corporate_id] ?? 0);
                    break;
            }
        }

        $countable = (int) ($this->populationUpdate()->summary['base_countable'] ?? 0);

        return [
            'persons' => $persons,
            'fee' => round($fee, 2),
            'deactivations' => $deactivations,
            'mass' => PopulationUpdateAnalyzer::isMassDeactivation($deactivations, $countable),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Cargas')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): ?string => PopulationUpdateUrls::index((int) $this->owner()->getKey())),
            $this->approveAction(),
            $this->rollbackAction(),
            ActionGroup::make([
                Action::make('downloadFile')
                    ->label('Descargar archivo original')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (): ?StreamedResponse => $this->downloadOriginal()),
                Action::make('reanalyze')
                    ->label('Volver a analizar')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (): bool => in_array($this->populationUpdate()->status, [CorporatePopulationUpdate::STATUS_ANALYZED, CorporatePopulationUpdate::STATUS_FAILED], true))
                    ->requiresConfirmation()
                    ->modalHeading('¿Volver a analizar el archivo?')
                    ->modalDescription('Se compara de nuevo con los afiliados de hoy. Las casillas que haya cambiado y las decisiones tomadas en cada fila (revisados, diferencias aceptadas, separaciones) vuelven a la propuesta del sistema. Las correcciones de datos ya guardadas se conservan en las fichas y en «Auditoría de cambios».')
                    ->action(fn () => $this->reanalyze()),
                Action::make('discard')
                    ->label('Descartar carga')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (): bool => in_array($this->populationUpdate()->status, [CorporatePopulationUpdate::STATUS_ANALYZED, CorporatePopulationUpdate::STATUS_FAILED], true))
                    ->requiresConfirmation()
                    ->modalHeading('¿Descartar esta carga?')
                    ->modalDescription('No se aplicará ningún cambio. La carga queda en el historial como descartada.')
                    ->modalSubmitActionLabel('Sí, descartar')
                    ->action(fn () => $this->discard()),
            ])->label('Más')->icon(Heroicon::OutlinedEllipsisVertical)->button()->color('gray'),
        ];
    }

    protected function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Aprobar y aplicar')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('success')
            ->visible(fn (): bool => $this->isEditable())
            ->modalHeading('Aprobar y aplicar la actualización')
            ->modalIcon(Heroicon::OutlinedShieldCheck)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalDescription('Se aplicará todo junto o nada. Antes de cambiar cada afiliado se guarda su foto para poder revertir.')
            ->schema([
                Placeholder::make('approve_summary')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => new HtmlString(view('filament.business.resources.affiliation-corporates.partials.population-approve-summary', [
                        'selected' => $this->selectedCounts(),
                        'preview' => $this->preview(),
                        'owner' => $this->owner(),
                    ])->render())),
                Checkbox::make('mass_confirmed')
                    ->label(fn (): string => 'Confirmo que el archivo es la población COMPLETA de la empresa y que se darán de baja '.$this->preview()['deactivations'].' afiliados.')
                    ->visible(fn (): bool => $this->preview()['mass'])
                    ->accepted(fn (): bool => $this->preview()['mass']),
                Placeholder::make('otp_status')
                    ->label('Clave OTP')
                    ->visible(fn (): bool => filled($this->pendingApplyChallenge))
                    ->content('La clave se envió a los destinatarios del centro de notificaciones. Pídasela y escríbala aquí. Vence en '.CorporatePopulationOtp::ttl().' minutos.'),
                ...$this->ownCodeFields(CorporatePopulationOtpChallenge::PURPOSE_APPLY),
                OtpBoxesInput::make('otp_code')
                    ->label('Clave de 6 dígitos')
                    ->length(6)
                    ->visible(fn (Get $get): bool => filled($this->pendingApplyChallenge) && ! $this->usingOwnCode($get, CorporatePopulationOtpChallenge::PURPOSE_APPLY))
                    ->required(fn (Get $get): bool => filled($this->pendingApplyChallenge) && ! $this->usingOwnCode($get, CorporatePopulationOtpChallenge::PURPOSE_APPLY)),
            ])
            ->modalSubmitActionLabel(fn (): string => filled($this->pendingApplyChallenge) ? 'Confirmar clave y aplicar' : 'Solicitar clave OTP')
            ->extraModalFooterActions(fn (): array => [$this->resendAction(CorporatePopulationOtpChallenge::PURPOSE_APPLY)])
            ->action(function (array $data, Action $action): void {
                $this->handleApprove($data, $action);
            });
    }

    protected function rollbackAction(): Action
    {
        return Action::make('rollback')
            ->label('Revertir carga')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (): bool => $this->populationUpdate()->status === CorporatePopulationUpdate::STATUS_APPLIED)
            ->disabled(fn (): bool => PopulationUpdateRollback::blockingReason($this->populationUpdate()) !== null)
            ->tooltip(fn (): ?string => PopulationUpdateRollback::blockingReason($this->populationUpdate()))
            ->modalHeading('Revertir esta carga')
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalIconColor('danger')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalDescription('Cada afiliado vuelve a como estaba antes de la carga y los totales reciben lo inverso. Quien cambió después o ya tuvo actividad clínica no se toca: se le informa.')
            ->schema([
                Textarea::make('note')
                    ->label('¿Por qué se revierte?')
                    ->placeholder('Ej.: La empresa envió un archivo incompleto; se cargará la versión corregida.')
                    ->rows(3)
                    ->required()
                    ->minLength(PopulationUpdateRollback::NOTE_MIN_LENGTH)
                    ->maxLength(1000),
                Placeholder::make('otp_status')
                    ->label('Clave OTP')
                    ->visible(fn (): bool => filled($this->pendingRollbackChallenge))
                    ->content('La clave se envió a los destinatarios del centro de notificaciones. Pídasela y escríbala aquí.'),
                ...$this->ownCodeFields(CorporatePopulationOtpChallenge::PURPOSE_ROLLBACK),
                OtpBoxesInput::make('otp_code')
                    ->label('Clave de 6 dígitos')
                    ->length(6)
                    ->visible(fn (Get $get): bool => filled($this->pendingRollbackChallenge) && ! $this->usingOwnCode($get, CorporatePopulationOtpChallenge::PURPOSE_ROLLBACK))
                    ->required(fn (Get $get): bool => filled($this->pendingRollbackChallenge) && ! $this->usingOwnCode($get, CorporatePopulationOtpChallenge::PURPOSE_ROLLBACK)),
            ])
            ->modalSubmitActionLabel(fn (): string => filled($this->pendingRollbackChallenge) ? 'Confirmar clave y revertir' : 'Solicitar clave OTP')
            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
            ->extraModalFooterActions(fn (): array => [$this->resendAction(CorporatePopulationOtpChallenge::PURPOSE_ROLLBACK)])
            ->action(function (array $data, Action $action): void {
                $this->handleRollback($data, $action);
            });
    }

    protected function resendAction(string $purpose): Action
    {
        return Action::make('resend'.ucfirst(strtolower($purpose)))
            ->label('Reenviar clave')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (): bool => filled($purpose === CorporatePopulationOtpChallenge::PURPOSE_APPLY ? $this->pendingApplyChallenge : $this->pendingRollbackChallenge))
            ->action(function (Action $action) use ($purpose): void {
                $challenge = $this->pendingChallenge($purpose);
                $user = Auth::user();

                try {
                    if (! $challenge instanceof CorporatePopulationOtpChallenge || ! $user instanceof User) {
                        throw new RuntimeException('Solicite primero la clave.');
                    }

                    CorporatePopulationOtp::resend($challenge, $user, $this->populationUpdate());
                    Notification::make()->title('Clave reenviada')->success()->send();
                } catch (Throwable $exception) {
                    Notification::make()->title('No se pudo reenviar')->body($exception->getMessage())->danger()->send();
                }

                $action->halt();
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleApprove(array $data, Action $action): void
    {
        abort_unless(static::canAccess(), 403);
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $update = $this->populationUpdate();
        $preview = $this->preview();

        if (array_sum($this->selectedCounts()) === 0) {
            Notification::make()->title('No hay cambios seleccionados')->body('Marque al menos una fila en «Cambios propuestos».')->warning()->send();
            $action->halt();

            return;
        }

        if (Cache::has(SyncCorporatePopulationItemsJob::busyKey($this->updateId))) {
            Notification::make()->title('Hay una sincronización en curso')->body('Espere a que termine «Sincronizar con el archivo» (le llegará un aviso) y vuelva a aprobar.')->warning()->send();
            $action->halt();

            return;
        }

        if ($preview['mass'] && ! ($data['mass_confirmed'] ?? false)) {
            Notification::make()->title('Falta su confirmación')->body('Esta carga da de baja a muchos afiliados: marque la confirmación.')->danger()->send();
            $action->halt();

            return;
        }

        if (! filled($this->pendingApplyChallenge)) {
            $this->issueOtp($user, $update, CorporatePopulationOtpChallenge::PURPOSE_APPLY);
            $action->halt();

            return;
        }

        $challenge = $this->pendingChallenge(CorporatePopulationOtpChallenge::PURPOSE_APPLY);
        $useOwn = (bool) ($data['use_own_code'] ?? false);

        try {
            $code = $challenge instanceof CorporatePopulationOtpChallenge
                ? SelfAuthorizedOtp::resolveCode($challenge, $user, CorporatePopulationOtp::kindFor(CorporatePopulationOtpChallenge::PURPOSE_APPLY), $useOwn, $data['own_code_reason'] ?? null, $data['otp_code'] ?? null)
                : '';
        } catch (RuntimeException $exception) {
            Notification::make()->title('No se pudo usar su clave')->body($exception->getMessage())->danger()->send();
            $action->halt();

            return;
        }

        if (! $challenge instanceof CorporatePopulationOtpChallenge
            || ! CorporatePopulationOtp::verifyAndConsume($challenge, $code, $user, $update, CorporatePopulationOtpChallenge::PURPOSE_APPLY)) {
            $this->otpFailed($challenge, CorporatePopulationOtpChallenge::PURPOSE_APPLY);
            $action->halt();

            return;
        }

        if ($useOwn) {
            $this->recordOwnCodeUse($challenge, $user, $update, (string) ($data['own_code_reason'] ?? ''));
        }

        $this->pendingApplyChallenge = null;

        try {
            PopulationUpdateApplier::approve($update, $user, (bool) ($data['mass_confirmed'] ?? false));
        } catch (Throwable $exception) {
            Notification::make()->title('No se pudo aprobar')->body($exception instanceof RuntimeException ? $exception->getMessage() : 'Error inesperado. No se cambió nada.')->danger()->send();

            return;
        }

        ApplyCorporatePopulationUpdateJob::dispatch($this->updateId);

        Notification::make()
            ->title('Aplicando la actualización')
            ->body('Se está aplicando en segundo plano. Le avisaremos al terminar; si algo falla, no se cambia nada.')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRollback(array $data, Action $action): void
    {
        abort_unless(static::canAccess(), 403);
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $update = $this->populationUpdate();
        $reason = PopulationUpdateRollback::blockingReason($update);
        if ($reason !== null) {
            Notification::make()->title('No se puede revertir')->body($reason)->danger()->send();
            $action->halt();

            return;
        }

        if (! filled($this->pendingRollbackChallenge)) {
            $this->issueOtp($user, $update, CorporatePopulationOtpChallenge::PURPOSE_ROLLBACK);
            $action->halt();

            return;
        }

        $challenge = $this->pendingChallenge(CorporatePopulationOtpChallenge::PURPOSE_ROLLBACK);
        $useOwn = (bool) ($data['use_own_code'] ?? false);

        try {
            $code = $challenge instanceof CorporatePopulationOtpChallenge
                ? SelfAuthorizedOtp::resolveCode($challenge, $user, CorporatePopulationOtp::kindFor(CorporatePopulationOtpChallenge::PURPOSE_ROLLBACK), $useOwn, $data['own_code_reason'] ?? null, $data['otp_code'] ?? null)
                : '';
        } catch (RuntimeException $exception) {
            Notification::make()->title('No se pudo usar su clave')->body($exception->getMessage())->danger()->send();
            $action->halt();

            return;
        }

        if (! $challenge instanceof CorporatePopulationOtpChallenge
            || ! CorporatePopulationOtp::verifyAndConsume($challenge, $code, $user, $update, CorporatePopulationOtpChallenge::PURPOSE_ROLLBACK)) {
            $this->otpFailed($challenge, CorporatePopulationOtpChallenge::PURPOSE_ROLLBACK);
            $action->halt();

            return;
        }

        if ($useOwn) {
            $this->recordOwnCodeUse($challenge, $user, $update, (string) ($data['own_code_reason'] ?? ''));
        }

        $this->pendingRollbackChallenge = null;

        try {
            PopulationUpdateRollback::start($update, $user, (string) ($data['note'] ?? ''));
        } catch (Throwable $exception) {
            Notification::make()->title('No se pudo revertir')->body($exception instanceof RuntimeException ? $exception->getMessage() : 'Error inesperado. No se cambió nada.')->danger()->send();

            return;
        }

        RollbackCorporatePopulationUpdateJob::dispatch($this->updateId);

        Notification::make()
            ->title('Revirtiendo la carga')
            ->body('Se está revirtiendo en segundo plano. Le avisaremos al terminar con el detalle de lo que volvió atrás.')
            ->success()
            ->send();
    }

    protected function issueOtp(User $user, CorporatePopulationUpdate $update, string $purpose): void
    {
        try {
            $issued = CorporatePopulationOtp::issue($user, $update, $purpose);
        } catch (Throwable $exception) {
            Notification::make()->title('No se pudo enviar la clave')->body($exception->getMessage())->danger()->persistent()->send();

            return;
        }

        if ($purpose === CorporatePopulationOtpChallenge::PURPOSE_APPLY) {
            $this->pendingApplyChallenge = $issued['challenge']->public_id;
        } else {
            $this->pendingRollbackChallenge = $issued['challenge']->public_id;
        }

        $canUseOwn = SelfAuthorizedOtp::userCan($user, CorporatePopulationOtp::kindFor($purpose));

        if (! ($issued['delivered'] ?? true)) {
            Notification::make()
                ->title('La clave no se pudo entregar')
                ->body('No llegó a ningún autorizador (revise los destinatarios en el centro de notificaciones). Como tiene permiso, marque «Usar la clave que yo solicité» para continuar.')
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Clave enviada')
            ->body('La OTP se envió a '.$issued['emails'].' correo(s) y '.$issued['phones'].' WhatsApp. Pídasela al autorizador y escríbala.'
                .($canUseOwn ? ' Si el autorizador no responde, puede marcar «Usar la clave que yo solicité».' : ''))
            ->success()
            ->send();
    }

    /**
     * Casilla «Usar la clave que yo solicité» y su motivo (solo con el permiso del tipo de clave).
     *
     * @return list<\Filament\Forms\Components\Field>
     */
    protected function ownCodeFields(string $purpose): array
    {
        $pending = fn (): bool => filled($purpose === CorporatePopulationOtpChallenge::PURPOSE_APPLY ? $this->pendingApplyChallenge : $this->pendingRollbackChallenge);
        $allowed = fn (): bool => $pending() && SelfAuthorizedOtp::userCan(Auth::user(), CorporatePopulationOtp::kindFor($purpose));

        return [
            Toggle::make('use_own_code')
                ->label('Usar la clave que yo solicité')
                ->helperText('Su usuario tiene permiso para continuar sin esperar al autorizador. Queda registrado y se le avisa al autorizador.')
                ->live()
                ->visible($allowed)
                ->dehydrated($allowed),
            Textarea::make('own_code_reason')
                ->label('¿Por qué usa su propia clave?')
                ->placeholder('Ej.: El autorizador no respondió y la empresa necesita la población actualizada hoy.')
                ->rows(2)
                ->visible(fn (Get $get): bool => $allowed() && (bool) $get('use_own_code'))
                ->required(fn (Get $get): bool => $allowed() && (bool) $get('use_own_code'))
                ->minLength(SelfAuthorizedOtp::REASON_MIN_LENGTH)
                ->maxLength(1000),
        ];
    }

    /**
     * La casilla solo cuenta si el usuario tiene el permiso: forzarla oculta no quita la clave.
     */
    protected function usingOwnCode(Get $get, string $purpose): bool
    {
        return (bool) $get('use_own_code') && SelfAuthorizedOtp::userCan(Auth::user(), CorporatePopulationOtp::kindFor($purpose));
    }

    protected function recordOwnCodeUse(CorporatePopulationOtpChallenge $challenge, User $user, CorporatePopulationUpdate $update, string $reason): void
    {
        $update->loadMissing('affiliationCorporate');
        [$emails, $phones] = CorporatePopulationOtp::recipientContacts();

        try {
            SelfAuthorizedOtp::recordUse(
                CorporatePopulationOtp::kindFor((string) $challenge->purpose),
                $challenge,
                $user,
                $reason,
                ($update->affiliationCorporate?->code ?? '').' · '.$update->file_name,
                $emails,
                $phones,
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function otpFailed(?CorporatePopulationOtpChallenge $challenge, string $purpose): void
    {
        $fresh = $challenge?->fresh();
        $remaining = $fresh ? max(0, $fresh->max_attempts - $fresh->attempt_count) : 0;

        if ($fresh === null || ! $fresh->isActive()) {
            if ($purpose === CorporatePopulationOtpChallenge::PURPOSE_APPLY) {
                $this->pendingApplyChallenge = null;
            } else {
                $this->pendingRollbackChallenge = null;
            }
        }

        Notification::make()
            ->title('Clave incorrecta o vencida')
            ->body($remaining > 0 ? 'Quedan '.$remaining.' intento(s).' : 'Solicite una clave nueva.')
            ->danger()
            ->send();
    }

    protected function pendingChallenge(string $purpose): ?CorporatePopulationOtpChallenge
    {
        $publicId = $purpose === CorporatePopulationOtpChallenge::PURPOSE_APPLY ? $this->pendingApplyChallenge : $this->pendingRollbackChallenge;
        if (! filled($publicId)) {
            return null;
        }

        return CorporatePopulationOtpChallenge::query()
            ->where('public_id', $publicId)
            ->where('user_id', Auth::id())
            ->where('corporate_population_update_id', $this->updateId)
            ->first();
    }

    protected function reanalyze(): void
    {
        abort_unless(static::canAccess(), 403);

        $updated = CorporatePopulationUpdate::query()
            ->whereKey($this->updateId)
            ->whereIn('status', [CorporatePopulationUpdate::STATUS_ANALYZED, CorporatePopulationUpdate::STATUS_FAILED])
            ->update(['status' => CorporatePopulationUpdate::STATUS_ANALYZING, 'error' => null, 'analyzed_by' => Auth::id(), 'analyzed_by_name' => Auth::user()?->name, 'updated_at' => now()]);

        if ($updated === 1) {
            AnalyzeCorporatePopulationUpdateJob::dispatch($this->updateId);
            Notification::make()->title('Analizando de nuevo')->body('Le avisaremos cuando esté listo.')->success()->send();
        }
    }

    protected function discard(): void
    {
        abort_unless(static::canAccess(), 403);

        $updated = CorporatePopulationUpdate::query()
            ->whereKey($this->updateId)
            ->whereIn('status', [CorporatePopulationUpdate::STATUS_ANALYZED, CorporatePopulationUpdate::STATUS_FAILED])
            ->update(['status' => CorporatePopulationUpdate::STATUS_DISCARDED, 'updated_at' => now()]);

        if ($updated === 1) {
            SecurityAudit::log('AUDIT_CORPORATE_POPULATION_DISCARDED', 'business.corporate-population.discard', ['update_id' => $this->updateId]);
            Notification::make()->title('Carga descartada')->body('No se aplicó ningún cambio.')->success()->send();
        }
    }

    protected function downloadOriginal(): ?StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $update = $this->populationUpdate();

        if (! Storage::disk($update->file_disk)->exists($update->file_path)) {
            Notification::make()->title('El archivo ya no está en el servidor')->danger()->send();

            return null;
        }

        return Storage::disk($update->file_disk)->download($update->file_path, $update->file_name);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->itemsQuery())
            ->defaultSort('id')
            ->columns([
                TextColumn::make('action')
                    ->label('Acción')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Item::actionLabel($state))
                    ->color(fn (string $state): string => Item::actionColor($state)),
                TextColumn::make('file_person')
                    ->label('En el archivo')
                    ->state(fn (Item $record): string => (string) ($record->file_data['full_name'] ?? '—'))
                    ->description(fn (Item $record): ?string => $record->file_data !== null
                        ? trim('C.I. '.($record->file_data['document'] ?: '—').' · Nac. '.($record->file_data['birth_date'] ? date('d/m/Y', strtotime((string) $record->file_data['birth_date'])) : '—').' · Fila '.$record->file_row)
                        : null)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('base_person')
                    ->label('En la base')
                    ->state(fn (Item $record): string => (string) ($record->details['base_name'] ?? '—'))
                    ->description(fn (Item $record): ?string => isset($record->details['base_name'])
                        ? '#'.$record->affiliate_corporate_id.' · C.I. '.($record->details['base_document'] ?? '—').' · '.($record->details['base_status'] ?? '')
                        : null)
                    ->wrap(),
                ViewColumn::make('explanation')
                    ->label('Detalle')
                    ->state(fn (Item $record): array => $this->explanationFor($record))
                    ->view('filament.business.resources.affiliation-corporates.partials.population-item-detail-cell')
                    ->extraCellAttributes(['class' => 'min-w-[22rem] max-w-2xl !whitespace-normal align-top']),
                ToggleColumn::make('selected')
                    ->label('Incluir')
                    ->disabled(fn (Item $record): bool => ! $record->selectable || ! $this->isEditable())
                    ->afterStateUpdated(fn () => $this->dispatch('$refresh')),
                TextColumn::make('apply_status')
                    ->label('Resultado')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Item $record): string => match ($state) {
                        Item::APPLY_APPLIED => 'Aplicado',
                        Item::APPLY_REVERTED => 'Revertido',
                        Item::APPLY_NOT_REVERTED => 'No revertido',
                        Item::APPLY_SKIPPED => 'Omitido',
                        default => $record->selected && $record->selectable ? 'Por aplicar' : '—',
                    })
                    ->tooltip(fn (Item $record): ?string => $record->result['rollback'] ?? null)
                    ->color(fn (string $state): string => match ($state) {
                        Item::APPLY_APPLIED => 'success',
                        Item::APPLY_REVERTED => 'gray',
                        Item::APPLY_NOT_REVERTED => 'danger',
                        default => 'gray',
                    }),
            ])
            ->searchable()
            ->recordActions([
                $this->explainAction(),
                ActionGroup::make([
                    $this->correctAction(),
                    $this->notSamePersonAction(),
                    $this->resolveDuplicateAction(),
                    $this->notDuplicateAction(),
                    $this->markReviewedAction(),
                    $this->reevaluateAction(),
                    $this->undoCorrectionAction(),
                ])
                    ->label('Resolver')
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->button()
                    ->size('sm')
                    ->color('primary')
                    ->visible(fn (): bool => $this->isEditable()),
            ])
            ->toolbarActions([
                $this->syncWithFileBulkAction(),
                BulkAction::make('include')
                    ->label('Incluir seleccionados')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (): bool => $this->isEditable())
                    ->action(fn (Collection $records) => $this->setSelected($records, true))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('exclude')
                    ->label('Excluir seleccionados')
                    ->icon(Heroicon::OutlinedMinusCircle)
                    ->color('gray')
                    ->visible(fn (): bool => $this->isEditable())
                    ->action(fn (Collection $records) => $this->setSelected($records, false))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Item $record): bool => $record->selectable && $this->isEditable())
            ->emptyStateHeading('Nada en esta pestaña')
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    protected function itemsQuery(): Builder
    {
        $query = Item::query()->where('corporate_population_update_id', $this->updateId);
        $search = trim((string) $this->getTableSearch());

        if ($this->activeTab === self::TAB_CHANGES) {
            $query->whereIn('action', [...Item::CHANGING_ACTIONS, Item::ACTION_DUPLICATE_BASE]);
        } elseif ($this->activeTab === self::TAB_MATCH_DIFFERENCES) {
            $query->where('action', Item::ACTION_MATCH)->whereJsonLength('details->differences', '>', 0);
        } else {
            $query->where('action', $this->activeTab);
        }

        if ($search !== '') {
            $query->where(fn (Builder $q) => $q->where('file_data', 'like', '%'.$search.'%')->orWhere('details', 'like', '%'.$search.'%'));
        }

        return $query->with('affiliateCorporate');
    }

    /**
     * @param  Collection<int, Item>  $records
     */
    protected function setSelected(Collection $records, bool $selected): void
    {
        abort_unless(static::canAccess() && $this->isEditable(), 403);

        Item::query()
            ->where('corporate_population_update_id', $this->updateId)
            ->whereIn('id', $records->modelKeys())
            ->where('selectable', true)
            ->update(['selected' => $selected, 'updated_at' => now()]);
    }

    /**
     * Explicación de la fila (memo por render: la columna y la ventana la piden).
     *
     * @return array<string, mixed>
     */
    public function explanationFor(Item $record): array
    {
        return $this->explanationMemo[(int) $record->getKey()] ??= PopulationItemExplainer::explain(
            $record,
            $record->affiliateCorporate,
            $this->originalFor($record),
            $this->planRows(),
            (int) ($this->populationUpdate()->summary['base_total'] ?? 0),
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\AfilliationCorporatePlan>
     */
    protected function planRows(): \Illuminate\Support\Collection
    {
        return $this->planRowsMemo ??= CorporateAffiliatePlanSynchronizer::planRowsFor($this->owner());
    }

    protected function originalFor(Item $record): ?AffiliateCorporate
    {
        $originalId = (int) ($record->details['original_id'] ?? 0);
        if ($originalId === 0) {
            return null;
        }

        if ($this->originalsMemo === null) {
            $ids = Item::query()
                ->where('corporate_population_update_id', $this->updateId)
                ->whereIn('action', [Item::ACTION_DUPLICATE_BASE, Item::ACTION_DEACTIVATE, Item::ACTION_BLOCKED_OPEN_CASE])
                ->whereNull('file_row')
                ->get(['details'])
                ->map(fn (Item $item): int => (int) ($item->details['original_id'] ?? 0))
                ->filter()
                ->unique()
                ->all();

            $this->originalsMemo = AffiliateCorporate::query()->whereIn('id', $ids ?: [0])->get()->keyBy('id')->all();
        }

        return $this->originalsMemo[$originalId] ?? null;
    }

    /**
     * La fila es de esta carga y la carga se puede modificar.
     */
    protected function guardRecord(Item $record): User
    {
        abort_unless(static::canAccess() && $this->isEditable(), 403);
        abort_unless((int) $record->corporate_population_update_id === $this->updateId, 404);

        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Ejecuta una corrección y muestra el error en español sin cerrar la ventana.
     *
     * @param  callable(): (string|null)  $callback  devuelve el texto del aviso de éxito
     */
    protected function runCorrection(Action $action, string $successTitle, callable $callback): void
    {
        try {
            $body = $callback();
            $this->explanationMemo = [];
            $this->originalsMemo = null;
            Notification::make()->title($successTitle)->body($body)->success()->send();
        } catch (RuntimeException $exception) {
            Notification::make()->title('No se guardó')->body($exception->getMessage())->danger()->persistent()->send();
            $action->halt();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('No se guardó')->body('Error inesperado. No se cambió nada; intente de nuevo o avise a sistemas.')->danger()->persistent()->send();
            $action->halt();
        }
    }

    /**
     * Texto del resultado de una fila después de reevaluarla.
     */
    protected static function outcomeText(Item $item): string
    {
        $pending = array_map(Field::label(...), (array) ($item->details['differences'] ?? []));

        return match ($item->action) {
            Item::ACTION_MATCH => $pending === []
                ? 'Verificado contra el archivo: ahora coincide en todos los datos.'
                : 'Verificado contra el archivo: es la misma persona; siguen distintos '.mb_strtolower(implode(', ', $pending)).'.',
            Item::ACTION_REVIEW => 'Sigue en «Revisar»: '.$item->reason,
            default => 'La fila quedó como «'.Item::actionLabel($item->action).'». '.(string) $item->reason,
        };
    }

    protected function explainAction(): Action
    {
        return Action::make('explain')
            ->label('Explicación')
            ->tooltip('Ver paso a paso por qué el sistema clasificó así esta fila')
            ->icon(Heroicon::OutlinedInformationCircle)
            ->iconButton()
            ->color('gray')
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->modalHeading(fn (Item $record): string => Item::actionLabel($record->action).' · '.($record->file_data['full_name'] ?? $record->details['base_name'] ?? 'Fila'))
            ->modalContent(fn (Item $record): \Illuminate\Contracts\View\View => view('filament.business.resources.affiliation-corporates.partials.population-item-explanation', [
                'explanation' => $this->explanationFor($record),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }

    protected function correctAction(): Action
    {
        $fields = [Field::FIELD_FIRST_NAME, Field::FIELD_LAST_NAME, Field::FIELD_DOCUMENT, Field::FIELD_BIRTH_DATE, Field::FIELD_SEX, Field::FIELD_RELATIONSHIP];

        $useFile = fn (string $field): Action => Action::make('useFile_'.$field)
            ->label('Usar el del archivo')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (Get $get): bool => filled($get('file_'.$field)))
            ->action(fn (Get $get, Set $set) => $set($field, $get('file_'.$field)));

        $hint = fn (string $field): \Closure => fn (Item $record): string => 'Archivo: '.Field::display($field, Field::fileValues((array) $record->file_data)[$field] ?? null)
            .' · Base: '.Field::display($field, $record->affiliateCorporate ? Field::baseValues($record->affiliateCorporate)[$field] : null);

        return Action::make('correct')
            ->label('Corregir datos')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Item $record): bool => $this->isEditable()
                && is_array($record->file_data)
                && $record->affiliate_corporate_id !== null
                && in_array($record->action, [Item::ACTION_REVIEW, Item::ACTION_MATCH, Item::ACTION_FIX_DOCUMENT, Item::ACTION_REACTIVATE], true))
            ->modalHeading(fn (Item $record): string => 'Corregir la ficha #'.$record->affiliate_corporate_id.' con el archivo')
            ->modalDescription('Elija el valor correcto de cada dato. Lo que deje distinto al archivo queda como «diferencia aceptada» con su nota. Al guardar, el sistema vuelve a comparar la ficha con la fila del archivo y le dice si ya cuadra. La tarifa no cambia.')
            ->modalIcon(Heroicon::OutlinedPencilSquare)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel('Guardar y verificar')
            ->fillForm(function (Item $record) use ($fields): array {
                $affiliate = $record->affiliateCorporate;
                abort_unless($affiliate instanceof AffiliateCorporate, 404);

                $comparison = Field::forPerson((array) $record->file_data, $affiliate);
                $data = ['expected' => PopulationItemCorrection::fingerprint($affiliate)];
                foreach ($fields as $field) {
                    $row = $comparison[$field];
                    $data[$field] = $row['counted'] && filled($row['file']) ? $row['file'] : $row['base'];
                    $data['file_'.$field] = $row['file'];
                    $data['base_'.$field] = $row['base'];
                }

                return $data;
            })
            ->schema([
                Placeholder::make('comparison')
                    ->hiddenLabel()
                    ->content(fn (Item $record): HtmlString => new HtmlString(view('filament.business.resources.affiliation-corporates.partials.population-item-fields', [
                        'fields' => $this->explanationFor($record)['fields'],
                        'caption' => 'Así está hoy',
                    ])->render())),
                SchemaActions::make([
                    Action::make('allFromFile')
                        ->label('Tomar todo del archivo')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->color('primary')
                        ->action(function (Get $get, Set $set) use ($fields): void {
                            foreach ($fields as $field) {
                                if (filled($get('file_'.$field))) {
                                    $set($field, $get('file_'.$field));
                                }
                            }
                        }),
                    Action::make('allFromBase')
                        ->label('Dejar todo como está en la base')
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->color('gray')
                        ->action(function (Get $get, Set $set) use ($fields): void {
                            foreach ($fields as $field) {
                                $set($field, $get('base_'.$field));
                            }
                        }),
                ]),
                Grid::make(2)->schema([
                    TextInput::make(Field::FIELD_FIRST_NAME)->label('Nombres')->required()->maxLength(100)->live(onBlur: true)
                        ->helperText($hint(Field::FIELD_FIRST_NAME))->hintAction($useFile(Field::FIELD_FIRST_NAME)),
                    TextInput::make(Field::FIELD_LAST_NAME)->label('Apellidos')->required()->maxLength(100)->live(onBlur: true)
                        ->helperText($hint(Field::FIELD_LAST_NAME))->hintAction($useFile(Field::FIELD_LAST_NAME)),
                    TextInput::make(Field::FIELD_DOCUMENT)->label('Cédula')->required()->maxLength(20)->live(onBlur: true)
                        ->regex('/^\s*[VEve]?[\s\-.]*[\d.]{1,12}(-\d{1,2}){0,2}\s*$/')
                        ->validationMessages(['regex' => 'Use solo números (adultos) o el formato de menor 012345678-12.'])
                        ->helperText($hint(Field::FIELD_DOCUMENT))->hintAction($useFile(Field::FIELD_DOCUMENT)),
                    DatePicker::make(Field::FIELD_BIRTH_DATE)->label('Fecha de nacimiento')->native(false)->displayFormat('d/m/Y')
                        ->minDate('1900-01-01')->maxDate(now())->live()
                        ->required(fn (Item $record): bool => filled($record->affiliateCorporate?->birth_date))
                        ->helperText($hint(Field::FIELD_BIRTH_DATE))->hintAction($useFile(Field::FIELD_BIRTH_DATE)),
                    Select::make(Field::FIELD_SEX)->label('Sexo')->options(['MASCULINO' => 'Masculino', 'FEMENINO' => 'Femenino'])->placeholder('Sin dato')->live()
                        ->helperText($hint(Field::FIELD_SEX))->hintAction($useFile(Field::FIELD_SEX)),
                    Select::make(Field::FIELD_RELATIONSHIP)->label('Parentesco')->options(array_combine(Field::RELATIONSHIPS, array_map(fn (string $value): string => ucfirst(mb_strtolower($value)), Field::RELATIONSHIPS)))->placeholder('Sin dato')->live()
                        ->helperText($hint(Field::FIELD_RELATIONSHIP))->hintAction($useFile(Field::FIELD_RELATIONSHIP)),
                ]),
                Placeholder::make('preview')
                    ->label('Así quedará')
                    ->content(fn (Get $get, Item $record): HtmlString => $this->correctionPreview($get, $record, $fields)),
                Checkbox::make('confirm_same')
                    ->label(fn (Item $record): string => 'Confirmo que la fila '.$record->file_row.' del archivo y la ficha #'.$record->affiliate_corporate_id.' son la misma persona.')
                    ->visible(fn (Item $record): bool => $record->action === Item::ACTION_REVIEW && ! ($record->details['confirmed_same_person'] ?? false))
                    ->accepted(fn (Item $record): bool => $record->action === Item::ACTION_REVIEW && ! ($record->details['confirmed_same_person'] ?? false))
                    ->validationMessages(['accepted' => 'Confírmelo para poder corregir la ficha. Si no es la misma persona, use «No es la misma persona».']),
                Textarea::make('note')
                    ->label('¿Por qué corrige estos datos?')
                    ->placeholder('Ej.: La cédula y la fecha se verificaron con la copia del documento enviada por la empresa.')
                    ->rows(2)
                    ->required()
                    ->minLength(PopulationItemCorrection::NOTE_MIN_LENGTH)
                    ->maxLength(1000),
                Hidden::make('expected'),
                ...array_merge(...array_map(fn (string $field): array => [Hidden::make('file_'.$field)->dehydrated(false), Hidden::make('base_'.$field)->dehydrated(false)], $fields)),
            ])
            ->action(function (array $data, Item $record, Action $action) use ($fields): void {
                $user = $this->guardRecord($record);

                $this->runCorrection($action, 'Ficha corregida', function () use ($data, $record, $user, $fields): string {
                    $result = PopulationItemCorrection::correct(
                        $record,
                        array_intersect_key($data, array_flip($fields)),
                        (string) ($data['note'] ?? ''),
                        $user,
                        (string) ($data['expected'] ?? ''),
                        (bool) ($data['confirm_same'] ?? false),
                    );

                    $parts = [$result['changed'] === [] ? 'No había datos que cambiar.' : 'Se guardó: '.implode(', ', array_map(fn (string $column): string => $this->columnLabel($column), $result['changed'])).'.'];
                    $parts[] = self::outcomeText($result['item']);
                    foreach ($result['patients'] as $patient) {
                        $parts[] = $patient['document_skipped']
                            ? 'El paciente de telemedicina #'.$patient['id'].' NO se actualizó: otra persona ya usa esa cédula en telemedicina.'
                            : 'Paciente de telemedicina #'.$patient['id'].' actualizado.';
                    }
                    array_push($parts, ...$result['warnings']);

                    return implode(' ', $parts);
                });
            });
    }

    /**
     * @param  list<string>  $fields
     */
    protected function correctionPreview(Get $get, Item $record, array $fields): HtmlString
    {
        $affiliate = $record->affiliateCorporate;
        if (! $affiliate instanceof AffiliateCorporate) {
            return new HtmlString('');
        }

        $values = [];
        foreach ($fields as $field) {
            $values[$field] = $get($field);
        }

        $clean = PopulationItemCorrection::normalize($values);
        $comparison = Field::compare(Field::fileValues((array) $record->file_data), $clean);
        $error = null;

        try {
            $warnings = PopulationItemCorrection::assertValid($clean, $affiliate, $this->owner());
        } catch (RuntimeException $exception) {
            $warnings = [];
            $error = $exception->getMessage();
        }

        return new HtmlString(view('filament.business.resources.affiliation-corporates.partials.population-item-fields', [
            'fields' => array_values($comparison),
            'caption' => null,
            'error' => $error,
            'warnings' => $warnings,
            'preview' => true,
        ])->render());
    }

    protected function columnLabel(string $column): string
    {
        return match ($column) {
            'nro_identificacion' => 'cédula',
            'first_name' => 'nombres',
            'last_name' => 'apellidos',
            'birth_date' => 'fecha de nacimiento',
            'sex' => 'sexo',
            'relationship' => 'parentesco',
            default => $column,
        };
    }

    protected function notSamePersonAction(): Action
    {
        return Action::make('notSamePerson')
            ->label('No es la misma persona')
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('warning')
            ->visible(fn (Item $record): bool => $this->isEditable() && $record->action === Item::ACTION_REVIEW && $record->affiliate_corporate_id !== null && is_array($record->file_data))
            ->modalHeading('Separar: no es la misma persona')
            ->modalIcon(Heroicon::OutlinedUserMinus)
            ->modalIconColor('warning')
            ->modalDescription(fn (Item $record): string => 'La fila '.$record->file_row.' del archivo («'.($record->file_data['full_name'] ?? '').'») pasará a tratarse como persona nueva (se le busca plan y se revisa que no esté vigente en otra afiliación). La ficha #'.$record->affiliate_corporate_id.' («'.($record->details['base_name'] ?? '').'»), como no está en el archivo, se propondrá de baja. Nada se aplica hasta «Aprobar y aplicar».')
            ->schema([
                Textarea::make('note')
                    ->label('¿Cómo verificó que son personas distintas?')
                    ->placeholder('Ej.: Son hermanos: la empresa confirmó que la cédula del archivo es del titular.')
                    ->rows(2)->required()->minLength(PopulationItemCorrection::NOTE_MIN_LENGTH)->maxLength(1000),
            ])
            ->modalSubmitActionLabel('Separar y volver a evaluar')
            ->action(function (array $data, Item $record, Action $action): void {
                $user = $this->guardRecord($record);
                $this->runCorrection($action, 'Fila separada', fn (): string => self::outcomeText(PopulationItemCorrection::notSamePerson($record, (string) $data['note'], $user)).' La ficha que se le había emparejado quedó en «Dar de baja» (revísela en «Cambios propuestos»).');
            });
    }

    protected function resolveDuplicateAction(): Action
    {
        return Action::make('resolveDuplicate')
            ->label('Resolver duplicado')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->visible(fn (Item $record): bool => $this->isEditable() && $record->action === Item::ACTION_DUPLICATE_BASE)
            ->modalHeading('Resolver ficha duplicada')
            ->modalIcon(Heroicon::OutlinedDocumentDuplicate)
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalDescription(fn (Item $record): string => 'Se conserva la ficha #'.($record->details['original_id'] ?? '').' (la que está en el archivo) y esta copia #'.$record->affiliate_corporate_id.' se dará de baja al aplicar la carga (queda INACTIVO, no se borra). La columna «Archivo» es la ficha que se conserva; «Base», esta copia.')
            ->schema([
                Placeholder::make('comparison')
                    ->hiddenLabel()
                    ->content(fn (Item $record): HtmlString => new HtmlString(view('filament.business.resources.affiliation-corporates.partials.population-item-fields', [
                        'fields' => $this->explanationFor($record)['fields'],
                        'caption' => 'Ficha que se conserva frente a la copia',
                        'leftLabel' => 'Se conserva (#'.($record->details['original_id'] ?? '').')',
                        'rightLabel' => 'Copia (#'.$record->affiliate_corporate_id.')',
                    ])->render())),
                CheckboxList::make('copy_fields')
                    ->label('Datos que le faltan a la ficha que se conserva y se pueden tomar de la copia')
                    ->options(fn (Item $record): array => $this->copyableFields($record))
                    ->visible(fn (Item $record): bool => $this->copyableFields($record) !== [])
                    ->helperText('Solo se ofrecen los datos que la ficha que se conserva tiene vacíos. Se guardan en el acto, con auditoría.'),
                Textarea::make('note')
                    ->label('¿Cómo verificó que es la misma persona?')
                    ->placeholder('Ej.: Misma persona cargada dos veces; la cédula correcta es la del archivo.')
                    ->rows(2)->required()->minLength(PopulationItemCorrection::NOTE_MIN_LENGTH)->maxLength(1000),
            ])
            ->modalSubmitActionLabel('Conservar #original y dar de baja la copia')
            ->action(function (array $data, Item $record, Action $action): void {
                $user = $this->guardRecord($record);
                $this->runCorrection($action, 'Duplicado resuelto', function () use ($data, $record, $user): string {
                    $result = PopulationItemCorrection::resolveDuplicate($record, array_values((array) ($data['copy_fields'] ?? [])), (string) $data['note'], $user);

                    return 'La copia #'.$record->affiliate_corporate_id.' quedó incluida para darse de baja al aplicar.'
                        .($result['copied'] !== [] ? ' Se completó en la ficha que se conserva: '.mb_strtolower(implode(', ', array_map(Field::label(...), $result['copied']))).'.' : '');
                });
            });
    }

    /**
     * @return array<string, string>
     */
    protected function copyableFields(Item $record): array
    {
        $copy = $record->affiliateCorporate;
        $original = $this->originalFor($record);
        if (! $copy instanceof AffiliateCorporate || ! $original instanceof AffiliateCorporate) {
            return [];
        }

        $kept = Field::baseValues($original);
        $fromCopy = Field::baseValues($copy);
        $out = [];
        foreach ([Field::FIELD_BIRTH_DATE, Field::FIELD_SEX, Field::FIELD_RELATIONSHIP] as $field) {
            if (blank($kept[$field]) && filled($fromCopy[$field])) {
                $out[$field] = Field::label($field).': '.Field::display($field, $fromCopy[$field]);
            }
        }

        return $out;
    }

    protected function notDuplicateAction(): Action
    {
        return Action::make('notDuplicate')
            ->label('No es un duplicado')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->visible(fn (Item $record): bool => $this->isEditable() && $record->action === Item::ACTION_DUPLICATE_BASE)
            ->modalHeading('No es un duplicado')
            ->modalDescription(fn (Item $record): string => 'La ficha #'.$record->affiliate_corporate_id.' es otra persona. Como no aparece en el archivo (que es la población completa), pasará a «Dar de baja» como cualquier otra ausente. Si debe seguir activa, apague luego «Incluir».')
            ->schema([
                Textarea::make('note')->label('¿Cómo lo verificó?')->rows(2)->required()->minLength(PopulationItemCorrection::NOTE_MIN_LENGTH)->maxLength(1000),
            ])
            ->modalSubmitActionLabel('Confirmar')
            ->action(function (array $data, Item $record, Action $action): void {
                $user = $this->guardRecord($record);
                $this->runCorrection($action, 'Reclasificado', fn (): string => self::outcomeText(PopulationItemCorrection::notDuplicate($record, (string) $data['note'], $user)));
            });
    }

    protected function markReviewedAction(): Action
    {
        return Action::make('markReviewed')
            ->label('Marcar como revisado')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->visible(fn (Item $record): bool => $this->isEditable() && $record->action === Item::ACTION_DUPLICATE_FILE && ! isset($record->details['reviewed']))
            ->modalHeading('Marcar como revisado')
            ->modalDescription('No cambia datos: deja constancia de que el analista revisó la fila repetida y qué concluyó.')
            ->schema([
                Textarea::make('note')->label('¿Qué concluyó?')->placeholder('Ej.: La empresa confirmó que está una sola vez; error del archivo.')->rows(2)->required()->minLength(PopulationItemCorrection::NOTE_MIN_LENGTH)->maxLength(1000),
            ])
            ->action(function (array $data, Item $record, Action $action): void {
                $user = $this->guardRecord($record);
                $this->runCorrection($action, 'Marcado como revisado', function () use ($data, $record, $user): ?string {
                    PopulationItemCorrection::markReviewed($record, (string) $data['note'], $user);

                    return null;
                });
            });
    }

    protected function reevaluateAction(): Action
    {
        return Action::make('reevaluate')
            ->label('Volver a evaluar')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (Item $record): bool => $this->isEditable() && $record->action === Item::ACTION_REVIEW && $record->affiliate_corporate_id === null && is_array($record->file_data))
            ->requiresConfirmation()
            ->modalHeading('Volver a evaluar esta fila')
            ->modalDescription('Se vuelve a buscar el plan por edad y se revisa de nuevo que la persona no esté vigente en otra afiliación. No cambia ninguna ficha.')
            ->modalSubmitActionLabel('Evaluar')
            ->action(function (Item $record, Action $action): void {
                $this->guardRecord($record);
                $this->runCorrection($action, 'Fila evaluada', fn (): string => self::outcomeText(PopulationItemCorrection::reevaluateOnly($record)));
            });
    }

    protected function undoCorrectionAction(): Action
    {
        return Action::make('undoCorrection')
            ->label('Deshacer última corrección')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (Item $record): bool => $this->isEditable() && PopulationItemCorrection::lastOpenCorrection((array) ($record->details['corrections'] ?? [])) !== null)
            ->modalHeading('Deshacer la última corrección')
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalIconColor('danger')
            ->modalDescription(function (Item $record): string {
                $corrections = (array) ($record->details['corrections'] ?? []);
                $last = $corrections[PopulationItemCorrection::lastOpenCorrection($corrections) ?? 0] ?? [];
                $changes = [];
                foreach ((array) ($last['after'] ?? []) as $column => $value) {
                    $changes[] = $this->columnLabel((string) $column).' vuelve a «'.($last['before'][$column] ?? '—').'»';
                }

                return 'La ficha vuelve a como estaba: '.implode('; ', $changes).'. Solo se deshace si nadie la cambió después.';
            })
            ->schema([
                Textarea::make('note')->label('¿Por qué la deshace?')->rows(2)->required()->minLength(PopulationItemCorrection::NOTE_MIN_LENGTH)->maxLength(1000),
            ])
            ->modalSubmitActionLabel('Deshacer')
            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
            ->action(function (array $data, Item $record, Action $action): void {
                $user = $this->guardRecord($record);
                $this->runCorrection($action, 'Corrección deshecha', fn (): string => self::outcomeText(PopulationItemCorrection::undoLast($record, (string) $data['note'], $user)));
            });
    }

    protected function syncWithFileBulkAction(): BulkAction
    {
        return BulkAction::make('syncWithFile')
            ->label('Sincronizar con el archivo')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->visible(fn (): bool => $this->isEditable() && $this->activeTab === self::TAB_MATCH_DIFFERENCES)
            ->modalHeading('Sincronizar las fichas seleccionadas con el archivo')
            ->modalIcon(Heroicon::OutlinedArrowDownTray)
            ->modalDescription('Para cada ficha seleccionada se toman del archivo los datos elegidos, solo donde no cuadran y el archivo los trae. Cada ficha pasa por las mismas validaciones que «Corregir datos» y se verifica después; si una falla, las demás siguen. Hasta '.PopulationItemCorrection::INLINE_LIMIT.' filas se hacen en el acto; más filas van en segundo plano y le llega un aviso al terminar.')
            ->schema([
                CheckboxList::make('groups')
                    ->label('Datos a tomar del archivo')
                    ->options([
                        'document' => 'Cédula',
                        'names' => 'Nombres y apellidos',
                        'birth_date' => 'Fecha de nacimiento',
                        'sex' => 'Sexo',
                        'relationship' => 'Parentesco',
                    ])
                    ->default(['document', 'names', 'birth_date'])
                    ->required()
                    ->columns(2),
                Textarea::make('note')
                    ->label('¿Por qué se toman del archivo?')
                    ->placeholder('Ej.: El archivo de la empresa del 04/11/2026 es la fuente oficial de cédulas y fechas.')
                    ->rows(2)->required()->minLength(PopulationItemCorrection::NOTE_MIN_LENGTH)->maxLength(1000),
            ])
            ->modalSubmitActionLabel('Sincronizar')
            ->action(function (array $data, Collection $records, BulkAction $action): void {
                abort_unless(static::canAccess() && $this->isEditable(), 403);
                $user = Auth::user();
                abort_unless($user instanceof User, 403);

                $records = $records->filter(fn (Item $record): bool => (int) $record->corporate_population_update_id === $this->updateId);
                $groups = array_values(array_intersect((array) ($data['groups'] ?? []), array_keys(PopulationItemCorrection::SYNC_GROUPS)));
                $busyKey = SyncCorporatePopulationItemsJob::busyKey($this->updateId);

                if (Cache::has($busyKey)) {
                    Notification::make()->title('Ya hay una sincronización en curso')->body('Espere el aviso de que terminó antes de lanzar otra.')->warning()->send();
                    $action->halt();

                    return;
                }

                if ($records->count() > PopulationItemCorrection::INLINE_LIMIT) {
                    if ($groups === [] || mb_strlen(trim((string) ($data['note'] ?? ''))) < PopulationItemCorrection::NOTE_MIN_LENGTH) {
                        Notification::make()->title('Faltan datos')->body('Elija qué datos tomar y explique el motivo.')->danger()->send();
                        $action->halt();

                        return;
                    }

                    Cache::put($busyKey, (int) $user->getKey(), now()->addMinutes(30));
                    SyncCorporatePopulationItemsJob::dispatch($this->updateId, $records->modelKeys(), $groups, trim((string) $data['note']), (int) $user->getKey());

                    Notification::make()
                        ->title('Sincronización en segundo plano')
                        ->body($records->count().' fichas en cola. Le llegará un aviso con el resultado; mientras tanto la carga no se puede aprobar.')
                        ->info()
                        ->send();

                    return;
                }

                try {
                    $result = PopulationItemCorrection::syncWithFile($records->values(), $groups, (string) ($data['note'] ?? ''), $user);
                } catch (RuntimeException $exception) {
                    Notification::make()->title('No se sincronizó')->body($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                $this->explanationMemo = [];
                $failed = collect($result['failed'])->take(10)->map(fn (array $row): string => $row['name'].': '.$row['error'])->implode(' · ');

                $notification = Notification::make()
                    ->title('Sincronización terminada')
                    ->body('Actualizadas: '.$result['updated'].' · Sin cambios: '.$result['unchanged'].' · Con error: '.count($result['failed']).($failed !== '' ? '. '.$failed : '.'))
                    ->persistent(count($result['failed']) > 0);

                (count($result['failed']) > 0 ? $notification->warning() : $notification->success())->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function detailText(Item $record): string
    {
        $parts = [(string) $record->reason];
        $details = (array) $record->details;

        if ($record->action === Item::ACTION_ADD && isset($details['fee'])) {
            $parts[] = 'Plan por edad'.(isset($details['age']) ? ' ('.$details['age'].' años)' : ' (sin fecha: plan único de la empresa)').' · tarifa anual US$ '.number_format((float) $details['fee'], 2, ',', '.');
        }

        if (isset($details['fix_document_to'])) {
            $parts[] = 'Cédula a guardar: '.$details['fix_document_to'];
        }

        if (isset($details['open_activity'])) {
            $parts[] = implode(' · ', (array) $details['open_activity']);
        }

        if (isset($record->result['rollback'])) {
            $parts[] = 'Reverso: '.$record->result['rollback'];
        }

        return trim(implode(' ', array_filter($parts)));
    }
}
