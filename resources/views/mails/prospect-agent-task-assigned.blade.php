<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva tarea de prospecto</title>
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
                                {{ config('app.name') }}
                            </p>
                            <h1 style="margin: 8px 0 0 0; font-size: 22px; font-weight: 700; color: #1c1c1e; letter-spacing: -0.02em;">
                                Nueva tarea de prospecto
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 24px 16px 24px;">
                            <p style="margin: 0; font-size: 15px; line-height: 1.5; color: #3a3a3c;">
                                Hola{{ filled($colaboradorName) ? ', '.$colaboradorName : '' }},
                            </p>
                            <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 1.5; color: #3a3a3c;">
                                <strong>{{ $assignedBy }}</strong> le asignó una tarea de captación. Revise el prospecto y la descripción para dar seguimiento.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 24px 16px 24px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: #f2f2f7; border-radius: 12px; border: 1px solid #e5e5ea;">
                                <tr>
                                    <td style="padding: 16px;">
                                        <p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 600; color: #8e8e93;">Tarea</p>
                                        <p style="margin: 0; font-size: 17px; font-weight: 700; color: #007aff; font-variant-numeric: tabular-nums;">
                                            N.º {{ $taskId }}
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 16px 16px 16px;">
                                        <p style="margin: 0 0 4px 0; font-size: 12px; font-weight: 600; color: #8e8e93;">Prospecto</p>
                                        <p style="margin: 0; font-size: 15px; color: #1c1c1e;">{{ $prospectName }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 16px 16px 16px;">
                                        <p style="margin: 0 0 4px 0; font-size: 12px; font-weight: 600; color: #8e8e93;">Asignada por</p>
                                        <p style="margin: 0; font-size: 15px; color: #1c1c1e;">{{ $assignedBy }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 16px 16px 16px;">
                                        <p style="margin: 0 0 4px 0; font-size: 12px; font-weight: 600; color: #8e8e93;">Fecha y hora</p>
                                        <p style="margin: 0; font-size: 15px; color: #1c1c1e;">{{ $assignedAt }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 0 16px 16px 16px;">
                                        <p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 600; color: #8e8e93;">Descripción</p>
                                        <p style="margin: 0; font-size: 15px; line-height: 1.45; color: #3a3a3c; white-space: pre-wrap;">{{ $taskDescription }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 24px 24px 24px;">
                            <a href="{{ $prospectUrl }}" style="display: inline-block; background: #28cd41; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 700; padding: 12px 20px; border-radius: 999px;">
                                Ver el prospecto
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 24px 24px 24px;">
                            <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #8e8e93;">
                                Este mensaje es automático. La misma tarea también aparece en la campana de {{ config('app.name') }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
