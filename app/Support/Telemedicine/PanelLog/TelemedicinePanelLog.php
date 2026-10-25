<?php

declare(strict_types=1);

namespace App\Support\Telemedicine\PanelLog;

use App\Enums\TelemedicinePanelEventCategory as Category;
use App\Enums\TelemedicinePanelEventLevel as Level;
use App\Models\TelemedicinePanelEvent;
use App\Models\User;
use App\Support\LivePresence\PageLabelResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Throwable;

/**
 * Bitácora del panel de Telemedicina: junta los eventos de la petición y los
 * escribe en un solo INSERT al terminar, después de responderle al médico.
 *
 * Nunca rompe la pantalla: cualquier fallo al registrar se traga y queda solo
 * en el log. No guarda el estado del formulario (datos clínicos): solo lo que
 * el médico vio (mensajes, avisos) y en qué caso, paso y servicio estaba.
 *
 * Instancia por petición (`scoped`): en un worker de colas no arrastra estado.
 */
final class TelemedicinePanelLog
{
    public const PANEL_PATH = 'telemedicina';

    /** Tope de eventos por petición: un bucle no puede inflar la tabla. */
    public const MAX_EVENTS_PER_REQUEST = 40;

    public const MESSAGE_LIMIT = 2000;

    /**
     * @var list<array<string, mixed>>
     */
    private array $buffer = [];

    private bool $flushRegistered = false;

    private ?string $requestId = null;

    /**
     * @var array<int, true>
     */
    private array $handledExceptions = [];

    private ?bool $inScope = null;

    /** Solo para tests: Livewire::test monta los componentes en una ruta propia. */
    private static ?bool $forcedScope = null;

    public static function current(): self
    {
        return app(self::class);
    }

    public static function isTelemedicinePath(?string $path): bool
    {
        $path = trim((string) parse_url((string) $path, PHP_URL_PATH), '/');

        return $path === self::PANEL_PATH || str_starts_with($path, self::PANEL_PATH.'/');
    }

    /**
     * La ruta que el médico ve en el navegador. En una petición de Livewire es la
     * de la página que contiene al componente, no `/livewire/update`.
     */
    public function pagePath(): string
    {
        try {
            $path = Livewire::isLivewireRequest()
                ? (string) Livewire::originalPath()
                : (string) request()->path();
        } catch (Throwable) {
            $path = (string) request()->path();
        }

        return '/'.ltrim((string) parse_url($path, PHP_URL_PATH), '/');
    }

    /**
     * En consola y en los workers la ruta es `/`: nunca entra.
     */
    public function inScope(): bool
    {
        return self::$forcedScope ?? ($this->inScope ??= self::isTelemedicinePath($this->pagePath()));
    }

