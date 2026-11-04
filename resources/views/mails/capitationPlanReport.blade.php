@php
    $logoUrl = rtrim((string) config('parameters.INTEGRACORP_URL'), '/').'/image/logoNewTDG.png';
    $visible = array_slice($planNames, 0, 15);
    $rest = count($planNames) - count($visible);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $documentTitle }}</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color:#f4f5f7;">
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px; margin:0 auto; padding:24px 12px;">
        <tr>
            <td style="background-color:#ffffff; border:1px solid #e7e7e7; border-radius:10px; overflow:hidden;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="padding:24px 20px 12px; border-bottom:3px solid #052F60;">
                            <img src="{{ $logoUrl }}" alt="Tu Dr Group" style="max-width:200px; height:auto;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px 8px; color:#1f2937;">
                            <h2 style="margin:0 0 14px; font-size:18px; color:#052F60;">{{ $documentTitle }}</h2>

                            @if (filled($customMessage))
                                <p style="margin:0 0 16px; font-size:14px; line-height:1.7; color:#374151;">{!! nl2br(e($customMessage)) !!}</p>
                            @endif

                            <p style="margin:0 0 10px; font-size:14px; line-height:1.7; color:#374151;">
                                Adjuntamos en PDF el detalle {{ count($planNames) === 1 ? 'del plan' : 'de los planes' }} de capitado:
                                costo por afiliado, frecuencia de pago, vigencia y beneficios incluidos.
                            </p>

                            <ul style="margin:0 0 18px; padding-left:18px; font-size:13px; line-height:1.7; color:#374151;">
                                @foreach ($visible as $name)
                                    <li>{{ $name }}</li>
                                @endforeach
                                @if ($rest > 0)
                                    <li>y {{ $rest }} {{ $rest === 1 ? 'plan más' : 'planes más' }}.</li>
                                @endif
                            </ul>

                            <p style="margin:0 0 20px; font-size:13px; line-height:1.7; color:#6b7280;">
                                Quedamos a su disposición para cualquier aclaratoria.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 28px; background-color:#f8fafc; border-top:1px solid #e5e7eb; font-size:11px; color:#9ca3af;">
                            Tu Dr Group · Este correo fue generado desde IntegraCorp.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
