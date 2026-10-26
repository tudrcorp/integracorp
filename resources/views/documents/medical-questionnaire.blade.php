<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $questionnaire->name }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #0f172a; margin: 0; }
        table { border-collapse: collapse; }
        .questions thead { display: table-header-group; }
        .questions tr { page-break-inside: avoid; }
        .closing { page-break-inside: avoid; }
        .header { width: 100%; table-layout: fixed; border-bottom: 2px solid #052F60; }
        .header td { vertical-align: middle; padding-bottom: 10px; }
        .header img { max-width: 170px; height: auto; }
        .title { font-size: 14px; font-weight: bold; color: #052F60; text-align: right; }
        .subtitle { font-size: 9px; color: #475569; text-align: right; margin-top: 3px; }
        .intro { margin: 12px 0 8px 0; color: #334155; line-height: 1.45; }
        .fields { width: 100%; table-layout: fixed; margin: 6px 0 12px 0; }
        .fields td { padding: 6px 6px 2px 0; vertical-align: bottom; }
        .line { border-bottom: 1px solid #94a3b8; height: 14px; }
        .label { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .questions { width: 100%; table-layout: fixed; }
        .questions th { background: #052F60; color: #ffffff; font-size: 8.5px; padding: 6px 6px; text-align: left; }
        .questions th.answer { text-align: center; }
        .questions td { border-bottom: 1px solid #e2e8f0; padding: 7px 6px; vertical-align: top; line-height: 1.4; }
        .questions tr.odd td { background: #f8fafc; }
        .questions td.number { color: #052F60; font-weight: bold; }
        .questions td.answer { text-align: center; font-size: 12px; }
        .section-title { font-size: 10px; font-weight: bold; color: #052F60; margin: 14px 0 6px 0; }
        .obs { width: 100%; table-layout: fixed; }
        .obs td { border-bottom: 1px solid #94a3b8; height: 20px; }
        .declaration { margin-top: 12px; color: #334155; line-height: 1.45; font-size: 8.5px; }
        .signatures { width: 100%; table-layout: fixed; margin-top: 34px; }
        .signatures td { text-align: center; padding: 0 18px; vertical-align: top; }
        .signatures .sign-line { border-top: 1px solid #0f172a; padding-top: 4px; font-size: 8.5px; color: #334155; }
        .footer { margin-top: 16px; font-size: 7.5px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
            <table class="header">
                <tr>
                    <td style="width: 45%;">
                        @if ($logoDataUri !== '')
                            <img src="{{ $logoDataUri }}" alt="Tu Dr. En Casa">
                        @endif
                    </td>
                    <td style="width: 55%;">
                        <div class="title">{{ $questionnaire->name }}</div>
                        <div class="subtitle">{{ $questionnaire->plan?->description ?? 'Plan' }} · {{ $questions->count() }} preguntas · Versión del {{ $generatedAt->format('d/m/Y') }}</div>
                    </td>
                </tr>
            </table>
            <p class="intro">Responda <strong>Sí</strong> o <strong>No</strong> a cada pregunta tomando en cuenta a todos los solicitantes. Toda respuesta afirmativa debe ampliarse en «Observaciones»: indique el número de la pregunta, la persona, el diagnóstico o enfermedad, la fecha y su condición actual.</p>
            <table class="fields">
                <tr>
                    <td style="width: 50%;"><div class="line"></div><div class="label">Nombre del titular</div></td>
                    <td style="width: 25%;"><div class="line"></div><div class="label">Cédula</div></td>
                    <td style="width: 25%;"><div class="line"></div><div class="label">Fecha</div></td>
                </tr>
            </table>
            <table class="questions">
                <thead>
                    <tr>
                        <th style="width: 6%;">N°</th>
                        <th style="width: 76%;">Pregunta</th>
                        <th class="answer" style="width: 9%;">Sí</th>
                        <th class="answer" style="width: 9%;">No</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($questions as $question)
                        <tr class="{{ $loop->odd ? 'odd' : '' }}">
                            <td class="number">{{ $loop->iteration }}</td>
                            <td>{{ $question->text }}</td>
                            <td class="answer"><span style="font-family: DejaVu Sans, sans-serif;">&#9744;</span></td>
                            <td class="answer"><span style="font-family: DejaVu Sans, sans-serif;">&#9744;</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="closing">
            <div class="section-title">Observaciones</div>
            <table class="obs">
                @for ($i = 0; $i < 5; $i++)
                    <tr><td></td></tr>
                @endfor
            </table>
            <p class="declaration">Certifico que he leído todas las respuestas y declaraciones de esta solicitud y que, a mi mejor entendimiento, están completas y son verdaderas. Entiendo que cualquier omisión o declaración incompleta o incorrecta puede causar que las reclamaciones sean negadas y que el plan sea modificado, rescindido o cancelado.</p>
            <table class="signatures">
                <tr>
                    <td><div class="sign-line">Firma del titular</div></td>
                    <td><div class="sign-line">Firma del agente</div></td>
                </tr>
            </table>
            <div class="footer">Tu Dr. En Casa · Documento generado por IntegraCorp el {{ $generatedAt->format('d/m/Y h:i a') }}</div>
            </div>
</body>
</html>
