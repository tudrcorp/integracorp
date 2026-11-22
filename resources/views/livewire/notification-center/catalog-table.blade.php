<div class="ncat">
    <div class="ncat-intro">
        <p>
            <b>Encienda o apague cada notificación con un clic.</b>
            Se aplica al instante y cada intento bloqueado queda en la Bitácora. Use el menú ⋯ para ver sus envíos o configurar destinatarios.
        </p>
        <div class="ncat-legend" aria-label="Leyenda">
            <span><x-filament::icon icon="heroicon-m-building-office-2" /> Interna: personal TDG</span>
            <span><x-filament::icon icon="heroicon-m-globe-alt" /> Externa: clientes y red</span>
            <span><x-filament::icon icon="heroicon-m-lock-closed" /> Crítica: no se apaga por canal</span>
        </div>
    </div>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
