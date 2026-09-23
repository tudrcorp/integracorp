<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

/**
 * Traduce un error técnico a lo que el analista necesita para decidir: de
 * quién es la culpa, qué pasó en palabras de negocio y qué hacer.
 *
 * Es un catálogo de patrones conocidos, en orden: gana el primero que
 * coincide. Lo que no está catalogado se marca «Sin clasificar» y se revisa
 * con el detalle técnico. Sirve igual para trabajos fallidos y para errores.
 */
final class FailureDiagnosis
{
    public const CATEGORY_PROVIDER = 'provider';

    public const CATEGORY_NETWORK = 'network';

    public const CATEGORY_CONFIG = 'config';

    public const CATEGORY_CODE = 'code';

    public const CATEGORY_DATA = 'data';

    public const CATEGORY_RESOURCES = 'resources';

    public const CATEGORY_MANUAL = 'manual';

    public const CATEGORY_UNKNOWN = 'unknown';

    public const CATEGORY_LABELS = [
        self::CATEGORY_PROVIDER => 'Proveedor externo',
        self::CATEGORY_NETWORK => 'Red o conexión',
        self::CATEGORY_CONFIG => 'Configuración',
        self::CATEGORY_CODE => 'Nuestro código',
        self::CATEGORY_DATA => 'Datos que ya no existen',
        self::CATEGORY_RESOURCES => 'Recursos del servidor',
        self::CATEGORY_MANUAL => 'Retirado a mano',
        self::CATEGORY_UNKNOWN => 'Sin clasificar',
    ];

    /** Qué hacer con los trabajos fallidos del grupo. */
    public const ACTION_RETRY = 'retry';

    public const ACTION_FIX = 'fix';

    public const ACTION_CONFIG = 'config';

    public const ACTION_DELETE = 'delete';

    public const ACTION_REVIEW = 'review';

    public const ACTION_LABELS = [
        self::ACTION_RETRY => 'Reintentar',
        self::ACTION_FIX => 'Corregir y reintentar',
        self::ACTION_CONFIG => 'Revisar configuración',
        self::ACTION_DELETE => 'Eliminar',
        self::ACTION_REVIEW => 'Revisar el detalle',
    ];

