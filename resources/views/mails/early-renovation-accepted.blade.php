<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renovación anticipada</title>
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

        $items = is_array($items ?? null) ? $items : [];
        $period = (int) ($renewal_period_days ?? 30);
        $isCorporate = ($kind ?? 'individual') === 'corporate';
    @endphp
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 680px; margin: 0 auto;">
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
                            <h2 style="margin: 0 0 6px; color: #1f2937; font-size: 18px;">Renovación anticipada</h2>
                            <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">{{ $panel ?? '—' }} · {{ $accepted_at ?? '—' }}</p>

                            <p style="margin: 0 0 16px; padding: 12px; background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; color: #92400e;">
                                <strong>{{ $authorized_by ?? '—' }}</strong> aceptó
                                {{ count($items) === 1 ? 'una renovación' : count($items).' renovaciones' }} {{ $kind_label ?? '' }}
                                <strong>antes del período de renovación</strong>, que se abre a {{ $period }} días de la fecha de renovación.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="5" style="padding: 10px 12px; border: 1px solid #fde68a; background-color: #fef3c7; font-weight: bold; color: #92400e;">
                                        Renovaciones aceptadas ({{ count($items) }})
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 10px; border: 1px solid #e5e7eb; color: #6b7280; font-size: 11px; text-transform: uppercase;">Afiliación</td>
                                    <td style="padding: 8px 10px; border: 1px solid #e5e7eb; color: #6b7280; font-size: 11px; text-transform: uppercase;">{{ $isCorporate ? 'Empresa' : 'Titular' }}</td>
                                    <td style="padding: 8px 10px; border: 1px solid #e5e7eb; color: #6b7280; font-size: 11px; text-transform: uppercase;">Fecha de renovación</td>
                                    <td style="padding: 8px 10px; border: 1px solid #e5e7eb; color: #6b7280; font-size: 11px; text-transform: uppercase;">Plan aplicado</td>
                                    <td style="padding: 8px 10px; border: 1px solid #e5e7eb; color: #6b7280; font-size: 11px; text-transform: uppercase;">Anual</td>
                                </tr>
                                @foreach ($items as $item)
                                    <tr>
                                        <td style="padding: 8px 10px; border: 1px solid #e5e7eb; color: #111827; font-weight: bold;">
                                            @if (filled($item['url'] ?? null))
                                                <a href="{{ $item['url'] }}" style="color: #1d4ed8; text-decoration: none;">{{ $item['affiliation_code'] ?? '—' }}</a>
                                            @else
                                                {{ $item['affiliation_code'] ?? '—' }}
                                            @endif
                                        </td>
                                        <td style="padding: 8px 10px; border: 1px solid #e5e7eb; color: #111827;">{{ $item['holder'] ?? '—' }}</td>
                                        <td style="padding: 8px 10px; border: 1px solid #e5e7eb; color: #111827;">
                                            {{ $item['date_renewal'] ?? '—' }}<br>
                                            <span style="color: #b45309; font-size: 12px; font-weight: bold;">faltaban {{ \App\Support\Renovations\EarlyRenovationNotificationPayload::daysText($item['days_before_renewal'] ?? null) }}</span>
                                        </td>
                                        <td style="padding: 8px 10px; border: 1px solid #e5e7eb; color: #111827;">
                                            {{ $item['plan'] ?? '—' }}<br>
                                            <span style="color: #6b7280; font-size: 12px;">{{ $item['payment_frequency'] ?? '—' }} · {{ (int) ($item['total_persons'] ?? 0) }} {{ (int) ($item['total_persons'] ?? 0) === 1 ? 'persona' : 'personas' }}</span>
                                        </td>
                                        <td style="padding: 8px 10px; border: 1px solid #e5e7eb; color: #111827; white-space: nowrap;">{{ \App\Support\Renovations\EarlyRenovationNotificationPayload::money((float) ($item['annual_amount'] ?? 0)) }}</td>
                                    </tr>
                                @endforeach
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold; color: #111827;">Registro de la autorización</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 32%; color: #6b7280;">Autorizado por</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $authorized_by ?? '—' }}{{ filled($authorized_by_email ?? null) ? ' · '.$authorized_by_email : '' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Panel</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $panel ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Fecha</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $accepted_at ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Motivo</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827; font-weight: bold; word-break: break-word;">{{ $reason ?? '—' }}</td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 8px; color: #6b7280; font-size: 12px;">
                                La renovación quedó registrada en el histórico de renovaciones marcada como anticipada, con el motivo y los días que faltaban.
                                La nueva vigencia parte de la fecha de renovación original: renovar antes no adelanta el aniversario.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
