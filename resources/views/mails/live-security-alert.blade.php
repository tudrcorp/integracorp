<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $event['title'] ?? 'Alerta de seguridad' }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f5f7;">
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto;">
        <tr>
            <td style="padding: 24px; background-color: #ffffff; border: 1px solid #e7e7e7; border-top: 4px solid #dc2626; border-radius: 8px;">
                <h2 style="margin: 0 0 6px; color: #b91c1c; font-size: 19px;">🚨 {{ $event['title'] ?? 'Alerta de seguridad' }}</h2>
                <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">Detectado el {{ date('d/m/Y H:i:s', (int) ($event['at'] ?? time())) }}</p>
                <p style="margin: 0 0 16px; color: #111827; font-size: 14px; line-height: 1.6;">{{ $event['detail'] ?? '' }}</p>

                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px; font-size: 13px;">
                    @foreach (['ip' => 'IP', 'account' => 'Cuenta', 'user_id' => 'Usuario (ID)', 'type' => 'Tipo'] as $field => $label)
                        @if (! empty($event[$field]))
                            <tr>
                                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">{{ $label }}</td>
                                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $event[$field] }}</td>
                            </tr>
                        @endif
                    @endforeach
                </table>

                <p style="margin: 0; color: #555555; font-size: 13px; line-height: 1.6;">
                    Revise el <strong>Monitor en vivo</strong> en INTEGRACORP → Negocios para ver el detalle en tiempo real.
                    Si una cuenta recibe muchos intentos fallidos, el sistema la bloquea temporalmente de forma automática.
                    Este aviso no se repetirá durante {{ (int) config('live-presence.security.alert_cooldown_minutes', 30) }} minutos para el mismo tipo de ataque.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
