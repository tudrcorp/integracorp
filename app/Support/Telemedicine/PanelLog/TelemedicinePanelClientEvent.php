<?php

declare(strict_types=1);

namespace App\Support\Telemedicine\PanelLog;

use App\Enums\TelemedicinePanelEventCategory as Category;
use App\Enums\TelemedicinePanelEventLevel as Level;
use Illuminate\Support\Str;

/**
 * Traduce lo que reporta el navegador a un evento de la bitácora.
 *
 * Son los fallos que el servidor nunca ve: la petición no llegó, la sesión
 * venció, el servidor respondió con error y Livewire lo tapó, o un script de la
 * página se rompió y el botón quedó sin efecto.
 */
final class TelemedicinePanelClientEvent
{
    public const REQUEST_FAILED = 'peticion_fallida';

    public const SESSION_EXPIRED = 'sesion_vencida';

    public const OFFLINE = 'sin_conexion';

    public const SCRIPT_ERROR = 'error_navegador';

    public const EVENTS = [self::REQUEST_FAILED, self::SESSION_EXPIRED, self::OFFLINE, self::SCRIPT_ERROR];

    /**
     * @param  array{event: string, status?: int|null, message?: string|null, source?: string|null, components?: list<string>|null, methods?: list<string>|null, online?: bool|null}  $data
     * @return array{title: string, level: Level, attributes: array<string, mixed>}
     */
    public static function describe(array $data): array
    {
        $status = isset($data['status']) ? (int) $data['status'] : null;
        $methods = array_values(array_filter((array) ($data['methods'] ?? [])));
        $doing = $methods === [] ? '' : ' al '.self::methodLabel($methods);

        [$title, $level, $explanation] = match ($data['event']) {
            self::SESSION_EXPIRED => [
                'La sesión venció'.$doing,
                Level::Error,
                'El navegador recibió 419: la página llevaba demasiado tiempo abierta o se cerró la sesión en otra pestaña. Lo que no se guardó se perdió.',
            ],
            self::OFFLINE => [
                'Sin conexión'.$doing,
                Level::Error,
                'La petición no llegó al servidor: el equipo del médico perdió la conexión a internet.',
            ],
            self::SCRIPT_ERROR => [
                'Error de la página en el navegador',
                Level::Warning,
                'Un script de la página falló. Si coincide con un botón que «no hace nada», esta es la causa.',
            ],
            default => [
                'El servidor respondió con error'.($status !== null ? ' '.$status : '').$doing,
                Level::Error,
                'La petición llegó pero el servidor respondió con error. Busque un «Error del servidor» a la misma hora.',
            ],
        };

        return [
            'title' => $title,
            'level' => $level,
            'attributes' => [
                'message' => TelemedicinePanelLog::limitMessage(trim($explanation.' '.($data['message'] ?? ''))),
                'action' => $methods === [] ? null : Str::limit(implode(', ', $methods), 115, ''),
                'component' => isset($data['components'][0]) ? Str::limit((string) $data['components'][0], 185, '') : null,
                'context' => array_filter([
                    'estado_http' => $status,
                    'componentes' => array_values((array) ($data['components'] ?? [])) ?: null,
                    'origen' => $data['source'] ?? null,
                    'en_linea' => $data['online'] ?? null,
                ], static fn (mixed $value): bool => $value !== null),
            ],
        ];
    }

    public static function category(): Category
    {
        return Category::Browser;
    }

    /**
     * @param  list<string>  $methods
     */
    private static function methodLabel(array $methods): string
    {
        return match (true) {
            in_array('create', $methods, true) => 'pulsar «Registrar»',
            in_array('save', $methods, true) => 'pulsar «Guardar»',
            in_array('callMountedAction', $methods, true) => 'confirmar una acción',
            in_array('mountAction', $methods, true) => 'abrir una acción',
            in_array('callSchemaComponentMethod', $methods, true) => 'avanzar de paso',
            default => 'interactuar con la pantalla',
        };
    }
}
