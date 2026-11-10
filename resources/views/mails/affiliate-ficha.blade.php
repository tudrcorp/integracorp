<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha del afiliado</title>
</head>
<body style="margin: 0; padding: 24px; background: #f1f5f9; font-family: Arial, sans-serif; font-size: 14px; color: #0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px;">
        <tr>
            <td style="padding: 24px 28px; border-bottom: 3px solid #052f60;">
                <p style="margin: 0; font-size: 18px; font-weight: bold; color: #052f60;">Ficha del afiliado</p>
                <p style="margin: 4px 0 0; font-size: 13px; color: #475569;">{{ $affiliateName }} · {{ $affiliateDocument }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px 28px;">
                <p style="margin: 0 0 12px;">Hola,</p>
                <p style="margin: 0 0 12px;">
                    Adjuntamos la ficha del afiliado <strong>{{ $affiliateName }}</strong>, de la afiliación <strong>{{ $affiliationCode }}</strong>,
                    con la información de <strong>{{ mb_strtolower($scopeLabel) }}</strong>.
                </p>

                @if (filled($analystMessage))
                    <p style="margin: 0 0 12px; padding: 12px 14px; background: #f8fafc; border-left: 3px solid #0cb7f2; white-space: pre-line;">{{ $analystMessage }}</p>
                @endif

                @if ($includesClinical)
                    <p style="margin: 0 0 12px; padding: 10px 12px; background: #fff7ed; border: 1px solid #fed7aa; font-size: 12px; color: #9a3412;">
                        El documento contiene información médica confidencial. No lo reenvíe fuera de los canales autorizados.
                    </p>
                @else
                    <p style="margin: 0 0 12px; font-size: 12px; color: #64748b;">Este documento es confidencial; no lo reenvíe fuera de los canales autorizados.</p>
                @endif

                <p style="margin: 16px 0 0;">Saludos,<br>{{ filled($senderName) ? $senderName.' · ' : '' }}Tu Doctor en Casa</p>
            </td>
        </tr>
    </table>
</body>
</html>
