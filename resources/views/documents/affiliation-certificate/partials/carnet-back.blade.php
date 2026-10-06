{{-- Reverso del carnet: contactos 24/7 y QR de verificación del certificado. --}}
<div class="card-cut" style="width:332px; margin:0 auto;">
<table style="width:324px; height:204px; background:#FFFFFF; border:1px solid #E4ECEF; border-radius:12px;">
    <tr><td style="height:30px; padding:13px 16px 0 16px;">
        <table style="width:292px;"><tr>
            <td style="width:150px; text-align:left; font-size:12px; font-weight:bold;">Solicita tu médico 24/7</td>
            <td style="width:140px; text-align:right; font-size:13px; font-style:italic; color:#2D89CA;">Humanizamos la salud</td>
        </tr></table>
    </td></tr>
    <tr><td style="height:120px; padding:6px 16px 0 16px;">
        <table style="width:292px;"><tr>
            <td style="width:206px; text-align:left;">
                @foreach ([['MediChat', '+58 424-213 21 12', 'M'], ['Coordinación', '+58 414-901 03 52', 'C']] as [$label, $phone, $initial])
                    <table style="margin-bottom:6px;"><tr>
                        <td style="width:20px; vertical-align:middle;"><table><tr><td style="width:20px; height:20px; background:#26B4E8; border-radius:10px; text-align:center; vertical-align:middle; font-family:'DejaVu Sans', sans-serif; font-size:9px; font-weight:bold; color:#0B3D52;">{{ $initial }}</td></tr></table></td><td style="width:8px;"></td>
                        <td style="vertical-align:middle;">
                            <div style="font-size:7.5px; letter-spacing:0.8px; text-transform:uppercase; color:#64748B;">{{ $label }}</div>
                            <div style="font-size:11px; font-weight:bold;">{{ $phone }}</div>
                        </td>
                    </tr></table>
                @endforeach
                <table><tr>
                    <td style="width:20px; vertical-align:middle;"><table><tr><td style="width:20px; height:20px; background:#26B4E8; border-radius:10px; text-align:center; vertical-align:middle; font-family:'DejaVu Sans', sans-serif; font-size:9px; font-weight:bold; color:#0B3D52;">@</td></tr></table></td><td style="width:8px;"></td>
                    <td style="vertical-align:middle; font-size:11px; font-weight:bold;">24h@tudrencasa.com</td>
                </tr></table>
            </td>
            <td style="width:84px; text-align:center;">
                <img src="{{ $data['images']['qr'] }}" alt="QR de verificación" style="width:66px; height:66px;">
                <div style="font-size:7px; letter-spacing:0.4px; text-transform:uppercase; color:#64748B; line-height:1.2; margin-top:3px;">Escanea para<br>verificar tu plan</div>
            </td>
        </tr></table>
    </td></tr>
    <tr><td style="padding:0 16px 11px 16px; vertical-align:bottom; text-align:left; font-size:7.5px; color:#64748B;">
        <strong>Tu Doctor en Casa, C.A. · RIF J-50358368-1</strong>
    </td></tr>
</table>
</div>
