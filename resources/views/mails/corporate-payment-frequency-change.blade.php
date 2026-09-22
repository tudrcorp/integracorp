<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
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

        $reversed = $event === 'reversed';
        $cell = 'padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;';
        $labelCell = 'padding: 8px 12px; border: 1px solid #e5e7eb; width: 38%; color: #6b7280;';
        $headCell = 'padding: 8px 10px; border: 1px solid #e5e7eb; background-color: #f3f4f6; color: #374151; font-size: 12px; text-align: left;';
        $rowCell = 'padding: 7px 10px; border: 1px solid #e5e7eb; color: #111827; font-size: 12px;';
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
                            <h2 style="margin: 0 0 6px; color: {{ $reversed ? '#b91c1c' : '#1f2937' }}; font-size: 18px;">{{ $title }}</h2>
                            <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">Notificación generada el {{ $generatedAt }}</p>
                            <p style="margin: 0 0 20px; color: #555555;">{{ $intro }}</p>

                            @foreach ($changes as $change)
                                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 12px;">
                                    <tr>
                                        <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: {{ $reversed ? '#fef2f2' : '#eff6ff' }}; font-weight: bold; color: #111827;">
                                            {{ $change['affiliation'] }}
                                        </td>
                                    </tr>
                                    <tr><td style="{{ $labelCell }}">Frecuencia</td><td style="{{ $cell }}">{{ $change['previousFrequency'] }} → <strong>{{ $change['newFrequency'] }}</strong></td></tr>
                                    <tr><td style="{{ $labelCell }}">Tarifa anual</td><td style="{{ $cell }}">{{ $change['feeAnual'] }} (no cambia)</td></tr>
                                    <tr><td style="{{ $labelCell }}">Monto por período</td><td style="{{ $cell }}">{{ $change['previousTotal'] }} → <strong>{{ $change['newTotal'] }}</strong></td></tr>
                                    <tr><td style="{{ $labelCell }}">Saldo redistribuido</td><td style="{{ $cell }}">{{ $change['pendingBalance'] }}</td></tr>
                                    <tr><td style="{{ $labelCell }}">Cambio hecho por</td><td style="{{ $cell }}">{{ $change['performedBy'] }} · {{ $change['performedAt'] }}</td></tr>
                                    @if ($change['reversedBy'])
                                        <tr><td style="{{ $labelCell }}">Revertido por</td><td style="{{ $cell }}">{{ $change['reversedBy'] }} · {{ $change['reversedAt'] }}</td></tr>
                                        <tr><td style="{{ $labelCell }}">Motivo del reverso</td><td style="{{ $cell }}">{{ $change['reversalReason'] }}</td></tr>
                                    @endif
                                </table>

                                <p style="margin: 14px 0 6px; font-weight: bold; color: #111827;">
                                    {{ $reversed ? 'Avisos de cobro restaurados (vuelven a POR PAGAR)' : 'Avisos de cobro cancelados' }} ({{ count($change['cancelled']) }})
                                </p>
                                @if ($change['cancelled'] === [])
                                    <p style="margin: 0 0 12px; color: #6b7280; font-size: 13px;">No tenía avisos de cobro pendientes.</p>
                                @else
                                    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 12px;">
                                        <tr>
                                            <th style="{{ $headCell }}">Aviso</th>
                                            <th style="{{ $headCell }}">Fecha de cobro</th>
                                            <th style="{{ $headCell }}">Período</th>
                                            <th style="{{ $headCell }} text-align: right;">Monto</th>
                                        </tr>
                                        @foreach ($change['cancelled'] as $row)
                                            <tr>
                                                <td style="{{ $rowCell }}">{{ $row['invoice'] }}</td>
                                                <td style="{{ $rowCell }}">{{ $row['date'] }}</td>
                                                <td style="{{ $rowCell }}">{{ $row['months'] }}</td>
                                                <td style="{{ $rowCell }} text-align: right;">{{ $row['amount'] }}</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                @endif

                                <p style="margin: 14px 0 6px; font-weight: bold; color: #111827;">
                                    {{ $reversed ? 'Avisos de cobro anulados por el reverso' : 'Avisos de cobro nuevos' }} ({{ count($change['created']) }})
                                </p>
                                @if ($change['created'] === [])
                                    <p style="margin: 0 0 12px; color: #6b7280; font-size: 13px;">No se generaron avisos nuevos.</p>
                                @else
                                    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 12px;">
                                        <tr>
                                            <th style="{{ $headCell }}">Aviso</th>
                                            <th style="{{ $headCell }}">Fecha de cobro</th>
                                            <th style="{{ $headCell }}">Período</th>
                                            <th style="{{ $headCell }} text-align: right;">Monto</th>
                                        </tr>
                                        @foreach ($change['created'] as $row)
                                            <tr>
                                                <td style="{{ $rowCell }}">{{ $row['invoice'] }}</td>
                                                <td style="{{ $rowCell }}">{{ $row['date'] }}</td>
                                                <td style="{{ $rowCell }}">{{ $row['months'] }}</td>
                                                <td style="{{ $rowCell }} text-align: right;">{{ $row['amount'] }}</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                @endif

                                <p style="margin: 8px 0 24px;">
                                    <a href="{{ $change['url'] }}" style="display: inline-block; padding: 10px 18px; background-color: #052F60; color: #ffffff; text-decoration: none; border-radius: 999px; font-size: 13px; font-weight: bold;">
                                        {{ $reversed ? 'Ver el registro del reverso' : 'Revisar y validar el cambio' }}
                                    </a>
                                </p>
                            @endforeach

                            <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                Este mensaje es automático. Cada cambio queda registrado con el estado anterior completo en INTEGRACORP → Administración → Cambios de frecuencia de pago.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
