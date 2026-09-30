<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afiliado {{ $kind_label ?? 'individual' }} actualizado por Operaciones</title>
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

        $changes = is_array($changes ?? null) ? $changes : [];
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
                            <h2 style="margin: 0 0 6px; color: #1f2937; font-size: 18px;">Afiliado {{ $kind_label ?? 'individual' }} actualizado por Operaciones</h2>
                            <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">{{ $source_label ?? '—' }} · {{ $updated_at ?? '—' }}</p>
                            <p style="margin: 0 0 16px; color: #555555;">
                                <strong>{{ $updated_by ?? '—' }}</strong> actualizó los datos personales de
                                <strong>{{ $affiliate_name ?? '—' }}</strong> (afiliación <strong>{{ $affiliation_code ?? '—' }}</strong>).
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="3" style="padding: 10px 12px; border: 1px solid #bfdbfe; background-color: #eff6ff; font-weight: bold; color: #1e3a8a;">
                                        Cambios realizados ({{ count($changes) }})
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 28%; color: #6b7280; font-size: 12px; text-transform: uppercase;">Campo</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 36%; color: #6b7280; font-size: 12px; text-transform: uppercase;">Antes</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 36%; color: #6b7280; font-size: 12px; text-transform: uppercase;">Ahora</td>
                                </tr>
                                @foreach ($changes as $change)
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #374151; font-weight: bold;">{{ $change['label'] }}</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #9ca3af; text-decoration: line-through; word-break: break-word;">{{ $change['before'] }}</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #065f46; background-color: #f0fdf4; font-weight: bold; word-break: break-word;">{{ $change['after'] }}</td>
                                    </tr>
                                @endforeach
                            </table>

                            @if (filled($age_range_warning ?? null))
                                <p style="margin: 0 0 12px; padding: 12px; background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; color: #991b1b; font-size: 13px;">
                                    <strong>Tarifa:</strong> {{ $age_range_warning }}
                                </p>
                            @endif

                            @if (filled($titular_note ?? null))
                                <p style="margin: 0 0 12px; padding: 12px; background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; color: #92400e; font-size: 13px;">
                                    <strong>Titular:</strong> {{ $titular_note }}
                                </p>
                            @endif

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold; color: #111827;">Afiliado</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Nombre</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $affiliate_name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Cédula</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $affiliate_document ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Parentesco</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $relationship ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Afiliación</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $affiliation_code ?? '—' }}</td>
                                </tr>
                                @if (filled($company ?? null))
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Empresa</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $company }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Plan</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $plan ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Estado</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $status ?? '—' }}</td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold; color: #111827;">Registro del cambio</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Analista</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $updated_by ?? '—' }} · {{ $updated_by_email ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Origen</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $source_label ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Fecha</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $updated_at ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Paciente de telemedicina</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ ($telemedicine_synced ?? false) ? 'Actualizado con los mismos datos' : 'Sin cambios (no está vinculado o no hubo datos que replicar)' }}</td>
                                </tr>
                            </table>

                            @if (filled($url ?? null))
                                <p style="margin: 0 0 8px; text-align: center;">
                                    <a href="{{ $url }}" style="display: inline-block; padding: 10px 20px; background-color: #2d89ca; color: #ffffff; text-decoration: none; border-radius: 999px; font-weight: bold; font-size: 14px;">Ver afiliado en INTEGRACORP</a>
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
