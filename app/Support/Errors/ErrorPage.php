<?php

declare(strict_types=1);

namespace App\Support\Errors;

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Contenido de las páginas de error (resources/views/errors): qué le pasó al
 * usuario en español, cómo salir de ahí (atrás, inicio de su panel, reintentar)
 * y los datos para soporte. Todo es tolerante a fallos: una página de error no
 * puede romperse porque Filament, la sesión o la base no respondan.
 */
final class ErrorPage
{
    /** @var array<string, string> */
    private const PANEL_LABELS = [
        'admin' => 'Administrador',
        'business' => 'Negocios',
        'operations' => 'Operaciones',
        'administration' => 'Administración',
        'marketing' => 'Marketing',
        'agents' => 'Agentes',
        'master' => 'Agencia Master',
        'general' => 'Agencia General',
        'telemedicina' => 'Telemedicina',
        'projects' => 'Proyectos',
        'metrics' => 'Métricas',
    ];

    /**
     * Mensajes del framework en inglés: no se muestran, el texto propio ya lo explica.
     */
    private const FRAMEWORK_MESSAGE_PATTERN = '/^(this action is unauthorized|unauthenticated|too many (attempts|requests)|csrf token mismatch|the route .* could not be found|no query results for model|not found|forbidden|unauthorized|page expired|server error|service unavailable|the .* method is not supported)/i';

    public static function status(?Throwable $exception): int
    {
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

        return $status >= 400 && $status <= 599 ? $status : 500;
    }

    /**
     * @return array{title: string, message: string, hint: string, icon: string, tone: string, retry: bool}
     */
    public static function content(int $status): array
    {
        return match (true) {
            $status === 401 => [
                'title' => 'Necesitas iniciar sesión',
                'message' => 'Tu sesión no está activa o ya expiró.',
                'hint' => 'Inicia sesión de nuevo para continuar donde estabas.',
                'icon' => 'user',
                'tone' => 'info',
                'retry' => false,
            ],
            $status === 403 => [
                'title' => 'No tienes permiso para ver esto',
                'message' => 'Tu usuario no tiene el permiso necesario para abrir esta página o realizar esta acción.',
                'hint' => 'Si lo necesitas para tu trabajo, pide a tu supervisor o al equipo de Sistemas que te asigne el permiso y compárteles los datos de soporte de abajo.',
                'icon' => 'lock',
                'tone' => 'warning',
                'retry' => false,
            ],
            $status === 404 => [
                'title' => 'No encontramos lo que buscas',
                'message' => 'La página o el registro no existe, cambió de lugar o fue eliminado.',
                'hint' => 'Revisa el enlace o vuelve al listado para buscarlo de nuevo.',
                'icon' => 'search',
                'tone' => 'info',
                'retry' => false,
            ],
            $status === 405 => [
                'title' => 'Esta acción no está disponible',
                'message' => 'La página no admite la operación que se intentó realizar.',
                'hint' => 'Vuelve atrás y repite la acción desde el botón correspondiente.',
                'icon' => 'alert',
                'tone' => 'info',
                'retry' => false,
            ],
            $status === 419 => [
                'title' => 'Tu sesión expiró',
                'message' => 'Por seguridad, la página estuvo abierta mucho tiempo sin actividad y la solicitud no se pudo enviar.',
                'hint' => 'Recarga la página y vuelve a intentarlo. Si estabas llenando un formulario, revisa los datos antes de guardar.',
                'icon' => 'clock',
                'tone' => 'info',
                'retry' => true,
            ],
            $status === 429 => [
                'title' => 'Demasiados intentos',
                'message' => 'Se hicieron muchas solicitudes en poco tiempo.',
                'hint' => 'Espera un minuto y vuelve a intentarlo.',
                'icon' => 'clock',
                'tone' => 'warning',
                'retry' => true,
            ],
            $status === 503 => [
                'title' => 'Estamos en mantenimiento',
                'message' => 'IntegraCorp se está actualizando.',
                'hint' => 'Volverá a estar disponible en unos minutos. Intenta de nuevo en breve.',
                'icon' => 'wrench',
                'tone' => 'info',
                'retry' => true,
            ],
            $status >= 500 => [
                'title' => 'Algo salió mal de nuestro lado',
                'message' => 'Ocurrió un error inesperado y no pudimos completar la solicitud. El error quedó registrado para el equipo técnico.',
                'hint' => 'Vuelve a intentarlo en unos minutos. Si se repite, comparte los datos de soporte de abajo con Sistemas.',
                'icon' => 'alert',
                'tone' => 'danger',
                'retry' => true,
            ],
            default => [
                'title' => 'No pudimos procesar la solicitud',
                'message' => 'La solicitud no es válida o le faltan datos.',
                'hint' => 'Vuelve atrás y revisa la información antes de intentarlo de nuevo.',
                'icon' => 'alert',
                'tone' => 'warning',
                'retry' => false,
            ],
        };
    }

