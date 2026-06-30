<?php

declare(strict_types=1);

namespace App\Support\GuiaChat;

/**
 * Opciones del menú de servicio del GUIA-CHAT público.
 */
final class ServiceMenuOption
{
    public const BUSINESS_ADVISOR = 'business_advisor';

    public const INTEGRACORP_LOGIN = 'integracorp_login';

    public const SERVICE_SUGGESTION = 'service_suggestion';

    public const GUIA_CHAT_BUG = 'guia_chat_bug';

    public const INTEGRACORP_BUG = 'integracorp_bug';

    public const FEEDBACK_STEP_REPORTER_NAME = 'reporter_name';

    public const FEEDBACK_STEP_MESSAGE = 'message';

    public static function requiresReporterName(string $mode): bool
    {
        return in_array($mode, [self::GUIA_CHAT_BUG, self::INTEGRACORP_BUG], true);
    }

    public static function initialFeedbackStep(string $mode): string
    {
        return self::requiresReporterName($mode)
            ? self::FEEDBACK_STEP_REPORTER_NAME
            : self::FEEDBACK_STEP_MESSAGE;
    }

    /**
     * @return array{first_name: string, last_name: string}
     */
    public static function parseReporterFullName(string $input): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $input) ?? '');

        if ($normalized === '') {
            return [
                'first_name' => '',
                'last_name' => '',
            ];
        }

        $parts = explode(' ', $normalized);

        if (count($parts) === 1) {
            return [
                'first_name' => $parts[0],
                'last_name' => '',
            ];
        }

        return [
            'first_name' => implode(' ', array_slice($parts, 0, -1)),
            'last_name' => (string) $parts[count($parts) - 1],
        ];
    }

    public static function reporterNameReprompt(): string
    {
        return 'Indica tu **nombre y apellido** en un solo mensaje (por ejemplo: Juan Pérez) y pulsa enviar.';
    }

    /**
     * @return list<array{
     *     key: string,
     *     action: string,
     *     label: string,
     *     description: string,
     *     accent: string,
     *     icon: string,
     *     highlight_brand?: bool
     * }>
     */
    public static function catalog(): array
    {
        return [
            [
                'key' => self::BUSINESS_ADVISOR,
                'action' => self::BUSINESS_ADVISOR,
                'label' => 'Comunicame con un Asesor de Negocios.',
                'description' => 'Atención comercial directa por WhatsApp o teléfono.',
                'accent' => 'from-emerald-500/35 to-teal-400/15',
                'icon' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z',
            ],
            [
                'key' => self::INTEGRACORP_LOGIN,
                'action' => self::INTEGRACORP_LOGIN,
                'label' => 'Login directo en INTEGRACORP.',
                'description' => 'Accede al portal según tu perfil.',
                'accent' => 'from-blue-500/35 to-cyan-400/15',
                'icon' => 'M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9',
            ],
            [
                'key' => self::SERVICE_SUGGESTION,
                'action' => self::SERVICE_SUGGESTION,
                'label' => 'Sugerencias para mejoras del servicio.',
                'description' => 'Comparte ideas para mejorar tu experiencia.',
                'accent' => 'from-violet-500/35 to-purple-400/15',
                'icon' => 'M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18',
            ],
            [
                'key' => self::GUIA_CHAT_BUG,
                'action' => self::GUIA_CHAT_BUG,
                'label' => 'Reportar fallas del GUIA-CHAT',
                'description' => 'Cuéntanos qué falló en el asistente.',
                'accent' => 'from-amber-500/35 to-orange-400/15',
                'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
                'highlight_brand' => true,
            ],
            [
                'key' => self::INTEGRACORP_BUG,
                'action' => self::INTEGRACORP_BUG,
                'label' => 'Reportar fallas del sistema INTEGRACORP.',
                'description' => 'Incidencias en portales o módulos internos.',
                'accent' => 'from-rose-500/35 to-red-400/15',
                'icon' => 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z',
            ],
        ];
    }

    /**
     * @return array{key: string, action: string, label: string, description: string, accent: string, icon: string, highlight_brand?: bool}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::catalog() as $option) {
            if ($option['key'] === $key) {
                return $option;
            }
        }

        return null;
    }

    public static function feedbackPrompt(string $mode, string $step = self::FEEDBACK_STEP_MESSAGE): string
    {
        if ($step === self::FEEDBACK_STEP_REPORTER_NAME) {
            return 'Para registrar tu reporte, indica tu **nombre y apellido** en un solo mensaje y pulsa enviar.';
        }

        return match ($mode) {
            self::SERVICE_SUGGESTION => 'Gracias por ayudarnos a mejorar. Escribe tu sugerencia con el mayor detalle posible y pulsa enviar.',
            self::GUIA_CHAT_BUG => 'Lamentamos la falla en **GUIA-CHAT**. Describe qué ocurrió, en qué paso y qué esperabas ver; luego pulsa enviar.',
            self::INTEGRACORP_BUG => 'Para reportar una falla del sistema **INTEGRACORP**, indica el módulo afectado, qué intentabas hacer y el mensaje de error si lo hubo; luego pulsa enviar.',
            default => 'Cuéntanos tu mensaje con detalle y pulsa enviar.',
        };
    }

    public static function feedbackAcknowledgement(string $mode): string
    {
        return match ($mode) {
            self::SERVICE_SUGGESTION => 'Recibimos tu sugerencia. Nuestro equipo la revisará para mejorar el servicio. ¡Gracias!',
            self::GUIA_CHAT_BUG => 'Recibimos tu reporte sobre **GUIA-CHAT**. Lo revisaremos a la brevedad. ¡Gracias!',
            self::INTEGRACORP_BUG => 'Recibimos tu reporte del sistema **INTEGRACORP**. Soporte lo revisará pronto. ¡Gracias!',
            default => 'Recibimos tu mensaje. ¡Gracias!',
        };
    }

    public static function draftPlaceholder(?string $mode, ?string $step = null): string
    {
        return match ($step) {
            self::FEEDBACK_STEP_REPORTER_NAME => 'Escribe tu nombre y apellido...',
            default => match ($mode) {
                self::SERVICE_SUGGESTION => 'Escribe tu sugerencia aquí...',
                self::GUIA_CHAT_BUG => 'Describe la falla del GUIA-CHAT...',
                self::INTEGRACORP_BUG => 'Describe la falla del sistema INTEGRACORP...',
                default => 'Pregunta lo que necesites...',
            },
        };
    }
}
