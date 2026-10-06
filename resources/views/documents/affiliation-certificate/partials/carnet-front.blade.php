{{-- Frente del carnet: color del plan, nombre, documento, plan, cobertura y vigencia. --}}
@php($th = $data['th'])
<div class="card-cut" style="width:332px; margin:0 auto;">
<table style="width:324px; height:204px; background:{{ $th['bg'] }}; color:{{ $th['ink'] }}; border-radius:12px;">
    <tr><td style="height:26px; padding:14px 18px 0 18px;">
        <table style="width:288px;"><tr>
            <td style="width:200px; text-align:left;"><img src="{{ $data['images']['logoTheme'] }}" alt="Tu Doctor en Casa" style="width:122px;"></td>
            <td style="width:88px; text-align:right; padding-top:2px; font-size:8.5px; font-weight:bold; letter-spacing:1.2px; text-transform:uppercase; color:{{ $th['sub'] }};">Afiliado</td>
        </tr></table>
    </td></tr>
    <tr><td style="height:70px; padding:0 18px; vertical-align:bottom; text-align:left;">
        <div style="font-size:16px; line-height:1.15; font-weight:bold; color:{{ $th['title'] }};">{{ $b['nombre'] }}</div>
        <div style="font-size:10px; color:{{ $th['sub'] }}; margin-top:3px;">{{ $b['docFmt'] }}</div>
    </td></tr>
    <tr><td style="height:42px; padding:8px 18px 0 18px;">
        <table style="width:288px;"><tr>
            <td style="width:150px; text-align:left; vertical-align:bottom;">
                <span style="padding:2px 9px; border-radius:8px; background:{{ $th['pillBg'] }}; color:{{ $th['pillInk'] }}; font-size:10px; font-weight:bold; letter-spacing:0.8px;">{{ $b['plan'] ?? $data['planNombre'] }}</span>
                <div style="font-size:8.5px; color:{{ $th['sub'] }}; margin-top:5px; white-space:nowrap;">{{ $b['cobertura'] ?? $data['carnetCobertura'] }}</div>
                @if ($data['carnetPreex'])
                    <div style="font-size:8px; color:{{ $th['sub'] }}; white-space:nowrap;">Excluye preexistencias</div>
                @endif
            </td>
            <td style="width:138px; text-align:right; vertical-align:bottom;">
                <div style="font-size:8px; letter-spacing:1px; text-transform:uppercase; color:{{ $th['sub'] }};">Vigencia</div>
                <div style="font-size:9.5px; font-weight:bold; white-space:nowrap; margin-top:2px;">{{ $data['desde'] }} – {{ $data['hasta'] }}</div>
                <div style="font-size:8.5px; color:{{ $th['sub'] }}; margin-top:2px;">{{ $data['codigo'] }}</div>
            </td>
        </tr></table>
    </td></tr>
    <tr><td style="height:44px; padding:0; line-height:0; vertical-align:bottom;"><img src="{{ $data['images']['carnetWave'] }}" alt="" style="width:324px; height:30px; display:block;"></td></tr>
</table>
</div>