    /**
     * Nombre corto del error para tickets y reportes internos.
     */
    public static function shortLabel(int $status): string
    {
        return match (true) {
            $status === 401 => 'Sin sesión',
            $status === 403 => 'Sin permiso',
            $status === 404 => 'No encontrado',
            $status === 405 => 'Método no permitido',
            $status === 419 => 'Sesión expirada',
            $status === 429 => 'Demasiados intentos',
            $status === 503 => 'En mantenimiento',
            $status >= 500 => 'Error del servidor',
            default => 'Solicitud rechazada',
        };
    }

    /**
     * Mensaje propio del abort (p. ej. «Documento no encontrado.»). Solo en 4xx:
     * un 5xx puede traer detalles internos que el usuario no debe ver.
     */
    public static function detail(?Throwable $exception, int $status): ?string
    {
        if ($exception === null || $status >= 500) {
            return null;
        }

        $message = trim($exception->getMessage());

        if ($message === '' || mb_strlen($message) > 300 || preg_match(self::FRAMEWORK_MESSAGE_PATTERN, $message) === 1) {
            return null;
        }

        return $message;
    }

    /**
     * Inicio del panel en el que estaba el usuario según la URL; fuera de un panel, el sitio.
     *
     * @return array{url: string, label: string}
     */
    public static function home(Request $request): array
    {
        $panel = self::panel($request);

        if ($panel !== null) {
            return ['url' => url('/'.$panel['path']), 'label' => 'Ir al inicio de '.$panel['label']];
        }

        return ['url' => url('/'), 'label' => 'Ir al inicio'];
    }

    /**
     * Panel Filament al que pertenece la URL (por su primer segmento).
     *
     * @return array{id: string, path: string, label: string}|null
     */
    public static function panel(Request $request): ?array
    {
        $segment = mb_strtolower((string) $request->segment(1));

        try {
            foreach (Filament::getPanels() as $panel) {
                $path = trim((string) $panel->getPath(), '/');

                if ($path !== '' && $path === $segment) {
                    return ['id' => $panel->getId(), 'path' => $path, 'label' => self::panelLabel($panel->getId())];
                }
            }
        } catch (Throwable) {
            // Sin Filament disponible no hay panel.
        }

        return null;
    }

    public static function panelLabel(?string $panelId): string
    {
        return self::PANEL_LABELS[(string) $panelId] ?? 'mi panel';
    }

    /**
     * Errores que tiene sentido mandar a Sistemas. No: sesión (401/419), exceso
     * de intentos (429) ni mantenimiento (503), que no son fallas a corregir.
     */
    public static function reportable(int $status): bool
    {
        return ! in_array($status, [401, 419, 429, 503], true);
    }

    /**
     * A dónde vuelve «Volver atrás» cuando el navegador no tiene historial
     * (enlace abierto en una pestaña nueva): la página anterior si es de este
     * sitio y no es la misma que falló; si no, el inicio del panel.
     */
    public static function backUrl(Request $request, string $fallback): string
    {
        try {
            $previous = (string) url()->previous();
        } catch (Throwable) {
            $previous = '';
        }

        $sameHost = $previous !== '' && parse_url($previous, PHP_URL_HOST) === $request->getHost();

        return $sameHost && $previous !== $request->fullUrl() ? $previous : $fallback;
    }

    /**
     * «Reintentar»: un GET se recarga tal cual; un envío (POST de formulario,
     * sesión vencida) vuelve a la página anterior para no reenviar datos.
     */
    public static function retryUrl(Request $request, int $status, string $back): string
    {
        return $request->isMethod('GET') && $status !== 419 ? $request->fullUrl() : $back;
    }
}
