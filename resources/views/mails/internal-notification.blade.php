<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titleLine }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f5f7;">
    @php
        $logoPath = public_path('image/logoNewTDG.png');
        $logoSrc = isset($message) && file_exists($logoPath) ? $message->embed($logoPath) : asset('image/logoNewTDG.png');
    @endphp
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 640px; margin: 0 auto;">
        <tr>
            <td style="padding: 5px; background-color: #ffffff; border: 1px solid #e7e7e7; border-radius: 8px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="padding: 20px 10px;">
                            <img src="{{ $logoSrc }}" alt="INTEGRACORP" width="200" style="max-width: 200px; width: 100%; height: auto; display: block; margin: 0 auto; border: 0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 24px 8px; color: #052F60; font-size: 18px; font-weight: bold;">{{ $titleLine }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 24px 20px; color: #1f2937; font-size: 14px; line-height: 1.6;">{!! nl2br(e($bodyText)) !!}</td>
                    </tr>
                    @if ($isFallback)
                        <tr>
                            <td style="padding: 12px 24px 20px; color: #6b7280; font-size: 12px; border-top: 1px solid #e5e7eb;">
                                Recibe este aviso por correo porque no se pudo entregar por Telegram. Para recibirlo allí, vincule su Telegram en IntegraCorp → menú de usuario → «Conectar Telegram».
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
