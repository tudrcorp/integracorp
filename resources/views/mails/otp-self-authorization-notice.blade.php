<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Clave OTP autoautorizada</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f5f7;">
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto;">
        <tr>
            <td style="padding: 24px; background-color: #ffffff; border: 1px solid #e7e7e7; border-top: 4px solid #d97706; border-radius: 8px;">
                <h2 style="margin: 0 0 6px; color: #92400e; font-size: 19px;">Clave OTP autoautorizada</h2>
                <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">{{ $notice['at'] }}</p>
                <p style="margin: 0 0 16px; color: #111827; font-size: 14px; line-height: 1.6;">
                    <strong>{{ $notice['user'] }}</strong> usó la clave que él mismo solicitó, sin esperar a que se la dictaran. Tiene permiso para hacerlo; este aviso es para que quede su control.
                </p>
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px; font-size: 13px;">
                    @foreach (['operation' => 'Operación', 'where' => 'Dónde', 'subject' => 'Detalle', 'reason' => 'Motivo'] as $field => $label)
                        @if (filled($notice[$field] ?? null))
                            <tr>
                                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 30%; color: #6b7280;">{{ $label }}</td>
                                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $notice[$field] }}</td>
                            </tr>
                        @endif
                    @endforeach
                </table>
                <p style="margin: 0; color: #555555; font-size: 13px; line-height: 1.6;">
                    No necesita hacer nada. Si no corresponde, revíselo en <strong>Negocios → Configuración → Claves OTP</strong> y retire el permiso desde el editor de permisos del usuario.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
