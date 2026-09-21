<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante cargado desde la PWA</title>
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
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto;">
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
                            <h2 style="margin: 0 0 6px; color: #1f2937; font-size: 18px;">Comprobante cargado desde la PWA</h2>
                            <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">Notificación generada el {{ $generatedAt }}</p>
                            <p style="margin: 0 0 16px; color: #555555;">
                                El comprobante de la cotización nro. <strong>{{ $quoteCode }}</strong> fue cargado por el usuario
                                <strong>{{ $userName }}</strong> desde el canal <strong>PWA</strong>.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold; color: #111827;">Cotización</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Código</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $quoteCode }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Titular</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $quoteClient }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Teléfono</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $quotePhone }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Correo</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $quoteEmail }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Estado</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $quoteStatus }}</td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold; color: #111827;">Usuario PWA</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Nombre</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $userName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Cédula</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $userIdNumber }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Correo</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $userEmail }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Teléfono</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $userPhone }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Canal</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $channel }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Cargado el</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $uploadedAt }}</td>
                                </tr>
                            </table>

                            <p style="margin: 0; color: #555555;">El comprobante va adjunto a este correo.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
