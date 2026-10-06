{{-- Guía de uso al pie de la última hoja de carnets. --}}
<table style="width:720px; margin-top:18px;"><tr>
    <td style="width:200px; background:#F6F6F6; border-radius:16px; padding:13px 16px;">
        <table><tr><td style="width:30px; height:30px; background:#26B4E8; border-radius:15px; text-align:center; vertical-align:middle; font-family:'DejaVu Sans', sans-serif; font-size:14px; font-weight:bold; color:#0B3D52;">+</td></tr></table>
        <div style="font-size:13.5px; font-weight:bold; margin-top:6px;">Cómo pedir tu médico</div>
        <div class="soft" style="font-size:11px; line-height:1.5; margin-top:6px;">Escribe a MediChat con tu nombre y cédula. Un médico te atiende por teléfono y, si hace falta, coordinamos la visita a tu casa.</div>
    </td>
    <td style="width:12px;"></td>
    <td style="width:200px; background:#F6F6F6; border-radius:16px; padding:13px 16px;">
        <table><tr><td style="width:30px; height:30px; background:#26B4E8; border-radius:15px; text-align:center; vertical-align:middle; font-family:'DejaVu Sans', sans-serif; font-size:14px; font-weight:bold; color:#0B3D52;">&#9684;</td></tr></table>
        <div style="font-size:13.5px; font-weight:bold; margin-top:6px;">Períodos de espera</div>
        <table class="soft" style="width:200px; font-size:11px; margin-top:6px;">
            @foreach ([['Telemedicina', 'Inmediata'], ['Atención en sitio', '8 días'], ['Centros de salud', 'A consultar']] as [$label, $value])
                <tr><td style="width:120px; padding-bottom:3px;">{{ $label }}</td><td style="width:80px; text-align:right; font-weight:bold; color:#12293A;">{{ $value }}</td></tr>
            @endforeach
        </table>
    </td>
    <td style="width:12px;"></td>
    <td style="width:200px; background:#F6F6F6; border-radius:16px; padding:13px 16px;">
        <table><tr><td style="width:30px; height:30px; background:#26B4E8; border-radius:15px; text-align:center; vertical-align:middle; font-family:'DejaVu Sans', sans-serif; font-size:14px; font-weight:bold; color:#0B3D52;">&#10003;</td></tr></table>
        <div style="font-size:13.5px; font-weight:bold; margin-top:6px;">Tu plan, en regla</div>
        <div class="soft" style="font-size:11px; line-height:1.5; margin-top:6px;">Este certificado es válido mientras el período esté pagado.</div>
    </td>
</tr></table>
<div class="soft" style="font-size:11px; line-height:1.5; text-align:center; margin:12px 50px 0 50px;">Recomendamos que nuestro afiliado conserve su tarjeta cerca de sus documentos personales. Este carnet no es un requisito obligatorio para solicitar el servicio: si el cliente presenta una eventualidad, puede identificarse con su nombre y número de cédula.</div>
<div class="soft" style="font-size:11px; line-height:1.5; text-align:center; margin:6px 50px 0 50px;">Los servicios incluidos en este producto se encuentran regidos, amparados y aprobados por las <strong style="text-decoration:underline; color:#12293A;">Condiciones Especiales</strong>.</div>
