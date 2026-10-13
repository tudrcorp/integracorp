{{-- Guía de uso al pie de la última hoja de carnets (diseño «Voucher Tu Dr en Casa» v6). Tablas de ancho fijo: DomPDF no maneja flex ni grid. --}}
@php
    $steps = [
        ['1', 'Escríbenos', 'Contacta al <strong style="color:#12293A;">+58 414-200 92 29</strong>.<br>Proporciona la siguiente información: número de cédula, dirección donde te encuentras, teléfono de contacto y tus síntomas.'],
        ['2', 'Te evalúa un médico', 'Un médico de guardia revisará tu información y te brindará la atención médica telefónica que necesitas.'],
        ['3', 'Activamos tu plan', 'Activamos los beneficios de tu plan para estabilizarte en caso de ser necesario, y te acompañamos con monitoreo médico hasta el alta.'],
    ];
    $waits = [
        ['Telemedicina', 'Desde el primer día', 'Sin período de espera'],
        ['Atención médica en sitio', 'A partir del día 8', '8 días de espera'],
        ['Centros de salud', 'Según evaluación', 'Consulta con coordinación médica'],
    ];
    $circle = 'width:30px; height:30px; background:#26B4E8; border-radius:15px; text-align:center; vertical-align:middle; font-size:14px; font-weight:bold; color:#FFFFFF;';
@endphp
<table style="width:720px; margin-top:6px;"><tr>
    <td style="width:684px; background:#F6F6F6; border-radius:16px; padding:12px 18px 10px 18px;">
        <table style="width:684px;"><tr>
            <td style="width:440px; font-size:15px; font-weight:bold; color:#12293A;">Activa tu servicio en 3 pasos</td>
            <td style="width:244px; text-align:right; font-size:15px; font-style:italic; color:#2D89CA;">Estamos contigo 24/7</td>
        </tr></table>

        {{-- Círculos numerados unidos por la línea punteada del diseño. --}}
        <table style="width:684px; margin-top:10px;"><tr>
            <td style="width:30px;"><table><tr><td style="{{ $circle }}">1</td></tr></table></td>
            <td style="width:204px; vertical-align:middle;"><div style="border-top:2px dashed #AEC2CB;"></div></td>
            <td style="width:30px;"><table><tr><td style="{{ $circle }}">2</td></tr></table></td>
            <td style="width:204px; vertical-align:middle;"><div style="border-top:2px dashed #AEC2CB;"></div></td>
            <td style="width:30px;"><table><tr><td style="{{ $circle }}">3</td></tr></table></td>
            <td style="width:186px;"></td>
        </tr></table>

        <table style="width:684px; margin-top:6px;"><tr>
            @foreach ($steps as [$number, $title, $text])
                <td style="width:214px; vertical-align:top; {{ $loop->last ? '' : 'padding-right:20px;' }}">
                    <div style="font-size:13px; font-weight:bold; color:#12293A;">{{ $title }}</div>
                    <div style="font-size:10px; line-height:1.4; color:#35657D; margin-top:3px;">{!! $text !!}</div>
                </td>
            @endforeach
        </tr></table>

        <div style="border-top:1px solid #E4E6E8; margin-top:10px; padding-top:8px;">
            <table style="width:684px;"><tr>
                <td style="width:420px; font-size:12.5px; font-weight:bold; color:#12293A;">¿Desde cuándo puedes usar cada servicio?</td>
                <td style="width:264px; text-align:right; font-size:9.5px; color:#64748B;">Contado desde tu fecha de afiliación</td>
            </tr></table>
            <table style="width:684px; margin-top:6px;"><tr>
                @foreach ($waits as [$label, $value, $note])
                    <td style="width:221px; vertical-align:top; {{ $loop->last ? '' : 'padding-right:10px;' }}">
                        <div style="background:#FFFFFF; border-radius:10px; padding:6px 12px;">
                            <div style="font-size:10px; color:#64748B;">{{ $label }}</div>
                            <div style="font-size:13px; font-weight:bold; color:#12293A; margin-top:2px;">{{ $value }}</div>
                            <div style="font-size:9.5px; color:#64748B; margin-top:2px;">{{ $note }}</div>
                        </div>
                    </td>
                @endforeach
            </tr></table>
            <div style="font-size:10px; color:#35657D; text-align:center; margin-top:6px;">Servicios válidos exclusivamente durante el período pagado.</div>
        </div>
    </td>
</tr></table>
<div class="soft" style="font-size:9.5px; line-height:1.4; text-align:center; margin:6px 24px 0 24px;">Conserve su carnet de afiliado cerca de sus documentos personales. Esta tarjeta no es un requisito obligatorio para solicitar el servicio; si usted presenta una eventualidad puede identificarse con su nombre y número de cédula.</div>
<div class="soft" style="font-size:9.5px; line-height:1.4; text-align:center; margin:3px 50px 0 50px;">Los servicios incluidos en este producto se encuentran regidos y amparados por las <strong style="text-decoration:underline; color:#12293A;">Condiciones Especiales</strong>.</div>
