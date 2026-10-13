<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $carnetsOnly ? 'Carnet de afiliado' : 'Certificado de afiliación' }}</title>
</head>
<body style="margin:0; padding:0; background:#f3f6fb; font-family:Arial, Helvetica, sans-serif; color:#111111;">
    @php
        $logoPath = public_path('image/logoNewPdf.png');
        $logoSrc = isset($message) && file_exists($logoPath)
            ? $message->embed($logoPath)
            : asset('image/logoNewPdf.png');
        $document = $carnetsOnly
            ? ($carnetsCount === 1 ? 'el carnet de afiliado' : 'los '.$carnetsCount.' carnets de afiliado')
            : 'el certificado de afiliación'.($carnetsCount > 0 ? ' con '.($carnetsCount === 1 ? 'su carnet' : 'sus '.$carnetsCount.' carnets') : '');
        $plural = $carnetsOnly && $carnetsCount > 1;
    @endphp

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f6fb; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:620px; background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; overflow:hidden;">
                    <tr>
                        <td align="center" style="padding:28px 24px 18px 24px; background:linear-gradient(180deg, #f9fafb 0%, #ffffff 100%);">
                            <img src="{{ $logoSrc }}" alt="{{ config('app.name') }}" width="220" style="max-width:220px; width:100%; height:auto; display:block; margin:0 auto; border:0; outline:none; text-decoration:none;">
                        </td>
                    </tr>

                    <tr>
                        <td align="left" style="padding:10px 28px 24px 28px; color:#334155; font-size:15px; line-height:1.65;">
                            <p style="margin:0 0 12px 0; font-weight:700; color:#111111;">Estimado(a),</p>
                            <p style="margin:0 0 12px 0; color:#111111;">Gusto en saludarle.</p>

                            <p style="margin:0 0 12px 0;">
                                Le hacemos llegar {{ $document }} de Tu Doctor en Casa {{ $plural ? 'correspondientes' : 'correspondiente' }} a la afiliación <strong>{{ $affiliationCode }}</strong>{!! filled($planName) ? ', plan <strong>'.e($planName).'</strong>' : '' !!}.
                            </p>

                            @if (filled($analystMessage))
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0;">
                                    <tr>
                                        <td style="padding:12px 16px; background:#f1f5f9; border-left:4px solid #052F60; border-radius:8px; color:#111111; font-size:14px; line-height:1.6;">
                                            {!! nl2br(e($analystMessage)) !!}
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if ($downloadUrl)
                                <p style="margin:0 0 16px 0;">
                                    Por su tamaño, el documento no viaja adjunto: puede descargarlo con el siguiente botón{!! $linkExpiresAt ? ' hasta el <strong>'.e($linkExpiresAt).'</strong>' : '' !!}.
                                </p>
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px 0;">
                                    <tr>
                                        <td align="center" style="border-radius:999px; background:#052F60;">
                                            <a href="{{ $downloadUrl }}" style="display:inline-block; padding:12px 26px; color:#ffffff; font-weight:700; font-size:15px; text-decoration:none; border-radius:999px;">Descargar documento (PDF)</a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:0 0 12px 0; font-size:12px; color:#64748b;">Si el botón no funciona, copie este enlace en su navegador:<br><a href="{{ $downloadUrl }}" style="color:#2d89ca; word-break:break-all;">{{ $downloadUrl }}</a></p>
                            @else
                                <p style="margin:0 0 12px 0;">Encontrará el documento adjunto a este correo en formato PDF.</p>
                            @endif

                            <p style="margin:0 0 12px 0;">
                                {{ $carnetsOnly ? ($plural ? 'Le recomendamos conservar los carnets en su teléfono o impresos.' : 'Le recomendamos conservar el carnet en su teléfono o impreso.') : ($carnetsCount > 0 ? 'Le recomendamos conservar el certificado y los carnets en su teléfono o impresos.' : 'Le recomendamos conservar el certificado en su teléfono o impreso.') }}
                                Con el código QR podrá verificar la vigencia y acceder a los contactos de atención las 24 horas.
                            </p>

                            <p style="margin:0 0 8px 0; font-weight:700; color:#111111;">Departamento de Afiliaciones | Tu Doctor Group</p>
                            <p style="margin:0 0 8px 0; color:#111111;">WhatsApp: (+58) 424 222 0056 / 424 227 1498</p>
                            <p style="margin:0; color:#111111;">Email: <a href="mailto:afiliaciones@tudrencasa.com">afiliaciones@tudrencasa.com</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @include('mails.partials.tdg-corporate-footer')
</body>
</html>
