<?php

declare(strict_types=1);

namespace App\Support\Telemedicine\PanelLog;

use App\Enums\TelemedicinePanelEventCategory as Category;
use App\Enums\TelemedicinePanelEventLevel as Level;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\EventBus;
use Livewire\Mechanisms\HandleComponents\ComponentContext;
use Throwable;
use WeakMap;

use function Livewire\after;
use function Livewire\on;
use function Livewire\store;

/**
 * Escucha al panel de Telemedicina desde los ganchos de Livewire y anota en la
 * bitácora lo que el médico hace y lo que le sale mal:
 *
 * - abre una pantalla;
 * - abre o confirma una acción, avanza de paso, pulsa «Registrar» / «Guardar»;
 * - un mensaje de validación lo frena (y si ese mensaje quedó sin campo donde
 *   mostrarse, lo marca: el médico no vio nada);
 * - el servidor falla o le muestra un aviso de error;
 * - pulsó y no pasó nada visible: ni éxito, ni error, ni aviso.
 *
 * El resultado de un clic se decide al deshidratar el componente, cuando ya se
 * sabe si hubo redirección, errores o avisos.
 */
final class TelemedicinePanelLivewireMonitor
{
    /** Métodos de guardado de las páginas de Filament. */
    private const SUBMIT_METHODS = ['create', 'save'];

    /**
     * Clics pendientes de resultado, por id de componente.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $pending = [];

    /**
     * Bus de eventos donde ya se conectaron los ganchos. Se compara por instancia:
     * una aplicación nueva (tests, workers) trae su propio bus.
     */
    private static ?WeakMap $registeredOnBus = null;

    public static function register(): void
    {
        $bus = app(EventBus::class);
        self::$registeredOnBus ??= new WeakMap;

        if (isset(self::$registeredOnBus[$bus])) {
            return;
        }

        self::$registeredOnBus[$bus] = true;

        on('call', static function (mixed $component, string $method, array $params): void {
            self::safely(static fn () => self::onCall($component, $method, $params));
        });

        on('exception', static function (mixed $target, Throwable $exception): void {
            self::safely(static fn () => self::onException($target, $exception));
        });

        after('dehydrate', static function (mixed $component, mixed $context): void {
            self::safely(static fn () => self::onDehydrate($component, $context));
        });
    }

    /**
     * @param  array<int, mixed>  $params
     */
    public static function onCall(mixed $component, string $method, array $params): void
    {
        if (! $component instanceof Component || ! TelemedicinePanelLog::current()->inScope()) {
            return;
        }

        $tracked = match (true) {
            in_array($method, self::SUBMIT_METHODS, true) => [
                'kind' => 'submit',
                'action' => $method,
                'label' => $method === 'create' ? 'Registrar' : 'Guardar',
            ],
            $method === 'mountAction' => [
                'kind' => 'mount_action',
                'action' => Str::limit((string) ($params[0] ?? ''), 115, ''),
                'label' => null,
            ],
            $method === 'callMountedAction' => [
                'kind' => 'call_action',
                'action' => Str::limit(self::mountedActionName($component) ?? '', 115, ''),
                'label' => self::mountedActionLabel($component),
            ],
            $method === 'callSchemaComponentMethod' && ($params[1] ?? null) === 'nextStep' => [
                'kind' => 'next_step',
                'action' => 'nextStep',
                'label' => null,
                'step' => self::wizardStepLabel($component, (string) ($params[0] ?? ''), self::stepIndex($params[2] ?? null)),
            ],
            default => null,
        };

        if ($tracked === null) {
            return;
        }

        $notifications = session()->get('filament.notifications', []);

        self::$pending[$component->getId()] = $tracked + [
            'started_at' => microtime(true),
            'notifications_before' => is_array($notifications) ? count($notifications) : 0,
            'was_busy' => $method === 'create' && property_exists($component, 'isCreating') && $component->isCreating === true,
            'exception' => null,
        ];
    }