    /**
     * @var list<array{pattern: string, category: string, title: string, advice: string, action: string}>
     */
    private const CATALOG = [
        [
            'pattern' => '/sacado de la cola a mano/i',
            'category' => self::CATEGORY_MANUAL,
            'title' => 'Sacado de la cola a mano para liberarla',
            'advice' => 'Se retiró para destrabar la cola. Cuando la cola esté sana y la causa corregida, reintente; si ya no hace falta, elimínelo.',
            'action' => self::ACTION_RETRY,
        ],
        [
            'pattern' => '/stopped due to non-payment|extending your subscription/i',
            'category' => self::CATEGORY_PROVIDER,
            'title' => 'WhatsApp (UltraMsg) suspendido por falta de pago',
            'advice' => 'Renueve la instancia de UltraMsg. Cuando vuelva a estar activa, reintente estos envíos.',
            'action' => self::ACTION_RETRY,
        ],
        [
            'pattern' => '/max length limit exceeded/i',
            'category' => self::CATEGORY_CODE,
            'title' => 'Mensaje de WhatsApp más largo de lo que acepta UltraMsg',
            'advice' => 'El texto se divide con WhatsAppMessageSplitter. Si estos fallos son anteriores a esa corrección, elimínelos; si siguen apareciendo, revise el trabajo que arma el mensaje.',
            'action' => self::ACTION_FIX,
        ],
        [
            'pattern' => '/daily user sending limit exceeded|5\.4\.5|sending quota|too many (emails|messages)/i',
            'category' => self::CATEGORY_PROVIDER,
            'title' => 'El correo alcanzó su límite diario de envío',
            'advice' => 'El proveedor de correo (Gmail) no acepta más envíos hoy. Espere a que se renueve el cupo (24 h) o cambie a un servidor SMTP con más capacidad; luego reintente.',
            'action' => self::ACTION_RETRY,
        ],
        [
            'pattern' => '/\b429\b|too many requests|rate.?limit/i',
            'category' => self::CATEGORY_PROVIDER,
            'title' => 'El proveedor frenó por exceso de peticiones',
            'advice' => 'Es pasajero: el servicio externo pidió bajar el ritmo. Reintente en unos minutos, por partes si son muchos.',
            'action' => self::ACTION_RETRY,
        ],
        [
            'pattern' => '/\bHTTP 5\d\d\b|respondió HTTP 5\d\d|\b50[234]\b.*(gateway|unavailable)|server error|service unavailable/i',
            'category' => self::CATEGORY_PROVIDER,
            'title' => 'El servicio externo tuvo un error de su lado',
            'advice' => 'El proveedor respondió con error 5xx. Confirme que ya se recuperó y reintente.',
            'action' => self::ACTION_RETRY,
        ],
        [
            'pattern' => '/class "?pusher\\\\pusher"? not found/i',
            'category' => self::CATEGORY_CONFIG,
            'title' => 'Notificaciones en vivo configuradas con Pusher, que no está instalado',
            'advice' => 'Las notificaciones del panel intentan transmitirse por Pusher. Ponga BROADCAST_CONNECTION=log (o null) en el .env y recargue la configuración. Estos fallidos se pueden eliminar: la notificación ya quedó guardada en el panel.',
            'action' => self::ACTION_CONFIG,
        ],
        [
            'pattern' => '/curl error (6|7|28|35)|could not resolve host|connection (refused|reset|timed out)|operation timed out|failed to connect|ssl (connect )?error|network is unreachable/i',
            'category' => self::CATEGORY_NETWORK,
            'title' => 'No hubo conexión con el servicio externo',
            'advice' => 'Falló la red o el servicio no respondió a tiempo. Si ya hay conexión, reintente.',
            'action' => self::ACTION_RETRY,
        ],
        [
            'pattern' => '/SQLSTATE\[(40001|HY000)\].*(deadlock|lock wait timeout)|deadlock found|lock wait timeout/i',
            'category' => self::CATEGORY_RESOURCES,
            'title' => 'La base de datos estaba ocupada (bloqueo)',
            'advice' => 'Dos procesos quisieron escribir lo mismo a la vez. Es pasajero: reintente.',
            'action' => self::ACTION_RETRY,
        ],
        [
            'pattern' => '/SQLSTATE\[HY000\] \[2002\]|server has gone away|too many connections/i',
            'category' => self::CATEGORY_RESOURCES,
            'title' => 'La base de datos no respondió',
            'advice' => 'MySQL estaba caído o saturado en ese momento. Verifique que responde y reintente.',
            'action' => self::ACTION_RETRY,
        ],
        [
            'pattern' => '/incomplete object|__PHP_Incomplete_Class/i',
            'category' => self::CATEGORY_CONFIG,
            'title' => 'Trabajo que no pertenece a esta aplicación (o a esta versión del código)',
            'advice' => 'La clase del trabajo no existe aquí: lo encoló otra aplicación que comparte la misma tabla de colas, o una versión vieja del código. No se puede ejecutar: elimínelo y verifique que cada aplicación use su propia base o tabla de colas.',
            'action' => self::ACTION_DELETE,
        ],
        [
            'pattern' => '/unable to open path|failed to open stream: no such file|file does not exist|filenotfoundexception/i',
            'category' => self::CATEGORY_DATA,
            'title' => 'El archivo que se iba a usar no existe en el servidor',
            'advice' => 'El documento (PDF, tarjeta, adjunto) no está en storage: se borró o nunca se generó. Regenérelo y reintente; si ya no hace falta, elimine el fallido.',
            'action' => self::ACTION_FIX,
        ],
        [
            'pattern' => '/no query results for model|modelnotfoundexception/i',
            'category' => self::CATEGORY_DATA,
            'title' => 'El registro que procesaba el trabajo ya no existe',
            'advice' => 'Se borró antes de que el trabajo corriera. Reintentar no sirve: elimine estos fallidos.',
            'action' => self::ACTION_DELETE,
        ],
        [
            'pattern' => '/allowed memory size|maximum execution time|out of memory/i',
            'category' => self::CATEGORY_RESOURCES,
            'title' => 'El trabajo se quedó sin memoria o sin tiempo',
            'advice' => 'Procesa demasiado de una vez. Hay que dividirlo en partes (chunks) o subir su límite; después reintente.',
            'action' => self::ACTION_FIX,
        ],
        [
            'pattern' => '/maxattemptsexceeded|attempted too many times|has timed out/i',
            'category' => self::CATEGORY_RESOURCES,
            'title' => 'Se agotaron los intentos (tardó demasiado o el worker se cayó)',
            'advice' => 'Compare el $timeout del trabajo con el retry_after de la conexión y revise si el worker se reinició a mitad. Después reintente.',
            'action' => self::ACTION_FIX,
        ],
        [
            'pattern' => '/SQLSTATE/i',
            'category' => self::CATEGORY_CODE,
            'title' => 'Consulta a la base de datos inválida',
            'advice' => 'La consulta no coincide con el esquema (columna, tabla o dato inválido). Corrija el código o la migración y después reintente.',
            'action' => self::ACTION_FIX,
        ],
        [
            'pattern' => '/class "[^"]+" not found|call to (undefined|a member function)|typeerror|argumenterror|undefined (array key|variable|property|method|index|offset)|cannot access|must be of type|division by zero|attempt to read property/i',
            'category' => self::CATEGORY_CODE,
            'title' => 'Error de programación',
            'advice' => 'El código falló con estos datos. Abra el detalle, vaya al archivo y la línea marcados, corrija y después reintente.',
            'action' => self::ACTION_FIX,
        ],
    ];

