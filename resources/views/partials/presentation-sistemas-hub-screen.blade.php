@php
    $variant = $variant ?? 'phone';
    $heroImage = $heroImage ?? asset('image/presentaciones-sistemas-bg.png');
    $markImage = $markImage ?? asset('image/imagotipo.png');
@endphp

<div class="sys-hub sys-hub--{{ $variant }}">
    <div class="sys-hub__visual">
        <img src="{{ $heroImage }}" alt="" class="sys-hub__photo" width="720" height="1280">
        <span class="sys-hub__shade" aria-hidden="true"></span>
        <div class="sys-hub__chip">
            <img src="{{ $markImage }}" alt="" width="22" height="22">
            <span>Sistemas · TUDRGROUP</span>
        </div>
        <div class="sys-hub__copy">
            <h3>Departamento de Sistemas</h3>
            <p>INTEGRACORP × TUDRGROUP — panel interno de presentaciones y manuales técnicos.</p>
        </div>
    </div>

    @if ($variant === 'phone')
        <div class="sys-hub__login">
            <h3>Verifica tu identidad</h3>
            <p>Ingresa con tu cédula o con un teléfono registrado.</p>
            <div class="sys-hub__tabs">
                <span class="is-active">Cédula</span>
                <span>Teléfono</span>
            </div>
            <div class="sys-hub__field">Ej. 16007868</div>
            <p class="sys-hub__hint">En el sistema figura como V-16007868; solo comparamos los números.</p>
            <div class="sys-hub__btn">Ingresar a la presentación</div>
        </div>
    @else
        <div class="sys-hub__panel">
            <p class="sys-hub__eyebrow">Panel de Sistemas</p>
            <h3>Presentaciones</h3>
            <p class="sys-hub__lead">Sesiones técnicas internas del equipo de sistemas.</p>
            <p class="sys-hub__meta">Solo colaboradores registrados · cierre por inactividad: 10 min</p>
            <article class="sys-hub__card">
                <span>1</span>
                <div>
                    <strong>Presentaciones</strong>
                    <small>Capacitación · 3 recursos</small>
                </div>
            </article>
            <article class="sys-hub__card">
                <span>2</span>
                <div>
                    <strong>Manuales de Tecnología</strong>
                    <small>Documentación · 0 recursos</small>
                </div>
            </article>
            <p class="sys-hub__footer">TUDRGROUP · INTEGRACORP</p>
        </div>
    @endif
</div>
