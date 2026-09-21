<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isUpdate ? 'Actualización de asistencia a oficina' : 'Asistencia a oficina' }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <table role="presentation" align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 640px; margin: 24px auto;">
        <tr>
            <td style="padding: 0 16px;">
                <table width="100%" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08); border: 1px solid #e5e5ea;">
                    <tr>
                        <td align="center" style="padding: 20px 24px 4px 24px;">
                            <img
                                src="{{ asset('image/logoNewPdf.png') }}"
                                alt="{{ config('app.name') }}"
                                width="220"
                                style="max-width: 220px; width: 100%; height: auto; display: block; margin: 0 auto; border: 0; outline: none; text-decoration: none;"
                            >
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 24px 8px 24px;">
                            <p style="margin: 0; font-size: 13px; font-weight: 600; color: #8e8e93; text-transform: uppercase; letter-spacing: 0.06em;">
                                Calendario TDG
                            </p>
                            <h1 style="margin: 8px 0 0 0; font-size: 22px; font-weight: 700; color: #1c1c1e; letter-spacing: -0.02em;">
                                {{ $isUpdate ? 'Actualización de tu asistencia a oficina' : 'Tu asistencia a oficina' }}
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 24px 16px 24px;">
                            <p style="margin: 0; font-size: 15px; line-height: 1.5; color: #3a3a3c;">
                                Hola{{ filled($colaboradorName) ? ', '.$colaboradorName : '' }},
                            </p>
                            <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 1.5; color: #3a3a3c;">
                                @if ($isUpdate)
                                    Se actualizó tu asistencia a oficina para <strong>{{ $monthLabel }}</strong>. Revisa las jornadas asignadas a continuación.
                                @else
                                    Te compartimos tu asistencia a oficina para <strong>{{ $monthLabel }}</strong>. Esta es la jornada que debes efectuar en cada día asignado.
                                @endif
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 24px 16px 24px;">
                            @if ($assignments === [])
                                <table width="100%" cellpadding="0" cellspacing="0" style="background: #f2f2f7; border-radius: 12px; border: 1px solid #e5e5ea;">
                                    <tr>
                                        <td style="padding: 16px;">
                                            <p style="margin: 0; font-size: 15px; line-height: 1.5; color: #3a3a3c;">
                                                No tienes jornadas de oficina asignadas para este período.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            @else
                                <table width="100%" cellpadding="0" cellspacing="0" style="background: #f2f2f7; border-radius: 12px; border: 1px solid #e5e5ea; overflow: hidden;">
                                    <tr>
                                        <td style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #8e8e93; text-transform: uppercase; letter-spacing: 0.04em;">
                                            Fecha
                                        </td>
                                        <td style="padding: 12px 16px; font-size: 12px; font-weight: 600; color: #8e8e93; text-transform: uppercase; letter-spacing: 0.04em;">
                                            Oficina
                                        </td>
                                    </tr>
                                    @foreach ($assignments as $assignment)
                                        <tr>
                                            <td style="padding: 10px 16px; border-top: 1px solid #e5e5ea; font-size: 15px; color: #1c1c1e;">
                                                {{ $assignment['weekday_label'] }}, {{ $assignment['date_label'] }}
                                            </td>
                                            <td style="padding: 10px 16px; border-top: 1px solid #e5e5ea; font-size: 15px; font-weight: 600; color: #007aff;">
                                                {{ $assignment['office_label'] }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 24px 24px 24px;">
                            <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #8e8e93;">
                                Este mensaje es automático. Si hay una modificación posterior del calendario, recibirás una actualización por correo.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
