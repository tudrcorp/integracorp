<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte diario de auditorías</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f5f7;">
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto;">
        <tr>
            <td style="padding: 5px; background-color: #ffffff; border: 1px solid #e7e7e7; border-radius: 8px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="padding: 20px 10px;">
                            <img src="{{ config('parameters.PUBLIC_URL', config('app.url')).'/logoNewPdfTDEC.png' }}" alt="Tu Dr. en Casa" style="max-width: 100%; height: auto; border-radius: 8px;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 20px; color: #333333; font-size: 14px; line-height: 1.6;">
                            <h2 style="margin: 0 0 6px; color: #1f2937; font-size: 18px;">Reporte diario de auditorías completas</h2>
                            <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">Generado el {{ $generatedAt }}</p>
                            <p style="margin: 0 0 16px; color: #555555;">
                                A continuación se presenta el avance de auditorías por categoría. <strong>Auditados</strong> son los registros con la totalidad de sus puntos de control verificados; <strong>Pendientes</strong> son los que aún tienen puntos por auditar.
                            </p>
                            @php($categories = [$counts['agencies'], $counts['agents'], $counts['individual_affiliations'], $counts['corporate_affiliations']])
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <th style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; text-align: left; color: #374151; font-size: 13px;">Categoría</th>
                                    <th style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; text-align: right; color: #374151; font-size: 13px;">Total</th>
                                    <th style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; text-align: right; color: #374151; font-size: 13px;">Auditados</th>
                                    <th style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; text-align: right; color: #374151; font-size: 13px;">Pendientes</th>
                                </tr>
                                @foreach ($categories as $category)
                                    <tr>
                                        <td style="padding: 10px 12px; border: 1px solid #e5e7eb; color: #374151;">{{ $category['label'] }}</td>
                                        <td style="padding: 10px 12px; border: 1px solid #e5e7eb; text-align: right; color: #111827;">{{ $category['total'] }}</td>
                                        <td style="padding: 10px 12px; border: 1px solid #e5e7eb; text-align: right; font-weight: bold; color: #047857;">{{ $category['audited'] }}</td>
                                        <td style="padding: 10px 12px; border: 1px solid #e5e7eb; text-align: right; color: #b45309;">{{ $category['pending'] }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td style="padding: 10px 12px; border: 1px solid #d1d5db; background-color: #f9fafb; color: #111827; font-weight: bold;">Total general</td>
                                    <td style="padding: 10px 12px; border: 1px solid #d1d5db; background-color: #f9fafb; text-align: right; font-weight: bold; color: #111827;">{{ $counts['totals']['total'] }}</td>
                                    <td style="padding: 10px 12px; border: 1px solid #d1d5db; background-color: #f9fafb; text-align: right; font-weight: bold; color: #047857;">{{ $counts['totals']['audited'] }}</td>
                                    <td style="padding: 10px 12px; border: 1px solid #d1d5db; background-color: #f9fafb; text-align: right; font-weight: bold; color: #b45309;">{{ $counts['totals']['pending'] }}</td>
                                </tr>
                            </table>
                            <p style="margin: 0; color: #6b7280; font-size: 12px;">
                                Nota: un registro se contabiliza como auditado únicamente cuando todos sus puntos de control fueron verificados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
