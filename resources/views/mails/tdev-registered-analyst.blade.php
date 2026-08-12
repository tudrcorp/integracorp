<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f5f7;">
    @php
        use App\Support\Tdev\TdevWhatsAppBrandImage;

        $logoPath = TdevWhatsAppBrandImage::emailLogoPath();
        $logoSrc = isset($message) && file_exists($logoPath)
            ? $message->embed($logoPath)
            : asset('image/intcorp-tdev.png');
    @endphp
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto;">
        <tr>
            <td style="padding: 5px; background-color: #ffffff; border: 1px solid #e7e7e7; border-radius: 8px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="padding: 20px 10px;">
                            <img src="{{ $logoSrc }}" alt="INTEGRACORP · Tu Doctor En Viajes" width="560" style="max-width: 560px; width: 100%; height: auto; display: block; margin: 0 auto; border: 0; border-radius: 8px;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 20px; color: #333333; font-size: 14px; line-height: 1.6;">
                            <h2 style="margin: 0 0 6px; color: #1f2937; font-size: 18px;">{{ $title }}</h2>
                            <p style="margin: 0 0 8px; color: #0f766e; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.08em;">
                                {{ $registrationTypeLabel }}
                            </p>
                            <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">Notificación generada el {{ $generatedAt }}</p>
                            <p style="margin: 0 0 16px; color: #555555;">
                                {{ $intro }}
                            </p>

                            @if ($agent)
                                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                    <tr>
                                        <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #ecfeff; font-weight: bold; color: #111827;">Agente</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Nombre</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $agent->full_name }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Cargo</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $agent->position ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Correo</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $agent->email ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Teléfono</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $agent->phone ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Nacimiento</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $agent->birth_date?->format('d/m/Y') ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Registrado</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $agent->registered_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
                                    </tr>
                                </table>
                            @endif

                            @if ($agency)
                                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                    <tr>
                                        <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #ecfeff; font-weight: bold; color: #111827;">
                                            {{ $agent ? 'Agencia' : 'Agencia registrada (nivel 3)' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Nombre</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $agency->name }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Nivel</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">Nivel {{ $agency->level }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Identificación</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $agency->identification_number ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Correo</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $agency->email ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Teléfono</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $agency->phone ?: '—' }}</td>
                                    </tr>
                                    @unless ($agent)
                                        <tr>
                                            <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Tel. adicional</td>
                                            <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $agency->phone_additional ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Representante</td>
                                            <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $agency->representative_name ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Instagram</td>
                                            <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ filled($agency->instagram_username) ? '@'.$agency->instagram_username : '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Dirección</td>
                                            <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $agency->address ?: '—' }}</td>
                                        </tr>
                                    @endunless
                                </table>
                            @endif

                            @if ($parentAgency)
                                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                    <tr>
                                        <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold; color: #111827;">Agencia principal (nivel 2)</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Nombre</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $parentAgency->name }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Correo</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $parentAgency->email ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Teléfono</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $parentAgency->phone ?: '—' }}</td>
                                    </tr>
                                </table>
                            @endif

                            <p style="margin: 0; padding: 12px; background-color: #ecfeff; border-radius: 8px; color: #0f766e; font-size: 13px;">
                                <strong>Acción:</strong> revise el registro en INTEGRACORP → Estructura comercial → AGENCIAS TDEV.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
