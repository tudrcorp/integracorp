@php
    $money = fn ($value) => number_format((float) $value, 2, ',', '.').' US$';
    $logoUrl = rtrim((string) config('parameters.INTEGRACORP_URL'), '/').'/image/logoNewTDG.png';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de cuenta — {{ $companyName }}</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color:#f4f5f7;">
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width:660px; margin:0 auto; padding:24px 12px;">
        <tr>
            <td style="background-color:#ffffff; border:1px solid #e7e7e7; border-radius:10px; overflow:hidden;">

                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="padding:24px 20px 12px; border-bottom:3px solid #052F60;">
                            <img src="{{ $logoUrl }}" alt="Tu Dr Group" style="max-width:210px; height:auto;">
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 28px 8px; color:#1f2937;">
                            <h2 style="margin:0 0 4px; font-size:18px; color:#052F60;">
                                Estado de cuenta y conciliación de afiliaciones
                            </h2>
                            <p style="margin:0 0 18px; font-size:13px; color:#6b7280;">
                                Período del {{ $from }} al {{ $to }}
                            </p>

                            <p style="margin:0 0 14px; font-size:14px; line-height:1.7; color:#374151;">
                                Estimados señores de <strong>{{ $companyName }}</strong>:
                            </p>

                            <p style="margin:0 0 14px; font-size:14px; line-height:1.7; color:#374151; text-align:justify;">
                                Por medio de la presente hacemos de su conocimiento que remitimos adjunto el
                                <strong>estado de cuenta correspondiente a la conciliación de las afiliaciones
                                ejecutadas</strong> durante el período comprendido entre el <strong>{{ $from }}</strong>
                                y el <strong>{{ $to }}</strong>.
                            </p>

                            <p style="margin:0 0 18px; font-size:14px; line-height:1.7; color:#374151; text-align:justify;">
                                El documento adjunto detalla, para cada afiliación ejecutada, el plan contratado, la
                                cobertura asociada, el monto a pagar y la distribución de la neta que corresponde a
                                Tu Dr Group y a su representada, conforme a las condiciones comerciales pactadas.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 8px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:13px;">
                                <tr>
                                    <td colspan="2" style="background-color:#052F60; color:#ffffff; padding:9px 12px; font-weight:bold; border-radius:6px 6px 0 0;">
                                        Resumen del período
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:9px 12px; border:1px solid #e5e7eb; color:#4b5563;">Afiliaciones ejecutadas</td>
                                    <td style="padding:9px 12px; border:1px solid #e5e7eb; text-align:right; font-weight:bold; color:#111827;">{{ $rowsCount }}</td>
                                </tr>
                                <tr style="background-color:#f9fafb;">
                                    <td style="padding:9px 12px; border:1px solid #e5e7eb; color:#4b5563;">Personas afiliadas</td>
                                    <td style="padding:9px 12px; border:1px solid #e5e7eb; text-align:right; font-weight:bold; color:#111827;">{{ $totals['affiliates'] }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:9px 12px; border:1px solid #e5e7eb; color:#4b5563;">Monto total a pagar</td>
                                    <td style="padding:9px 12px; border:1px solid #e5e7eb; text-align:right; font-weight:bold; color:#111827;">{{ $money($totals['sale_price']) }}</td>
                                </tr>
                                <tr style="background-color:#f9fafb;">
                                    <td style="padding:9px 12px; border:1px solid #e5e7eb; color:#4b5563;">Neta correspondiente a Tu Dr Group</td>
                                    <td style="padding:9px 12px; border:1px solid #e5e7eb; text-align:right; font-weight:bold; color:#111827;">{{ $money($totals['neta_tdg']) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:9px 12px; border:1px solid #e5e7eb; color:#4b5563;">Neta correspondiente a {{ $companyName }}</td>
                                    <td style="padding:9px 12px; border:1px solid #e5e7eb; text-align:right; font-weight:bold; color:#111827;">{{ $money($totals['neta_partner']) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px 6px;">
                            <p style="margin:0 0 14px; font-size:14px; line-height:1.7; color:#374151; text-align:justify;">
                                A efectos de control, el reporte adjunto incorpora en su pie de página la llave de
                                verificación <strong style="font-family:monospace; letter-spacing:1px;">{{ $securityKey }}</strong>,
                                la cual certifica la integridad de su contenido. Cualquier modificación posterior del
                                archivo invalidará dicha llave.
                            </p>

                            <p style="margin:0 0 14px; font-size:14px; line-height:1.7; color:#374151; text-align:justify;">
                                Agradecemos su revisión. En caso de observar alguna diferencia, le agradecemos
                                notificarla respondiendo a este correo para su oportuna aclaratoria.
                            </p>

                            <p style="margin:0 0 4px; font-size:14px; line-height:1.7; color:#374151;">
                                Sin otro particular, quedamos a su entera disposición.
                            </p>
                            <p style="margin:0 0 22px; font-size:14px; line-height:1.7; color:#374151;">
                                Atentamente,<br>
                                <strong>Departamento de Administración</strong><br>
                                Tu Dr Group
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 28px 22px; border-top:1px solid #eef0f3; background-color:#fafbfc;">
                            <p style="margin:0; font-size:11px; line-height:1.6; color:#9ca3af; text-align:center;">
                                Este mensaje y el documento adjunto son de carácter confidencial y están dirigidos
                                exclusivamente a su destinatario. Generado automáticamente por IntegraCorp — Tu Dr Group.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
