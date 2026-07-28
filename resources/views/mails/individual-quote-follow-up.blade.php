<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $followUpLabel }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:20px 24px;background:#0f172a;color:#ffffff;">
                            <div style="font-size:12px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.8;">Tu Doctor en Casa</div>
                            <div style="margin-top:6px;font-size:20px;font-weight:700;">{{ $followUpLabel }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 12px;font-size:14px;color:#475569;">
                                Hola <strong>{{ $recipientName }}</strong>, {{ $audienceLabel }}
                            </p>
                            <div style="white-space:pre-wrap;font-size:14px;line-height:1.55;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
{{ $messageBody }}
                            </div>
                            @if (! empty($mediaFiles))
                                <div style="margin-top:16px;padding:14px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;">
                                    <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#1e3a8a;">
                                        Material multimedia (también adjunto a este correo):
                                    </p>
                                    <ul style="margin:0;padding-left:18px;font-size:13px;line-height:1.6;color:#1e40af;">
                                        @foreach ($mediaFiles as $mediaFile)
                                            <li>
                                                <a href="{{ $mediaFile['url'] }}" style="color:#1d4ed8;">{{ $mediaFile['name'] }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
