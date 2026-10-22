<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $document['title'] }} · {{ $document['month_label'] }}</title>
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            color: #0f172a;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .header td {
            padding: 0 0 8px 0;
            vertical-align: bottom;
        }

        .brand {
            font-size: 10px;
            letter-spacing: 0.08em;
            color: #0369a1;
            font-weight: bold;
        }

        .title {
            font-size: 16px;
            font-weight: bold;
            padding-top: 2px;
        }

        .scope {
            font-size: 11px;
            color: #334155;
        }

        .calendar th,
        .calendar td {
            border: 1px solid #cbd5e1;
            vertical-align: top;
            width: 14.28%;
        }

        .calendar th {
            padding: 4px;
            background: #e0f2fe;
            font-size: 8px;
            text-align: center;
        }

        .calendar td {
            padding: 4px;
            height: 68px;
        }

        .day-out {
            background: #f8fafc;
            color: #94a3b8;
        }

        .day-number {
            font-size: 10px;
            font-weight: bold;
        }

        .line {
            font-size: 7px;
            line-height: 1.25;
            color: #0f172a;
        }

        .extra {
            font-size: 7px;
            color: #0369a1;
            font-weight: bold;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            padding: 14px 0 6px 0;
        }

        .person td {
            padding: 8px 0 2px 0;
            font-size: 11px;
            font-weight: bold;
            border-bottom: 1px solid #bae6fd;
        }

        .detail th,
        .detail td {
            border: 1px solid #e2e8f0;
            padding: 3px 5px;
            text-align: left;
        }

        .detail th {
            background: #f1f5f9;
            font-size: 8px;
        }

        .col-date { width: 18%; }
        .col-weekday { width: 18%; }
        .col-place { width: 64%; }

        .empty {
            color: #64748b;
        }

        .footer td {
            padding-top: 10px;
            font-size: 8px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="brand">TU DR. GROUP</div>
                <div class="title">{{ $document['title'] }}</div>
                <div class="scope">{{ $document['month_label'] }} · {{ $document['scope_label'] }}</div>
            </td>
        </tr>
    </table>

    <table class="calendar">
        <thead>
            <tr>
                @foreach ($document['weekdays'] as $weekday)
                    <th>{{ $weekday }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($document['weeks'] as $week)
                <tr>
                    @foreach ($week as $day)
                        <td class="{{ $day['in_month'] ? '' : 'day-out' }}">
                            <div class="day-number">{{ $day['day'] }}</div>
                            @if ($day['in_month'])
                                @foreach ($day['lines'] as $line)
                                    <div class="line">{{ $line }}</div>
                                @endforeach
                                @if ($day['extra'] > 0)
                                    <div class="extra">+{{ $day['extra'] }} más</div>
                                @endif
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <table>
        <tr>
            <td class="section-title">Colaboradores</td>
        </tr>
    </table>

    @forelse ($document['people'] as $person)
        <table class="person">
            <tr>
                <td>{{ $person['name'] }}</td>
            </tr>
        </table>
        <table class="detail">
            <thead>
                <tr>
                    <th class="col-date">Fecha</th>
                    <th class="col-weekday">Día</th>
                    <th class="col-place">Lugar</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($person['days'] as $day)
                    <tr>
                        <td>{{ $day['date'] }}</td>
                        <td>{{ $day['weekday'] }}</td>
                        <td>{{ $day['places'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="empty">Sin asignaciones en este mes.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @empty
        <table class="detail">
            <tr>
                <td class="empty">No hay asignaciones en este mes.</td>
            </tr>
        </table>
    @endforelse

    <table class="footer">
        <tr>
            <td>Generado el {{ $generatedAt }}. El almanaque resume el mes; el detalle lista cada asignación.</td>
        </tr>
    </table>
</body>
</html>
