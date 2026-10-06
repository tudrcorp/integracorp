{{--
    Certificado de afiliación y carnets — diseño «Voucher Tu Dr en Casa».

    Maquetado para DomPDF (hoja carta, 816×1056 px a 96 ppp): todo con tablas y
    anchos fijos, sin flex ni grid; las imágenes van como data URI; el logo cambia
    de archivo según el fondo porque DomPDF no aplica filtros CSS. La paginación la
    decide CertificateDocumentData: cada página tiene alto fijo y su pie va anclado
    abajo, así DomPDF nunca parte un bloque.
--}}
@php
    $th = $data['th'];
    $img = $data['images'];
    $total = $data['totalPaginas'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Certificado {{ $data['codigo'] }}</title>
<style>
    @page { size: letter; margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0; font-family: Helvetica, Arial, sans-serif; color: #12293A; font-size: 12px; }
    table { border-collapse: collapse; }
    td { padding: 0; vertical-align: top; }
    .page { position: relative; width: 816px; height: 1055px; overflow: hidden; page-break-after: always; }
    .page.last { page-break-after: auto; }
    .frame { width: 816px; }
    .frame-cell { padding: 30px 48px 0 48px; }
    .eyebrow { font-size: 10px; font-weight: bold; letter-spacing: 1.6px; text-transform: uppercase; color: #2D89CA; }
    .k { font-size: 9.5px; letter-spacing: 1px; text-transform: uppercase; color: #64748B; }
    .v { font-size: 12.5px; font-weight: bold; }
    .muted { color: #64748B; }
    .soft { color: #35657D; }
    .h-section { font-size: 15px; font-weight: bold; }
    .footer { position: absolute; left: 48px; bottom: 26px; width: 720px; border-top: 1px solid #EFEFEF; padding-top: 10px; font-size: 9.5px; line-height: 1.5; color: #64748B; }
    .seal-block { position: absolute; left: 52px; width: 712px; }
    .box { border: 1px solid #EFEFEF; border-radius: 10px; }
    .stamp { border: 2.5px solid #333333; color: #333333; padding: 5px 12px; text-align: center; transform: rotate(-6deg); display: inline-block; }
    .card-cut { border: 1px dashed #AEC2CB; border-radius: 14px; padding: 3px; }
</style>
</head>
<body>
@foreach ($data['pages'] as $pg)
<div class="page{{ $loop->last ? ' last' : '' }}">
<table class="frame"><tr><td class="frame-cell">

    @if ($pg['isCert'])
        {{-- Encabezado del certificado --}}
        <table style="width:720px;"><tr>
            <td style="width:360px; vertical-align:bottom;"><img src="{{ $img['logo'] }}" alt="Tu Doctor en Casa" style="height:42px;"></td>
            <td style="width:360px; text-align:right; vertical-align:bottom;">
                <div class="eyebrow">Certificado de afiliación</div>
                <div style="font-size:22px; font-weight:bold; letter-spacing:0.4px; margin-top:4px;">{{ $data['codigo'] }}</div>
            </td>
        </tr></table>

        {{-- Franja del plan --}}
        <table style="width:720px; margin-top:14px; background:{{ $th['bg'] }}; border-radius:16px; color:{{ $th['ink'] }};">
            <tr><td style="padding:16px 28px 0 28px;">
                <table style="width:664px;"><tr>
                    <td style="width:400px;">
                        <span style="font-size:10px; font-weight:bold; letter-spacing:1.6px; text-transform:uppercase; color:{{ $th['sub'] }};">Plan</span>
                        <span style="margin-left:8px; padding:3px 10px; border-radius:9px; background:#E6F6EE; color:#00703C; font-size:10px; font-weight:bold; letter-spacing:0.8px; text-transform:uppercase;"><span style="font-family:'DejaVu Sans', sans-serif;">&#9679;</span> Activo</span>
                        <div style="font-size:38px; line-height:1; font-weight:bold; color:{{ $th['title'] }}; margin-top:8px;">{{ $data['planNombre'] }}</div>
                        <div style="font-size:12px; color:{{ $th['sub'] }}; margin-top:6px;">{{ $data['heroSub'] }}</div>
                    </td>
                    <td style="width:264px; text-align:right; padding-top:4px;">
                        <div style="font-size:10px; font-weight:bold; letter-spacing:1.6px; text-transform:uppercase; color:{{ $th['sub'] }};">{{ $data['heroLabel'] }}</div>
                        <div style="font-size:30px; line-height:1; font-weight:bold; color:{{ $th['value'] }}; margin-top:6px;">{{ $data['heroValor'] }}</div>
                        <div style="font-size:11px; color:{{ $th['sub'] }}; margin-top:6px;">{{ $data['heroNota'] }}</div>
                    </td>
                </tr></table>
            </td></tr>
            <tr><td style="padding:10px 28px 0 28px;">
                <table style="width:664px; border-top:1px solid {{ $th['divider'] }};"><tr>
                    @foreach ([['Vigencia desde', $data['desde']], ['Vigencia hasta', $data['hasta']], ['Período facturado', $data['periodoFacturado']], ['Cobertura', 'Local · Venezuela']] as [$label, $value])
                        <td style="width:166px; padding-top:8px;">
                            <div style="font-size:9.5px; letter-spacing:1px; text-transform:uppercase; color:{{ $th['sub'] }};">{{ $label }}</div>
                            <div style="font-size:13px; font-weight:bold; margin-top:4px; white-space:nowrap;">{{ $value }}</div>
                        </td>
                    @endforeach
                </tr></table>
            </td></tr>
            <tr><td style="padding:0; line-height:0;"><img src="{{ $img['heroWave'] }}" alt="" style="width:720px; height:34px; display:block;"></td></tr>
        </table>

        {{-- Datos del contrato --}}
        <table style="width:720px; margin-top:14px;">
            @foreach ([[['Contratante', $data['contratante']], ['C.I. / RIF del contratante', $data['contratanteId']], ['Agente', $data['agente']]], [['Tarifa anual', $data['tarifaAnual']], ['Fecha de afiliación inicial', $data['fechaAfiliacion']], ['Fecha de emisión', $data['emision']]]] as $row)
                <tr>
                    @foreach ($row as [$label, $value])
                        <td style="width:232px; padding:0 4px 8px 4px;"><div class="k">{{ $label }}</div><div class="v" style="margin-top:3px;">{{ $value }}</div></td>
                    @endforeach
                </tr>
            @endforeach
        </table>

        @if ($data['esIndividual'])
            <div class="h-section" style="margin-top:6px;">Afiliado</div>
            <table class="box" style="width:720px; margin-top:8px;">
                <tr style="background:#F6F6F6;">
                    <td class="k" style="width:298px; padding:8px 16px;">Nombre y apellido</td>
                    <td class="k" style="width:140px; padding:8px 0;">Documento</td>
                    <td class="k" style="width:130px; padding:8px 0;">Nacimiento</td>
                    <td class="k" style="width:120px; padding:8px 0;">Parentesco</td>
                </tr>
                @foreach ($data['beneficiarios'] as $b)
                    <tr>
                        <td style="padding:7px 16px; border-top:1px solid #EFEFEF; font-weight:bold;">{{ $b['nombre'] }}</td>
                        <td style="padding:7px 0; border-top:1px solid #EFEFEF;">{{ $b['docFmt'] }}</td>
                        <td style="padding:7px 0; border-top:1px solid #EFEFEF;">{{ $b['nac'] }}</td>
                        <td style="padding:7px 0; border-top:1px solid #EFEFEF;">{{ $b['parentesco'] }}</td>
                    </tr>
                @endforeach
            </table>
        @else
            <table style="width:720px; margin-top:4px; background:#F6F6F6; border-radius:10px;"><tr>
                <td style="width:100px; padding:12px 0 12px 18px; white-space:nowrap; font-size:32px; line-height:1; font-weight:bold; color:#2D89CA;">{{ $data['nAfiliados'] }}</td>
                <td style="width:584px; padding:12px 18px 12px 0;">
                    <div style="font-size:14px; font-weight:bold;">{{ $data['grupoTitulo'] }}</div>
                    <div class="soft" style="font-size:11px; margin-top:2px;">La relación completa de afiliados se encuentra disponible en {{ $data['relPaginas'] }}.</div>
                </td>
            </tr></table>
        @endif
    @else
        {{-- Encabezado de páginas interiores --}}
        <table style="width:720px; border-bottom:1px solid #EFEFEF;"><tr>
            <td style="width:420px; padding-bottom:12px; vertical-align:bottom;">
                <div class="eyebrow">{{ $data['codigo'] }} · Plan {{ $data['planNombre'] }}</div>
                <div style="font-size:22px; font-weight:bold; margin-top:3px;">{{ $pg['title'] }}</div>
            </td>
            <td style="width:300px; padding-bottom:12px; vertical-align:bottom; text-align:right; font-size:10.5px;" class="muted">{{ $pg['note'] }}</td>
        </tr></table>
    @endif

    @if ($pg['hasBenef'])
        <table style="width:720px; margin-top:14px;"><tr>
            <td class="h-section" style="width:520px;">{{ $data['relationPlanColumn'] ? 'Beneficios de los planes contratados' : 'Beneficios del plan '.$data['planNombre'] }}{{ $pg['isCert'] ? '' : ' (cont.)' }}</td>
            <td class="muted" style="width:200px; text-align:right; font-size:10.5px; vertical-align:bottom;">{{ $data['nBeneficios'] }} beneficios incluidos</td>
        </tr></table>
        <table style="width:720px; margin-top:8px; border-bottom:1px solid #E4ECEF;">
            <tr>
                <td class="k" style="width:530px; padding:0 12px 6px 12px; border-bottom:2px solid #26B4E8;">Beneficio</td>
                <td class="k" style="width:154px; padding:0 12px 6px 0; border-bottom:2px solid #26B4E8;">Cobertura</td>
            </tr>
            @foreach ($pg['rows'] as $it)
                @if (isset($it['section']))
                    <tr><td colspan="2" style="height:16px; padding:3px 12px 1px 12px; font-size:10px; font-weight:bold; letter-spacing:1px; text-transform:uppercase; color:#2D89CA; vertical-align:bottom;">{{ $it['section'] }}</td></tr>
                    @continue
                @endif
                <tr style="background:{{ $loop->odd ? '#F6F6F6' : '#FFFFFF' }};">
                    <td style="height:18px; padding:1px 12px; font-size:11.5px; line-height:1.3; vertical-align:middle;">{{ $it['t'] }}</td>
                    <td style="height:18px; padding:1px 12px 1px 0; font-size:11.5px; line-height:1.3; vertical-align:middle; font-weight:bold; color:{{ $it['limited'] ? '#2D89CA' : '#12293A' }};">{{ $it['cob'] }}</td>
                </tr>
            @endforeach
        </table>
        @if ($pg['hasSeal'] && $data['preexNote'])
            <div class="muted" style="font-size:9.5px; line-height:1.4; margin-top:8px;">* Asistencia médica por emergencia: aplica para patologías listadas no preexistentes. El plan excluye enfermedades preexistentes.</div>
        @endif
    @endif

    @if ($pg['kind'] === 'rel')
        <table class="box" style="width:720px; margin-top:14px;">
            <tr style="background:#F6F6F6;">
                <td class="k" style="width:34px; padding:7px 0 7px 14px;">N.º</td>
                <td class="k" style="width:300px; padding:7px 0;">Nombre y apellido</td>
                <td class="k" style="width:132px; padding:7px 0;">Documento</td>
                <td class="k" style="width:120px; padding:7px 0;">Nacimiento</td>
                <td class="k" style="width:120px; padding:7px 0;">{{ $data['relationPlanColumn'] ? 'Plan' : 'Parentesco' }}</td>
            </tr>
            @foreach ($pg['people'] as $b)
                <tr>
                    <td class="muted" style="height:21px; padding:0 0 0 14px; border-top:1px solid #EFEFEF; font-size:11px; vertical-align:middle;">{{ $b['num'] }}</td>
                    <td style="height:21px; border-top:1px solid #EFEFEF; font-size:11px; font-weight:bold; vertical-align:middle;">{{ \App\Support\Affiliations\Certificates\CertificateFormat::short($b['nombre']) }}</td>
                    <td style="height:21px; border-top:1px solid #EFEFEF; font-size:11px; vertical-align:middle;">{{ $b['docFmt'] }}</td>
                    <td style="height:21px; border-top:1px solid #EFEFEF; font-size:11px; vertical-align:middle;">{{ $b['nac'] }}</td>
                    <td style="height:21px; border-top:1px solid #EFEFEF; font-size:11px; vertical-align:middle;">{{ $b['parentesco'] }}</td>
                </tr>
            @endforeach
        </table>
        @if ($pg['lastRel'])
            @include('documents.affiliation-certificate.partials.conditions')
        @endif
    @endif

    @if ($pg['kind'] === 'carnet')
        @foreach ($pg['people'] as $b)
            <table style="width:720px; margin-top:{{ $loop->first ? 14 : 10 }}px;"><tr>
                <td style="width:360px; text-align:center;">
                    @include('documents.affiliation-certificate.partials.carnet-front', ['b' => $b])
                </td>
                <td style="width:360px; text-align:center;">
                    @include('documents.affiliation-certificate.partials.carnet-back')
                </td>
            </tr></table>
        @endforeach

        @if ($pg['hasInfo'])
            @include('documents.affiliation-certificate.partials.carnet-guide')
        @endif
    @endif

</td></tr></table>

@if ($pg['hasSeal'] || $pg['lastRel'] || $pg['hasInfo'])
    <div class="seal-block" style="bottom:{{ $pg['isCert'] ? 92 : 62 }}px;">
        @include('documents.affiliation-certificate.partials.signature', ['withStamp' => ! $pg['hasInfo'] && $data['pagado']])
    </div>
@endif

<div class="footer">
    <table style="width:720px;"><tr>
        @if ($pg['isCert'])
            <td style="width:500px;">
                <strong>Tu Doctor en Casa, C.A. · RIF J-50358368-1</strong><br>
                Av. Francisco de Miranda, Centro Lido, Torre A, piso 12, of. 124, El Rosal, Caracas.<br>
                Los servicios de este plan se rigen por las Condiciones Generales, disponibles en tudrencasa.com.
            </td>
            <td style="width:220px; text-align:right; vertical-align:bottom;">
                Verificación: {{ $data['verificationKey'] }}<br>Página {{ $pg['num'] }} de {{ $total }}
            </td>
        @else
            <td style="width:560px; vertical-align:bottom;"><strong>Tu Doctor en Casa, C.A. · RIF J-50358368-1</strong> · InfoChat +58 424-227 14 98 · tudrencasa.com</td>
            <td style="width:160px; text-align:right; vertical-align:bottom;">Página {{ $pg['num'] }} de {{ $total }}</td>
        @endif
    </tr></table>
</div>
</div>
@endforeach
</body>
</html>
