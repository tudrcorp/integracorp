<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Pages;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Forms\Components\OtpBoxesInput;
use App\Jobs\AnalyzeCorporatePopulationUpdateJob;
use App\Jobs\ApplyCorporatePopulationUpdateJob;
use App\Jobs\RollbackCorporatePopulationUpdateJob;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\CorporatePopulationOtpChallenge;
use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem as Item;
use App\Models\User;
use App\Support\AffiliationCorporates\Population\CorporatePopulationOtp;
use App\Support\AffiliationCorporates\Population\PopulationUpdateAnalyzer;
use App\Support\AffiliationCorporates\Population\PopulationUpdateApplier;
use App\Support\AffiliationCorporates\Population\PopulationUpdateRollback;
use App\Support\AffiliationCorporates\Population\PopulationUpdateUrls;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
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
        foreach ([Item::ACTION_ADD, Item::ACTION_REACTIVATE, Item::ACTION_DEACTIVATE, Item::ACTION_FIX_DOCUMENT, Item::ACTION_DUPLICATE_BASE, Item::ACTION_REVIEW, Item::ACTION_BLOCKED_OPEN_CASE, Item::ACTION_DUPLICATE_FILE, Item::ACTION_INVALID, Item::ACTION_MATCH] as $action) {
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
                    ->modalDescription('Se compara de nuevo con los afiliados de hoy. Las casillas que haya cambiado vuelven a la propuesta del sistema.')
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
                OtpBoxesInput::make('otp_code')
                    ->label('Clave de 6 dígitos')
                    ->length(6)
                    ->visible(fn (): bool => filled($this->pendingApplyChallenge))
                    ->required(fn (): bool => filled($this->pendingApplyChallenge)),
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
                OtpBoxesInput::make('otp_code')
                    ->label('Clave de 6 dígitos')
                    ->length(6)
                    ->visible(fn (): bool => filled($this->pendingRollbackChallenge))
                    ->required(fn (): bool => filled($this->pendingRollbackChallenge)),
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
        if (! $challenge instanceof CorporatePopulationOtpChallenge
            || ! CorporatePopulationOtp::verifyAndConsume($challenge, (string) ($data['otp_code'] ?? ''), $user, $update, CorporatePopulationOtpChallenge::PURPOSE_APPLY)) {
            $this->otpFailed($challenge, CorporatePopulationOtpChallenge::PURPOSE_APPLY);
            $action->halt();

            return;
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
        if (! $challenge instanceof CorporatePopulationOtpChallenge
            || ! CorporatePopulationOtp::verifyAndConsume($challenge, (string) ($data['otp_code'] ?? ''), $user, $update, CorporatePopulationOtpChallenge::PURPOSE_ROLLBACK)) {
            $this->otpFailed($challenge, CorporatePopulationOtpChallenge::PURPOSE_ROLLBACK);
            $action->halt();

            return;
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

        Notification::make()
            ->title('Clave enviada')
            ->body('La OTP se envió a '.$issued['emails'].' correo(s) y '.$issued['phones'].' WhatsApp. Pídasela al autorizador y escríbala.')
            ->success()
            ->send();
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
                TextColumn::make('reason')
                    ->label('Detalle')
                    ->state(fn (Item $record): string => self::detailText($record))
                    ->wrap(),
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
            ->toolbarActions([
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
        } else {
            $query->where('action', $this->activeTab);
        }

        if ($search !== '') {
            $query->where(fn (Builder $q) => $q->where('file_data', 'like', '%'.$search.'%')->orWhere('details', 'like', '%'.$search.'%'));
        }

        return $query;
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
