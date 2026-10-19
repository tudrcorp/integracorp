<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feliz cumpleaños</title>
</head>

<body style="margin: 0; padding: 0; background-color: #18335e;">

    <table align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #18335e;">
        <tr>
            <td align="center" style="padding: 0; background-color: #18335e;">
                @if (filled($animationUrl))
                    <a href="{{ $animationUrl }}" target="_blank" style="text-decoration: none; border: 0; display: block;">
                        <img src="{{ \App\Support\Birthday\BirthdayCelebrationCard::publicUrl() }}"
                            width="600"
                            alt="¡Haz clic aquí para ver tus regalos!"
                            style="display: block; width: 100%; max-width: 600px; height: auto; border: 0;">
                    </a>
                @else
                    <img src="{{ \App\Support\PublicStorageAssetUrl::url((string) ($file ?? '')) }}"
                        width="600"
                        alt="Feliz cumpleaños"
                        style="display: block; width: 100%; max-width: 600px; height: auto; border: 0;">
                @endif
            </td>
        </tr>
    </table>

</body>

</html>
