@if ($isAlliedCertificate)
    @if ($signatureDataUri !== '')
        <div class="signature allied">
            <img src="{{ $signatureDataUri }}" alt="Firma {{ $companyName }}">
        </div>
    @endif
@else
    <div class="signature">
        <img src="{{ public_path('storage/certificados/firmaHC-Certificados.png') }}" alt="Firma autorizada">
    </div>
@endif
