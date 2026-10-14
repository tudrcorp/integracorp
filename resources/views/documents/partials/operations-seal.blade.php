{{-- Sello de Tu Doctor en Casa al cierre de los documentos de Operaciones. Una tabla de una fila con ancho fijo: DomPDF la mueve entera a la hoja siguiente si no cabe, sin partir la imagen. --}}
@if(($sealDataUri ?? '') !== '')
    <table class="operations-seal" style="width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 14px; page-break-inside: avoid;">
        <tr>
            <td style="width: 65%; padding: 0; border: 0;"></td>
            <td style="width: 35%; padding: 0; border: 0; text-align: center; vertical-align: top;">
                <img src="{{ $sealDataUri }}" alt="Sello de Tu Doctor en Casa" style="width: 32mm; height: auto;">
            </td>
        </tr>
    </table>
@endif
