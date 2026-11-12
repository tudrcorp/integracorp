{{-- Ajustes por inclusión o exclusión de afiliados (prorrateo por días, créditos y reversos). --}}
@foreach ($movementLines as $line)
    <tr>
        <td class="desc-col">
            <p class="plan-line">AJUSTE: {{ \Illuminate\Support\Str::upper($line['description']) }}</p>
        </td>
        <td class="amount-col">{{ $line['amount'] < 0 ? '-' : '' }}US${{ number_format(abs($line['amount']), 2) }}</td>
    </tr>
@endforeach
