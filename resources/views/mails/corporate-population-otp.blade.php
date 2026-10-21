<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clave OTP de población corporativa</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f5f7;">
    @php
        $logoPath = public_path('image/logoNewPdf.png');
        if (! file_exists($logoPath)) {
            $logoPath = public_path('image/logoNewTDG.png');
        }
        $logoSrc = isset($message) && file_exists($logoPath)
            ? $message->embed($logoPath)
            : asset('image/logoNewPdf.png');
    @endphp
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 640px; margin: 0 auto;">
        <tr>
            <td style="padding: 5px; background-color: #ffffff; border: 1px solid #e7e7e7; border-radius: 8px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="padding: 20px 10px;">
                            <img src="{{ $logoSrc }}" alt="INTEGRACORP" width="220" style="max-width: 220px; width: 100%; height: auto; display: block; margin: 0 auto; border: 0; border-radius: 8px;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 20px; color: #333333; font-size: 14px; line-height: 1.6;">
                            <h2 style="margin: 0 0 8px; color: #1f2937; font-size: 18px;">{{ $purpose_label ?? 'Actualización de población' }}</h2>
                            <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">Generada el {{ $generatedAt ?? '—' }}</p>
                            <p style="margin: 0 0 16px;">Dicte esta clave solo al analista. Vence en {{ $ttl_minutes ?? 5 }} minutos y es de un solo uso.</p>

                            <p style="margin: 0 0 20px; padding: 16px; text-align: center; font-size: 32px; letter-spacing: 8px; font-weight: bold; background: #f8fafc; border: 1px dashed #052F60; color: #052F60;">
                                {{ $otp_code ?? '------' }}
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Empresa</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{{ $company ?? '—' }} · {{ $affiliation_code ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Analista</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{{ $analyst_name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Archivo</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{{ $file_name ?? '—' }}</td>
                                </tr>
                                @foreach (($counts ?? []) as $label => $count)
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">{{ $label }}</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{{ $count }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    @include('mails.partials.tdg-corporate-footer')
</body>
</html>