    /**
     * Solo para tests: fuerza (true/false) o devuelve a la detección por ruta (null).
     */
    public static function forceScopeForTesting(?bool $inScope): void
    {
        self::$forcedScope = $inScope;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(Category $category, Level $level, string $event, string $title, array $attributes = []): void
    {
        try {
            if (count($this->buffer) >= self::MAX_EVENTS_PER_REQUEST) {
                return;
            }

            if (isset($attributes['url']) && ! isset($attributes['screen'])) {
                $attributes['screen'] = $this->screenLabel((string) $attributes['url']);
            }

            $this->buffer[] = array_merge($this->baseAttributes(), $attributes, [
                'occurred_at' => now()->format('Y-m-d H:i:s.v'),
                'level' => $level->value,
                'category' => $category->value,
                'event' => Str::limit($event, 40, ''),
                'title' => Str::limit($title, 250),
            ]);

            $this->registerFlush();
        } catch (Throwable $exception) {
            Log::warning('Bitácora de Telemedicina: no se pudo preparar el evento.', ['exception' => $exception->getMessage()]);
        }
    }

    public function markExceptionHandled(Throwable $exception): void
    {
        $this->handledExceptions[spl_object_id($exception)] = true;
    }

    public function exceptionWasHandled(Throwable $exception): bool
    {
        return isset($this->handledExceptions[spl_object_id($exception)]);
    }

    /**
     * Errores que llegan al manejador global y no pasaron por Livewire: una página
     * que no abre, un controlador del panel que falla.
     */
    public static function captureReportedException(Throwable $exception): void
    {
        try {
            if ($exception instanceof ValidationException) {
                return;
            }

            $log = self::current();

            if (! $log->inScope() || $log->exceptionWasHandled($exception)) {
                return;
            }

            $log->markExceptionHandled($exception);
            $log->record(Category::Error, Level::Error, 'error_servidor', 'Error del servidor: '.class_basename($exception), [
                'message' => self::limitMessage($exception->getMessage()),
                'context' => self::exceptionContext($exception),
            ]);
        } catch (Throwable) {
            // La bitácora nunca puede tapar el error original.
        }
    }

    /**
     * Escribe lo acumulado. Corre en `terminating`, después de enviar la respuesta.
     */
    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $rows = array_map(static function (array $row): array {
            foreach (['fields', 'context'] as $json) {
                $row[$json] = isset($row[$json]) && $row[$json] !== []
                    ? json_encode($row[$json], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR)
                    : null;
            }

            return $row;
        }, $this->buffer);

        $this->buffer = [];

        try {
            TelemedicinePanelEvent::query()->insert(self::normalizeRows($rows));
        } catch (Throwable $exception) {
            Log::warning('Bitácora de Telemedicina: no se pudieron guardar los eventos.', [
                'events' => count($rows),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pending(): array
    {
        return $this->buffer;
    }

    public static function limitMessage(?string $message): ?string
    {
        $message = trim((string) $message);

        return $message === '' ? null : Str::limit($message, self::MESSAGE_LIMIT);
    }

    /**
     * Dónde se originó el error en nuestro código, sin la traza completa.
     *
     * @return array<string, mixed>
     */
    public static function exceptionContext(Throwable $exception): array
    {
        $base = base_path().DIRECTORY_SEPARATOR;
        $origin = null;

        foreach ($exception->getTrace() as $frame) {
            $file = (string) ($frame['file'] ?? '');

            if ($file !== '' && str_starts_with($file, $base.'app') && ! str_contains($file, 'PanelLog')) {
                $origin = Str::after($file, $base).':'.($frame['line'] ?? '?');
                break;
            }
        }

        return array_filter([
            'exception' => $exception::class,
            'origin' => $origin ?? (str_starts_with($exception->getFile(), $base.'app')
                ? Str::after($exception->getFile(), $base).':'.$exception->getLine()
                : null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseAttributes(): array
    {
        $user = Auth::user();
        $path = $this->pagePath();

        return [
            'user_id' => $user instanceof User ? $user->getKey() : null,
            'user_name' => $user instanceof User ? Str::limit((string) $user->name, 145) : null,
            'telemedicine_doctor_id' => $user instanceof User && filled($user->doctor_id) ? (int) $user->doctor_id : null,
            'url' => Str::limit($path, 495, ''),
            'screen' => $this->screenLabel($path),
            'request_id' => $this->requestId ??= (string) Str::uuid(),
            'ip' => Str::limit((string) request()->ip(), 45, ''),
            'user_agent' => Str::limit((string) request()->userAgent(), 250, ''),
        ];
    }

    private function screenLabel(string $path): ?string
    {
        try {
            $label = PageLabelResolver::label($path);
        } catch (Throwable) {
            $label = null;
        }

        return $label === null ? null : Str::limit($label, 185);
    }

    private function registerFlush(): void
    {
        if ($this->flushRegistered) {
            return;
        }

        $this->flushRegistered = true;

        app()->terminating(function (): void {
            $this->flush();
        });
    }

    /**
     * El INSERT múltiple exige las mismas columnas en cada fila.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function normalizeRows(array $rows): array
    {
        $columns = (new TelemedicinePanelEvent)->getFillable();
        $normalized = [];

        foreach ($rows as $row) {
            $clean = [];

            foreach ($columns as $column) {
                $clean[$column] = $row[$column] ?? null;
            }

            $normalized[] = $clean;
        }

        return $normalized;
    }
}