    public static function onException(mixed $target, Throwable $exception): void
    {
        if (! $target instanceof Component) {
            return;
        }

        $log = TelemedicinePanelLog::current();

        if (! $log->inScope() || $log->exceptionWasHandled($exception)) {
            return;
        }

        $log->markExceptionHandled($exception);
        $pending = self::$pending[$target->getId()] ?? null;

        if ($pending !== null) {
            self::$pending[$target->getId()]['exception'] = $exception::class;
        }

        $subject = TelemedicinePanelSubject::fromComponent($target);
        $where = self::whereLabel($pending);

        if ($exception instanceof ValidationException) {
            $fields = self::validationFields($exception);
            $hidden = self::invisibleKeys($target, array_keys($fields));

            $log->record(
                Category::Validation,
                $hidden === [] ? Level::Warning : Level::Error,
                $hidden === [] ? 'validacion' : 'validacion_invisible',
                ($hidden === [] ? 'No pudo continuar' : 'Mensaje que el médico NO pudo ver').$where,
                $subject + self::callAttributes($target, $pending) + [
                    'message' => TelemedicinePanelLog::limitMessage(implode(' · ', array_map(
                        static fn (array $messages): string => implode(' ', $messages),
                        $fields,
                    ))),
                    'fields' => $fields,
                    'context' => array_filter([
                        'campos_sin_lugar_en_pantalla' => $hidden,
                    ]),
                ],
            );

            return;
        }

        $log->record(
            Category::Error,
            Level::Error,
            'error_servidor',
            'Error del servidor'.$where.': '.class_basename($exception),
            $subject + self::callAttributes($target, $pending) + [
                'message' => TelemedicinePanelLog::limitMessage($exception->getMessage()),
                'context' => TelemedicinePanelLog::exceptionContext($exception),
            ],
        );
    }

