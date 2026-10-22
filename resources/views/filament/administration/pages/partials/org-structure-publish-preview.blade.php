{{-- Resumen previo a enviar o publicar un borrador del organigrama. --}}
<div style="display: grid; gap: .9rem; font-size: .9rem;">
    @if ($preview['base_changed'])
        <div style="padding: .65rem .8rem; border-radius: .6rem; background: rgba(220, 38, 38, .1); color: rgb(185, 28, 28);">
            El organigrama vigente cambió desde que se creó este borrador. Crea un borrador nuevo desde la versión vigente y repite los cambios.
        </div>
    @endif

    @if ($preview['errors'] !== [])
        <div style="padding: .65rem .8rem; border-radius: .6rem; background: rgba(220, 38, 38, .1); color: rgb(185, 28, 28);">
            <strong>Hay que corregir antes de continuar:</strong>
            <ul style="margin: .35rem 0 0; padding-left: 1.1rem;">
                @foreach (array_slice($preview['errors'], 0, 12) as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            @if (count($preview['errors']) > 12)
                <div style="margin-top: .25rem;">Y {{ count($preview['errors']) - 12 }} más. Revísalos en «Revisión antes de publicar».</div>
            @endif
        </div>
    @else
        <div style="padding: .65rem .8rem; border-radius: .6rem; background: rgba(22, 163, 74, .1); color: rgb(21, 128, 61);">
            La estructura es válida: un solo puesto principal, sin ciclos y sin ocupantes inactivos.
        </div>
    @endif

    @if ($preview['warnings'] !== [])
        <div>
            <strong>Avisos (no impiden publicar):</strong>
            <ul style="margin: .35rem 0 0; padding-left: 1.1rem; opacity: .85;">
                @foreach (array_slice($preview['warnings'], 0, 8) as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($includeRrhh)
        @if ($preview['rrhh']['changes'] !== [])
            <div>
                <strong>Personas cuyo cargo en RRHH no coincide con su nuevo puesto ({{ count($preview['rrhh']['changes']) }}):</strong>
                <div style="max-height: 14rem; overflow-y: auto; margin-top: .35rem; border: 1px solid rgba(148, 163, 184, .35); border-radius: .5rem;">
                    <table style="width: 100%; border-collapse: collapse; font-size: .85rem;">
                        <thead>
                            <tr style="text-align: left; opacity: .7;">
                                <th style="padding: .4rem .6rem;">Persona</th>
                                <th style="padding: .4rem .6rem;">Hoy en RRHH</th>
                                <th style="padding: .4rem .6rem;">Pasaría a</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($preview['rrhh']['changes'] as $change)
                                <tr style="border-top: 1px solid rgba(148, 163, 184, .25);">
                                    <td style="padding: .4rem .6rem;">{{ $change['name'] }}</td>
                                    <td style="padding: .4rem .6rem;">{{ $change['from_cargo'] ?? 'Sin cargo' }}{{ $change['from_depto'] ? ' · '.$change['from_depto'] : '' }}</td>
                                    <td style="padding: .4rem .6rem;">{{ $change['to_cargo'] }}{{ $change['to_depto'] ? ' · '.$change['to_depto'] : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <p style="margin: 0; opacity: .75;">Nadie cambia de cargo en RRHH con esta versión.</p>
        @endif

        @foreach ($preview['rrhh']['skipped'] as $skipped)
            <p style="margin: 0; opacity: .75;">{{ $skipped }}</p>
        @endforeach
    @endif
</div>
