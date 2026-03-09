<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feliz Aniversario</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif;">

    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto;">
        <tr>
            <td style="padding: 20px; background-color: #ffffff; border: 1px solid #e7e7e7; border-radius: 8px;">
                @if($imagePath)
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td align="center" style="padding-bottom: 20px;">
                                <img src="{{ config('parameters.PUBLIC_URL') . '/' . $imagePath }}" style="display: block; width: 100%; max-width: 560px; height: auto; border-radius: 8px;" alt="Aniversario">
                            </td>
                        </tr>
                    </table>
                @endif
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 10px 0; font-size: 16px; line-height: 1.6; color: #333;">
                            <p style="margin: 0 0 16px;">Apreciado/a <strong>{{ $name }}</strong>,</p>
                            <p style="margin: 0;">{{ $content }}</p>
                            <p style="margin: 24px 0 0;">¡Gracias por ser parte de nuestro equipo!</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