    public static function onDehydrate(mixed $component, mixed $context): void
    {
        if (! $component instanceof Component) {
            return;
        }

        $log = TelemedicinePanelLog::current();

        if (! $log->inScope()) {
            return;
        }

        if ($context instanceof ComponentContext && $context->mounting) {
            if ($component instanceof Page) {
                $log->record(Category::Navigation, Level::Info, 'pagina_abierta', 'Abrió la pantalla', TelemedicinePanelSubject::fromComponent($component) + [
                    'component' => Str::limit($component::class, 185, ''),
                    'livewire_id' => $component->getId(),
                ]);
            }

            return;
        }

        $pending = self::$pending[$component->getId()] ?? null;

        if ($pending === null) {
            return;
        }

        unset(self::$pending[$component->getId()]);

        self::resolveOutcome($log, $component, $pending);
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private static function resolveOutcome(TelemedicinePanelLog $log, Component $component, array $pending): void
    {
        $subject = TelemedicinePanelSubject::fromComponent($component);
        $attributes = $subject + self::callAttributes($component, $pending);
        $notifications = self::newNotifications((int) $pending['notifications_before']);
        $redirected = store($component)->has('redirect');
        $errorKeys = array_keys($component->getErrorBag()->toArray());
        $where = self::whereLabel($pending);

        foreach ($notifications as $notification) {
            $status = (string) ($notification['status'] ?? '');

            if (! in_array($status, ['danger', 'warning'], true)) {
                continue;
            }

            $log->record(
                $status === 'danger' ? Category::Error : Category::Validation,
                $status === 'danger' ? Level::Error : Level::Warning,
                $status === 'danger' ? 'aviso_error' : 'aviso_advertencia',
                'Aviso mostrado'.$where.': '.Str::limit((string) ($notification['title'] ?? 'sin título'), 150),
                $attributes + ['message' => TelemedicinePanelLog::limitMessage(strip_tags((string) ($notification['body'] ?? '')))],
            );
        }

        if ($pending['exception'] !== null) {
            return;
        }

        $sawSomething = $redirected || $notifications !== [] || $errorKeys !== [];

        match ($pending['kind']) {
            'submit' => $sawSomething
                ? self::recordSubmitResult($log, $component, $pending, $attributes, $redirected, $notifications, $errorKeys)
                : $log->record(
                    Category::NoResponse,
                    Level::Error,
                    'sin_respuesta',
                    'Pulsó «'.$pending['label'].'» y no pasó nada visible',
                    $attributes + [
                        'message' => $pending['was_busy']
                            ? 'El formulario seguía marcado como «guardando» por un intento anterior que no terminó: Filament ignora cada clic nuevo hasta recargar la página.'
                            : 'Sin redirección, sin errores en pantalla y sin avisos. Revise los eventos anteriores de este formulario.',
                        'context' => ['formulario_ocupado' => $pending['was_busy']],
                    ],
                ),
            'call_action' => self::recordActionResult($log, $component, $pending, $attributes, $sawSomething),
            'mount_action' => $log->record(Category::Action, Level::Info, 'accion_abierta', 'Abrió «'.(self::mountedActionLabel($component) ?? $pending['action']).'»', $attributes),
            'next_step' => $errorKeys === []
                ? $log->record(Category::Action, Level::Info, 'paso_siguiente', 'Avanzó'.$where, $attributes)
                : null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $pending
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $notifications
     * @param  list<string>  $errorKeys
     */
    private static function recordSubmitResult(
        TelemedicinePanelLog $log,
        Component $component,
        array $pending,
        array $attributes,
        bool $redirected,
        array $notifications,
        array $errorKeys,
    ): void {
        $failed = $errorKeys !== [] || collect($notifications)->contains(fn (array $n): bool => ($n['status'] ?? null) === 'danger');

        if ($failed) {
            if ($errorKeys !== []) {
                $log->record(Category::Validation, Level::Warning, 'validacion', 'No pudo guardar: hay mensajes en el formulario', $attributes + [
                    'fields' => array_map(static fn (mixed $messages): array => array_values((array) $messages), $component->getErrorBag()->toArray()),
                ]);
            }

            return;
        }

        $log->record(Category::Action, Level::Info, 'guardado', 'Pulsó «'.$pending['label'].'» y se guardó', $attributes + [
            'context' => array_filter([
                'redirigido' => $redirected,
                'avisos' => array_values(array_filter(array_map(static fn (array $n): ?string => isset($n['title']) ? (string) $n['title'] : null, $notifications))),
                'duracion_ms' => (int) round((microtime(true) - (float) $pending['started_at']) * 1000),
            ]),
        ]);
    }

    /**
     * Una acción que se ejecutó bien cierra su ventana. Si la ventana sigue
     * abierta, sin errores ni avisos, el médico pulsó y no vio nada.
     *
     * @param  array<string, mixed>  $pending
     * @param  array<string, mixed>  $attributes
     */
    private static function recordActionResult(TelemedicinePanelLog $log, Component $component, array $pending, array $attributes, bool $sawSomething): void
    {
        $label = $pending['label'] ?? $pending['action'];
        $stillOpen = self::mountedActionName($component) === $pending['action'] && $pending['action'] !== '';

        if (! $sawSomething && $stillOpen) {
            $log->record(Category::NoResponse, Level::Warning, 'sin_respuesta', 'Confirmó «'.$label.'» y la ventana siguió abierta sin mensaje', $attributes + [
                'message' => 'La acción no se ejecutó ni mostró un error: puede estar deshabilitada, sin permiso o detenida por el código sin aviso.',
            ]);

            return;
        }

        $log->record(Category::Action, Level::Info, 'accion_ejecutada', 'Ejecutó «'.$label.'»', $attributes);
    }

    /**
     * @param  array<string, mixed>|null  $pending
     * @return array<string, mixed>
     */
    private static function callAttributes(Component $component, ?array $pending): array
    {
        return array_filter([
            'component' => Str::limit($component::class, 185, ''),
            'livewire_id' => $component->getId(),
            'action' => $pending['action'] ?? null,
            'step' => isset($pending['step']) ? Str::limit((string) $pending['step'], 115) : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>|null  $pending
     */
    private static function whereLabel(?array $pending): string
    {
        if ($pending === null) {
            return '';
        }

        return match ($pending['kind']) {
            'submit' => ' al pulsar «'.$pending['label'].'»',
            'next_step' => filled($pending['step'] ?? null) ? ' en el paso «'.$pending['step'].'»' : ' al avanzar de paso',
            'call_action' => ' en «'.($pending['label'] ?? $pending['action']).'»',
            default => '',
        };
    }

    /**
     * @return array<string, list<string>>
     */
    private static function validationFields(ValidationException $exception): array
    {
        $fields = [];

        foreach ($exception->errors() as $key => $messages) {
            $fields[Str::limit((string) $key, 150, '')] = array_values(array_map(
                static fn (mixed $message): string => Str::limit((string) $message, 500),
                array_slice((array) $messages, 0, 5),
            ));

            if (count($fields) >= 30) {
                break;
            }
        }

        return $fields;
    }

    /**
     * Claves de error que ningún campo puede mostrar. Livewire solo conserva y
     * pinta los errores cuya clave empieza por una propiedad del componente
     * (`data.…`, `mountedActions.…`): un `telemedicine_service_list_id` a secas
     * se pierde y el médico no ve nada.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private static function invisibleKeys(Component $component, array $keys): array
    {
        return array_values(array_filter(
            $keys,
            static fn (string $key): bool => ! property_exists($component, Str::before($key, '.')),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function newNotifications(int $before): array
    {
        $all = session()->get('filament.notifications', []);

        if (! is_array($all)) {
            return [];
        }

        return array_values(array_filter(
            array_slice($all, $before),
            static fn (mixed $notification): bool => is_array($notification),
        ));
    }

    private static function mountedActionName(Component $component): ?string
    {
        if (! property_exists($component, 'mountedActions') || ! is_array($component->mountedActions) || $component->mountedActions === []) {
            return null;
        }

        $last = end($component->mountedActions);

        return is_array($last) && isset($last['name']) ? (string) $last['name'] : null;
    }

    private static function mountedActionLabel(Component $component): ?string
    {
        if (! method_exists($component, 'getMountedAction')) {
            return null;
        }

        try {
            $label = $component->getMountedAction()?->getLabel();
        } catch (Throwable) {
            return null;
        }

        return filled($label) ? Str::limit(strip_tags((string) $label), 110) : null;
    }

    private static function wizardStepLabel(Component $component, string $wizardKey, int $index): ?string
    {
        if ($index < 0 || ! method_exists($component, 'getSchemaComponent')) {
            return null;
        }

        try {
            $wizard = $component->getSchemaComponent($wizardKey);
            $steps = array_values($wizard?->getChildSchema()?->getComponents() ?? []);
            $label = isset($steps[$index]) && method_exists($steps[$index], 'getLabel') ? $steps[$index]->getLabel() : null;
        } catch (Throwable) {
            return null;
        }

        return filled($label) ? Str::limit(strip_tags((string) $label), 110) : null;
    }

    /**
     * El asistente manda `{ currentStepIndex: n }`.
     */
    private static function stepIndex(mixed $arguments): int
    {
        if (! is_array($arguments)) {
            return -1;
        }

        $index = $arguments['currentStepIndex'] ?? $arguments[0] ?? null;

        return is_numeric($index) ? (int) $index : -1;
    }

    /**
     * La bitácora nunca puede romper la pantalla del médico.
     */
    private static function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::warning('Bitácora de Telemedicina: fallo al observar Livewire.', ['exception' => $exception->getMessage()]);
        }
    }

    /**
     * Solo para tests: olvida los clics pendientes.
     */
    public static function flushPending(): void
    {
        self::$pending = [];
    }
}
