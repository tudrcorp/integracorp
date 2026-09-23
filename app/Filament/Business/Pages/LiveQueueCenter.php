<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Models\FailedJob;
use App\Support\LivePresence\ErrorTracker;
use App\Support\LivePresence\ExceptionFingerprint;
use App\Support\LivePresence\FailedJobActions;
use App\Support\LivePresence\FailedJobCatalog;
use App\Support\LivePresence\FailureDiagnosis;
use App\Support\LivePresence\LiveActivitySnapshot;
use App\Support\LivePresence\LivePresenceAccess;
use App\Support\LivePresence\OperationsAdvisor;
use App\Support\LivePresence\QueueActivityRecorder;
use App\Support\LivePresence\QueueJobActions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Colas y errores: donde se decide qué hacer con lo que falló.
 *
 * - Fallidos por causa: cada grupo con su diagnóstico y la acción recomendada.
 * - Todos los fallidos: tabla con filtros y acciones masivas.
 * - Errores del sistema: agrupados por huella, con la línea de nuestro código.
 * - Colas y workers: quién escucha cada cola, qué espera y cuánto tarda.
 *
 * Mismo acceso que el monitor en vivo: solo la lista blanca de correos.
 */
class LiveQueueCenter extends Page implements HasTable
{
    use InteractsWithTable;

    public const TABS = [
        'causas' => 'Fallidos por causa',
        'fallidos' => 'Todos los fallidos',
        'errores' => 'Errores del sistema',
        'colas' => 'Colas y workers',
    ];

    protected static ?string $navigationLabel = 'Colas y errores';

    protected static ?string $title = 'Colas y errores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?int $navigationSort = 100;

    protected static ?string $slug = 'colas-y-errores';

    protected string $view = 'filament.business.pages.live-queue-center';

    /** Registros por página en todas las listas de la pantalla. */
    public const PER_PAGE = 10;

    #[Url(as: 'tab')]
    public string $tab = 'causas';

    public int $groupsPage = 1;

    public int $errorsPage = 1;

