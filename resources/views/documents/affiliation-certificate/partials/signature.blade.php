{{-- Firma autorizada, sello de la empresa y, si el período vigente está pagado, el sello «PAGADO». --}}
<table style="width:712px;"><tr>
    <td style="width:600px; vertical-align:middle;">
        <table style=""><tr>
            <td style="width:200px; padding-top:4px; font-size:11.5px; font-weight:bold; letter-spacing:0.9px; white-space:nowrap;">TU DOCTOR EN CASA, C. A.</td>
            <td style="width:240px; text-align:center; vertical-align:bottom;">
                {{-- La firma se apoya sobre la línea: el margen negativo la monta 12 px por debajo, como en el diseño, sin posición absoluta. --}}
                @if (filled($data['images']['signature'] ?? null))
                    <img src="{{ $data['images']['signature'] }}" alt="Firma autorizada" style="height:62px; width:auto; display:block; margin:0 auto -12px auto;">
                @else
                    <div style="height:16px;"></div>
                @endif
                <div style="height:1px; border-bottom:1px solid #12293A;"></div>
                <div style="font-size:9.5px; color:#64748B; margin-top:4px;">Firma autorizada</div>
            </td>
        </tr></table>
        @if ($withStamp)
            <div style="margin:10px 0 0 24px;">
                <div class="stamp">
                    <div style="font-size:18px; line-height:1; font-weight:bold; letter-spacing:3px;">PAGADO</div>
                    <div style="font-size:8px; letter-spacing:1.1px; text-transform:uppercase; font-weight:bold; margin-top:3px;">{{ $data['periodoFacturado'] }}</div>
                </div>
            </div>
        @endif
    </td>
    <td style="width:112px; text-align:right; vertical-align:middle;">
        <img src="{{ $data['images']['seal'] }}" alt="Sello Tu Doctor en Casa" style="width:{{ $withStamp ? 96 : 104 }}px;">
    </td>
</tr></table>
