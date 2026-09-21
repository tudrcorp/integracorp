@php
    /** @var callable $val */
    $stampDataUri = $stampDataUri ?? \App\Support\Telemedicine\TelemedicineDoctorStamp::dataUri($data['signature'] ?? null);
    $doctorName = trim((string) ($data['doctor_name'] ?? ''));
@endphp
<table class="grid doctor-block">
    <tr>
        <td>
            @if($doctorName !== '')
                <div class="label">Médico</div>
                <div class="value">{{ $val($doctorName) }}</div>
            @endif
            <div class="label">Colegio médico</div>
            <div class="value-muted">{{ $val($data['code_cm'] ?? null) }}</div>
            <div class="label">MPPS</div>
            <div class="value-muted">{{ $val($data['code_mpps'] ?? null) }}</div>
        </td>
        <td class="stamp-cell">
            @if($stampDataUri !== '')
                <div class="label">Sello digital</div>
                <img class="doctor-stamp" src="{{ $stampDataUri }}" alt="Sello del médico">
            @endif
        </td>
    </tr>
</table>