    public static function canAccess(): bool
    {
        return LivePresenceAccess::allows(Auth::user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * Se revalida en cada petición de Livewire, no solo al entrar.
     */
    public function boot(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function mount(): void
    {
        $this->selectTab($this->tab);
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function selectTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'causas';
    }

    public function goToPage(string $list, int $page): void
    {
        if ($list === 'groups') {
            $this->groupsPage = max(1, $page);
        } elseif ($list === 'errors') {
            $this->errorsPage = max(1, $page);
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(FailedJob::query())
            ->defaultSort('failed_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(self::PER_PAGE)
            ->emptyStateHeading('No hay trabajos fallidos')
            ->emptyStateDescription('Las colas están limpias con estos filtros.')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->columns([
                TextColumn::make('failed_at')
                    ->label('Falló')
                    ->dateTime('d/m/Y H:i:s')
                    ->description(fn (FailedJob $record): string => $record->failed_at !== null ? LiveActivitySnapshot::ago(max(0, time() - $record->failed_at->getTimestamp())) : '')
                    ->sortable(),
                TextColumn::make('job')
                    ->label('Trabajo')
                    ->state(fn (FailedJob $record): string => class_basename(FailedJob::jobClassFromPayload((string) $record->payload)))
                    ->description(fn (FailedJob $record): string => 'cola '.$record->queue)
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('payload', 'like', '%'.self::escapeLike($search).'%')),
                TextColumn::make('diagnosis')
                    ->label('Diagnóstico')
                    ->state(fn (FailedJob $record): string => self::rowDiagnosis($record)['title'])
                    ->description(fn (FailedJob $record): string => self::rowDiagnosis($record)['category_label'])
                    ->badge()
                    ->color(fn (FailedJob $record): string => self::categoryColor(self::rowDiagnosis($record)['category']))
                    ->wrap(),
                TextColumn::make('exception')
                    ->label('Error')
                    ->state(fn (FailedJob $record): string => self::rowException($record)['short_class'].': '.self::rowException($record)['message'])
                    ->limit(140)
                    ->tooltip(fn (FailedJob $record): string => self::rowException($record)['message'])
                    ->description(fn (FailedJob $record): string => self::rowException($record)['origin'])
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('exception', 'like', '%'.self::escapeLike($search).'%')),
            ])
            ->filters([
                SelectFilter::make('queue')
                    ->label('Cola')
                    ->options(fn (): array => self::queueOptions()),
                SelectFilter::make('period')
                    ->label('Período')
                    ->options([
                        'hour' => 'Última hora',
                        'day' => 'Últimas 24 h',
                        'week' => 'Últimos 7 días',
                        'older' => 'Más de 30 días',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'hour' => $query->where('failed_at', '>=', now()->subHour()),
                        'day' => $query->where('failed_at', '>=', now()->subDay()),
                        'week' => $query->where('failed_at', '>=', now()->subDays(7)),
                        'older' => $query->where('failed_at', '<', now()->subDays(30)),
                        default => $query,
                    }),
                SelectFilter::make('job_class')
                    ->label('Trabajo')
                    ->options(fn (): array => self::jobOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('payload', 'like', '%"displayName":"'.self::escapeLike(str_replace('\\', '\\\\', (string) $data['value'])).'"%')
                        : $query),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Ver')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->color('gray')
                    ->slideOver()
                    ->modalWidth(Width::FourExtraLarge)
                    ->modalHeading(fn (FailedJob $record): string => 'Fallido: '.class_basename(FailedJob::jobClassFromPayload((string) $record->payload)))
                    ->modalContent(fn (FailedJob $record): View => self::failedDetailView((string) $record->uuid))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                Action::make('retry')
                    ->label('Reintentar')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Reintentar este trabajo')
                    ->modalDescription(fn (FailedJob $record): string => self::retryWarning(FailedJob::jobClassFromPayload((string) $record->payload), 1))
                    ->modalSubmitActionLabel('Reintentar')
                    ->action(fn (FailedJob $record) => self::notifyRetry(FailedJobActions::retry([(string) $record->uuid]))),
                Action::make('delete')
                    ->label('Eliminar')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar este fallido')
                    ->modalDescription('Se borra del registro de fallidos y ya no se podrá reintentar. El trabajo no se ejecuta.')
                    ->modalSubmitActionLabel('Eliminar')
                    ->action(fn (FailedJob $record) => self::notifyDeleted(FailedJobActions::delete([(string) $record->uuid]))),
            ])
            ->toolbarActions([
                BulkAction::make('retrySelected')
                    ->label('Reintentar seleccionados')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Reintentar los seleccionados')
                    ->modalDescription('Cada trabajo vuelve a su cola con los intentos en cero. Si alguno envía WhatsApp o correo y el envío llegó a salir antes de fallar, el destinatario lo recibirá dos veces.')
                    ->modalSubmitActionLabel('Reintentar')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => self::notifyRetry(FailedJobActions::retry($records->pluck('uuid')->map(fn ($uuid): string => (string) $uuid)->all()))),
                BulkAction::make('deleteSelected')
                    ->label('Eliminar seleccionados')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar los seleccionados')
                    ->modalDescription('Se borran del registro de fallidos y ya no se podrán reintentar.')
                    ->modalSubmitActionLabel('Eliminar')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => self::notifyDeleted(FailedJobActions::delete($records->pluck('uuid')->map(fn ($uuid): string => (string) $uuid)->all()))),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('openMonitor')
                ->label('Monitor en vivo')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->url(fn (): string => LiveActivityMonitor::getUrl()),
            Action::make('deleteOlder')
                ->label('Limpiar antiguos')
                ->icon(Heroicon::OutlinedArchiveBoxXMark)
                ->color('warning')
                ->modalHeading('Eliminar fallidos antiguos')
                ->modalDescription('Borra del registro los trabajos fallidos más viejos que los días indicados. Ya no se podrán reintentar. La limpieza automática diaria borra los de más de '.(int) config('live-presence.queues.prune_failed_days', 30).' días.')
                ->modalSubmitActionLabel('Eliminar')
                ->form([
                    TextInput::make('days')
                        ->label('Más viejos que (días)')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(3650)
                        ->default(30)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        self::notifyDeleted(FailedJobActions::deleteOlderThan((int) $data['days']));
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->warning()->title('No se eliminó nada')->body($exception->getMessage())->send();
                    }
                }),
            Action::make('deleteAll')
                ->label('Vaciar todo')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->modalIcon(Heroicon::OutlinedExclamationTriangle)
                ->modalIconColor('danger')
                ->modalHeading('Eliminar TODOS los trabajos fallidos')
                ->modalDescription(fn (): string => 'Se borran '.self::failedTotal().' trabajos fallidos. Ninguno se podrá reintentar después. Para confirmar, escriba ELIMINAR.')
                ->modalSubmitActionLabel('Eliminar todo')
                ->form([
                    TextInput::make('confirmation')
                        ->label('Escriba ELIMINAR')
                        ->required()
                        ->in(['ELIMINAR'])
                        ->validationMessages(['in' => 'Escriba ELIMINAR en mayúsculas para confirmar.']),
                ])
                ->action(fn () => self::notifyDeleted(FailedJobActions::deleteAll())),
        ];
    }

    public function viewFailedAction(): Action
    {
        return Action::make('viewFailed')
            ->label('Ver ejemplo')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->color('gray')
            ->size('sm')
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading('Detalle del fallido más reciente')
            ->modalContent(fn (array $arguments): View => self::failedDetailView((string) ($arguments['uuid'] ?? '')))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }

    public function retryGroupAction(): Action
    {
        return Action::make('retryGroup')
            ->label('Reintentar')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Reintentar '.self::groupCountLabel((string) ($arguments['fingerprint'] ?? '')))
            ->modalDescription(function (array $arguments): string {
                $group = FailedJobCatalog::group((string) ($arguments['fingerprint'] ?? ''));

                return $group === null
                    ? 'El grupo ya no existe.'
                    : self::retryWarning($group['job_class'], $group['count']).($group['diagnosis']['action'] !== FailureDiagnosis::ACTION_RETRY ? ' Ojo: el diagnóstico recomienda «'.$group['diagnosis']['action_label'].'»; si la causa no se corrigió, volverán a fallar.' : '');
            })
            ->modalSubmitActionLabel('Reintentar')
            ->action(fn (array $arguments) => self::notifyRetry(FailedJobActions::retryGroup((string) ($arguments['fingerprint'] ?? ''))));
    }

    public function deleteGroupAction(): Action
    {
        return Action::make('deleteGroup')
            ->label('Eliminar')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Eliminar '.self::groupCountLabel((string) ($arguments['fingerprint'] ?? '')))
            ->modalDescription('Se borran del registro de fallidos todos los trabajos de esta causa, también los anteriores a 7 días. Ya no se podrán reintentar.')
            ->modalSubmitActionLabel('Eliminar')
            ->action(fn (array $arguments) => self::notifyDeleted(FailedJobActions::deleteGroup((string) ($arguments['fingerprint'] ?? ''))));
    }

    public function viewErrorAction(): Action
    {
        return Action::make('viewError')
            ->label('Ver')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->color('gray')
            ->size('sm')
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading('Detalle del error')
            ->modalContent(fn (array $arguments): View => view('live-presence.partials.error-detail', [
                'error' => ($error = ErrorTracker::group((string) ($arguments['fingerprint'] ?? ''))),
                'copyText' => $error !== null ? ErrorTracker::copyText($error) : '',
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar');
    }

    public function resolveErrorAction(): Action
    {
        return Action::make('resolveError')
            ->label('Marcar resuelto')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Marcar el error como resuelto')
            ->modalDescription('Use esto después de desplegar la corrección. Si el error vuelve a ocurrir, se marcará como «Regresión» y llegará un aviso.')
            ->modalSubmitActionLabel('Marcar resuelto')
            ->action(function (array $arguments): void {
                ErrorTracker::resolve((string) ($arguments['fingerprint'] ?? ''), (string) (Auth::user()?->name ?? 'system'));
                Notification::make()->success()->title('Error marcado como resuelto')->body('Si vuelve a ocurrir, se avisará como regresión.')->send();
            });
    }

    public function reopenErrorAction(): Action
    {
        return Action::make('reopenError')
            ->label('Reabrir')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->size('sm')
            ->action(function (array $arguments): void {
                ErrorTracker::reopen((string) ($arguments['fingerprint'] ?? ''));
                Notification::make()->success()->title('Error reabierto')->send();
            });
    }

    public function forgetErrorAction(): Action
    {
        return Action::make('forgetError')
            ->label('Descartar')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Descartar este error')
            ->modalDescription('Sale de la lista y se borra su historial. Si vuelve a ocurrir, aparecerá como error nuevo.')
            ->modalSubmitActionLabel('Descartar')
            ->action(function (array $arguments): void {
                ErrorTracker::forget((string) ($arguments['fingerprint'] ?? ''));
                Notification::make()->success()->title('Error descartado')->send();
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $health = LiveActivitySnapshot::systemHealth();
        $report = $health['queue_report'] ?? null;
        $errors = ErrorTracker::groups();
        $groups = $this->tab === 'causas' ? FailedJobCatalog::groups() : [];
        $groupsPager = self::pager(count($groups), $this->groupsPage);
        $errorsPager = self::pager(count($errors), $this->errorsPage);
        $this->groupsPage = $groupsPager['page'];
        $this->errorsPage = $errorsPager['page'];

        return [
            'health' => $health,
            'report' => $report,
            'groups' => array_slice($groups, ($groupsPager['page'] - 1) * self::PER_PAGE, self::PER_PAGE),
            'groupsPager' => $groupsPager,
            'errors' => $errors,
            'errorsVisible' => array_slice($errors, ($errorsPager['page'] - 1) * self::PER_PAGE, self::PER_PAGE),
            'errorsPager' => $errorsPager,
            'canReleaseQueues' => QueueJobActions::isSupported(),
            'jobStats' => $this->tab === 'colas' ? QueueActivityRecorder::jobStats() : [],
            'advice' => OperationsAdvisor::advise(['level' => 'green', 'level_label' => '', 'reasons' => [], 'offenders' => []], $report, $errors),
            'tabs' => self::TABS,
            'refreshedAt' => now()->format('H:i:s'),
        ];
    }

    /**
     * Sacar trabajos de una cola atascada para que el resto fluya.
     */
    public function releaseQueueAction(): Action
    {
        return Action::make('releaseQueue')
            ->label('Liberar')
            ->icon(Heroicon::OutlinedBolt)
            ->color('warning')
            ->size('sm')
            ->modalIcon(Heroicon::OutlinedBolt)
            ->modalIconColor('warning')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalHeading(fn (array $arguments): string => 'Liberar la cola «'.self::argumentQueue($arguments).'»')
            ->modalDescription(function (array $arguments): HtmlString {
                $counts = QueueJobActions::counts(self::argumentQueue($arguments));
                $labels = QueueJobActions::scopeLabels();
                $lines = ['Saque de la cola lo que la está frenando para que el resto fluya. Trabajos que tocaría cada opción:'];

                foreach ($labels as $scope => $label) {
                    $lines[] = '• '.e($label).': <strong>'.$counts[$scope].'</strong>';
                }

                $lines[] = 'Recomendado: <strong>mover a fallidos</strong>. Salen de la cola pero se pueden reintentar después desde «Fallidos por causa».';

                return new HtmlString(implode('<br>', $lines));
            })
            ->modalSubmitActionLabel('Liberar cola')
            ->form(fn (array $arguments): array => [
                Select::make('scope')
                    ->label('Qué sacar')
                    ->options(QueueJobActions::scopeLabels())
                    ->default(QueueJobActions::SCOPE_STUCK)
                    ->native(false)
                    ->required(),
                Select::make('job_class')
                    ->label('Tipo de trabajo')
                    ->placeholder('Todos los tipos')
                    ->options(QueueJobActions::jobClasses(self::argumentQueue($arguments)))
                    ->helperText('Opcional: saque solo un tipo de trabajo, por ejemplo el que está trabando la cola.')
                    ->native(false),
                Radio::make('mode')
                    ->label('Qué hacer con ellos')
                    ->options([
                        QueueJobActions::MODE_MOVE => 'Mover a fallidos (se pueden reintentar después)',
                        QueueJobActions::MODE_DELETE => 'Eliminar definitivamente',
                    ])
                    ->default(QueueJobActions::MODE_MOVE)
                    ->live()
                    ->required(),
                Textarea::make('reason')
                    ->label('Motivo')
                    ->placeholder('Por ejemplo: la cola se trabó con envíos de WhatsApp mientras UltraMsg estaba caído.')
                    ->rows(2)
                    ->maxLength(500)
                    ->required(fn (Get $get): bool => $get('mode') === QueueJobActions::MODE_DELETE)
                    ->minLength(fn (Get $get): int => $get('mode') === QueueJobActions::MODE_DELETE ? 10 : 0)
                    ->validationMessages(['required' => 'Explique por qué se eliminan.', 'min' => 'Explique el motivo con al menos 10 caracteres.']),
                TextInput::make('confirmation')
                    ->label('Para eliminar, escriba el nombre de la cola')
                    ->placeholder(self::argumentQueue($arguments))
                    ->visible(fn (Get $get): bool => $get('mode') === QueueJobActions::MODE_DELETE)
                    ->required(fn (Get $get): bool => $get('mode') === QueueJobActions::MODE_DELETE)
                    ->in([self::argumentQueue($arguments)])
                    ->validationMessages(['in' => 'Escriba exactamente el nombre de la cola.']),
            ])
            ->action(function (array $arguments, array $data): void {
                try {
                    $result = QueueJobActions::release(
                        self::argumentQueue($arguments),
                        (string) $data['scope'],
                        (string) $data['mode'],
                        filled($data['job_class'] ?? null) ? (string) $data['job_class'] : null,
                        (string) ($data['reason'] ?? ''),
                        (string) (Auth::user()?->name ?? 'system'),
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->warning()->title('No se liberó la cola')->body($exception->getMessage())->send();

                    return;
                }

                if ($result['affected'] === 0) {
                    Notification::make()->warning()->title('No había trabajos con ese criterio')->body('Puede que un worker los haya tomado mientras tanto.')->send();

                    return;
                }

                Notification::make()->success()
                    ->title('Cola «'.self::argumentQueue($arguments).'» liberada')
                    ->body($result['moved'] > 0
                        ? $result['moved'].' '.($result['moved'] === 1 ? 'trabajo movido' : 'trabajos movidos').' a fallidos: puede reintentarlos desde «Fallidos por causa».'
                        : $result['deleted'].' '.($result['deleted'] === 1 ? 'trabajo eliminado' : 'trabajos eliminados').'.')
                    ->send();
            });
    }

    public static function categoryColor(string $category): string
    {
        return match ($category) {
            FailureDiagnosis::CATEGORY_PROVIDER, FailureDiagnosis::CATEGORY_NETWORK => 'warning',
            FailureDiagnosis::CATEGORY_CODE, FailureDiagnosis::CATEGORY_RESOURCES => 'danger',
            FailureDiagnosis::CATEGORY_CONFIG => 'info',
            FailureDiagnosis::CATEGORY_DATA => 'gray',
            default => 'gray',
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function argumentQueue(array $arguments): string
    {
        return trim((string) ($arguments['queue'] ?? ''));
    }

    /**
     * @return array{page: int, pages: int, total: int, from: int, to: int}
     */
    private static function pager(int $total, int $page): array
    {
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pages);

        return [
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'from' => $total === 0 ? 0 : ($page - 1) * self::PER_PAGE + 1,
            'to' => min($total, $page * self::PER_PAGE),
        ];
    }

    private static function failedDetailView(string $uuid): View
    {
        $detail = FailedJobCatalog::detail($uuid);

        return view('live-presence.partials.failed-job-detail', ['detail' => $detail]);
    }

    /**
     * @return array{class: string, short_class: string, message: string, location: string, origin: string}
     */
    private static function rowException(FailedJob $record): array
    {
        static $memo = [];

        return $memo[$record->getKey()] ??= (function () use ($record): array {
            $parsed = ExceptionFingerprint::fromReport(mb_substr((string) $record->exception, 0, 6000));

            return ['class' => $parsed['class'], 'short_class' => $parsed['short_class'], 'message' => $parsed['message'], 'location' => $parsed['location'], 'origin' => $parsed['origin'] ?: $parsed['location']];
        })();
    }

    /**
     * @return array{category: string, category_label: string, title: string, advice: string, action: string, action_label: string}
     */
    private static function rowDiagnosis(FailedJob $record): array
    {
        $exception = self::rowException($record);

        return FailureDiagnosis::diagnose($exception['class'], $exception['message']);
    }

    private static function retryWarning(string $jobClass, int $count): string
    {
        $base = ($count === 1 ? 'El trabajo vuelve' : 'Los '.$count.' trabajos vuelven').' a su cola original con los intentos en cero y se ejecutarán en cuanto un worker los tome.';

        return FailedJobCatalog::sendsMessages($jobClass)
            ? $base.' Este trabajo envía mensajes (WhatsApp, correo o webhook): si el envío llegó a salir antes de fallar, el destinatario lo recibirá dos veces.'
            : $base;
    }

    private static function groupCountLabel(string $fingerprint): string
    {
        $group = FailedJobCatalog::group($fingerprint);

        return $group === null ? 'este grupo' : $group['count'].' '.($group['count'] === 1 ? 'fallido' : 'fallidos').' de '.$group['job'];
    }

    /**
     * @param  array{requested: int, retried: int, pending: int}  $result
     */
    private static function notifyRetry(array $result): void
    {
        if ($result['requested'] === 0) {
            Notification::make()->warning()->title('No había nada que reintentar')->send();

            return;
        }

        if ($result['pending'] > 0) {
            Notification::make()->warning()
                ->title($result['retried'].' de '.$result['requested'].' devueltos a la cola')
                ->body($result['pending'].' no se pudieron reintentar y siguen en la lista. Revise el log del servidor.')
                ->send();

            return;
        }

        Notification::make()->success()
            ->title($result['retried'].' '.($result['retried'] === 1 ? 'trabajo devuelto' : 'trabajos devueltos').' a la cola')
            ->body('Si la causa sigue, volverán a aparecer aquí.')
            ->send();
    }

    private static function notifyDeleted(int $deleted): void
    {
        Notification::make()->success()
            ->title($deleted === 0 ? 'No había fallidos que eliminar' : $deleted.' '.($deleted === 1 ? 'fallido eliminado' : 'fallidos eliminados'))
            ->send();
    }

    private static function failedTotal(): int
    {
        try {
            return FailedJob::query()->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, string>
     */
    private static function queueOptions(): array
    {
        try {
            return FailedJob::query()->distinct()->orderBy('queue')->pluck('queue', 'queue')->map(fn ($queue): string => (string) $queue)->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    private static function jobOptions(): array
    {
        $options = [];

        foreach (FailedJobCatalog::groups() as $group) {
            $options[$group['job_class']] = $group['job'];
        }

        asort($options);

        return $options;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    public static function copyButton(string $text, string $label = 'Copiar diagnóstico'): HtmlString
    {
        return new HtmlString(view('live-presence.partials.copy-button', ['text' => $text, 'label' => $label])->render());
    }
}
