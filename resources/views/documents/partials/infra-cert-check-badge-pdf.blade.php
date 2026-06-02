@if (filled($infraCheckBadgeDataUri ?? null))
    <img
        src="{{ $infraCheckBadgeDataUri }}"
        width="18"
        height="18"
        alt="Sí"
        class="infra-cert-badge-img"
    />
@else
    <table cellpadding="0" cellspacing="0" align="center" class="infra-cert-badge-fallback">
        <tr>
            <td class="infra-cert-badge-fallback-cell" title="Sí">&#10004;</td>
        </tr>
    </table>
@endif