    /**
     * @return array{category: string, category_label: string, title: string, advice: string, action: string, action_label: string}
     */
    public static function diagnose(string $exceptionClass, string $message): array
    {
        $haystack = $exceptionClass.': '.$message;

        foreach (self::CATALOG as $entry) {
            if (preg_match($entry['pattern'], $haystack) === 1) {
                return self::present($entry['category'], $entry['title'], $entry['advice'], $entry['action']);
            }
        }

        return self::present(
            self::CATEGORY_UNKNOWN,
            'Error no catalogado',
            'Abra el detalle para ver el mensaje completo y la línea de nuestro código donde ocurrió.',
            self::ACTION_REVIEW,
        );
    }

    /**
     * Un grupo que no se repite hace días casi siempre ya se corrigió o dejó
     * de importar: se recomienda eliminarlo. Los de un proveedor se siguen
     * recomendando reintentar, salvo que envíen mensajes: un correo o WhatsApp
     * de hace semanas llegaría tarde y fuera de contexto.
     *
     * @param  array{category: string, category_label: string, title: string, advice: string, action: string, action_label: string}  $diagnosis
     * @return array{category: string, category_label: string, title: string, advice: string, action: string, action_label: string}
     */
    public static function forStaleGroup(array $diagnosis, int $daysSinceLast, int $staleAfterDays, bool $sendsMessages = false): array
    {
        if ($daysSinceLast < $staleAfterDays || $diagnosis['action'] === self::ACTION_DELETE) {
            return $diagnosis;
        }

        if ($diagnosis['action'] === self::ACTION_RETRY) {
            if (! $sendsMessages) {
                return $diagnosis;
            }

            return [
                ...$diagnosis,
                'advice' => 'Son envíos de hace '.$daysSinceLast.' días: reintentarlos ahora haría llegar mensajes viejos y fuera de contexto. Elimínelos, salvo que alguno todavía deba enviarse. '.$diagnosis['advice'],
                'action' => self::ACTION_DELETE,
                'action_label' => self::ACTION_LABELS[self::ACTION_DELETE],
            ];
        }

        return [
            ...$diagnosis,
            'advice' => 'No se repite desde hace '.$daysSinceLast.' días: lo más probable es que ya esté corregido. '.$diagnosis['advice'],
            'action' => self::ACTION_DELETE,
            'action_label' => self::ACTION_LABELS[self::ACTION_DELETE],
        ];
    }

    /**
     * @return array{category: string, category_label: string, title: string, advice: string, action: string, action_label: string}
     */
    private static function present(string $category, string $title, string $advice, string $action): array
    {
        return [
            'category' => $category,
            'category_label' => self::CATEGORY_LABELS[$category],
            'title' => $title,
            'advice' => $advice,
            'action' => $action,
            'action_label' => self::ACTION_LABELS[$action],
        ];
    }
}
