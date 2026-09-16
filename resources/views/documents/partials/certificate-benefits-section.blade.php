{{--
    Bloque de beneficios del certificado: título, tabla, nota y —cuando es el último
    bloque y cabe— la firma. La plantilla lo envuelve en una celda de tabla para que
    DomPDF no lo parta entre páginas.
--}}
<div class="benefits-block">
    <h2 class="section-title" style="color: {{ $brandColor }};">{{ $sectionTitle }}</h2>

    <table class="table-benefits">
        <tbody>
            @foreach ($section['rows'] as $row)
                <tr>
                    <td>{{ $row['text'] }}</td>
                    <td class="mark">
                        @if ($row['show_cobertura'])
                            <span class="amount" style="color: {{ $isAlliedCertificate ? $brandColor : '#000000' }};">US$ {{ $coberturaFormatted }}</span>
                        @else
                            <img src="{{ public_path('storage/certificados/check-beneficios.png') }}" style="width: 12px; height: 12px;" alt="">
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (filled($section['note'] ?? null))
        <p class="benefit-note">{{ $section['note'] }}</p>
    @endif

    @if ($renderSignature)
        @include('documents.partials.certificate-signature')
    @endif
</div>
