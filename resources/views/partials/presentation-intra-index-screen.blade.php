@php
    $logo = $logo ?? asset('image/logoNewTDG.png');
@endphp

<div class="intra-index">
    <header class="intra-index__mast">
        <div>
            <img src="{{ $logo }}" alt="Tu Dr Group" class="intra-index__logo" width="180" height="44">
            <h3>Índice de portales</h3>
            <p>Los siete accesos internos de TUDR Group, en producción y en desarrollo.</p>
        </div>
        <p class="intra-index__stamp"><strong>Hola, equipo</strong>intra.tudrgroup.com</p>
    </header>

    <div class="intra-index__bar">
        <span class="intra-index__search">Buscar portal, URL o descripción…</span>
        <span class="intra-index__chip is-active">Todos 7</span>
        <span class="intra-index__chip intra-index__chip--ok">Producción 4</span>
        <span class="intra-index__chip intra-index__chip--warn">Desarrollo 3</span>
    </div>

    <div class="intra-index__cols">
        <section class="intra-index__group">
            <div class="intra-index__head">
                <h4><i class="intra-index__dot intra-index__dot--ok"></i>Producción</h4>
                <span>4</span>
            </div>
            <ol class="intra-index__rows">
                <li>
                    <b>1</b>
                    <span>Tecnología y Sistemas</span>
                    <em>Web</em>
                    <code><strong>integracorp</strong>.tudrgroup.com/dpto-tecnologia-sistemas</code>
                </li>
                <li>
                    <b>2</b>
                    <span>API · Documentación</span>
                    <em>API</em>
                    <code><strong>integracorp-api</strong>.tudrgroup.com/docs/</code>
                </li>
                <li>
                    <b>3</b>
                    <span>Marketing</span>
                    <em>Web</em>
                    <code><strong>marketing</strong>.tudrgroup.com</code>
                </li>
                <li>
                    <b>4</b>
                    <span>Integracorp App</span>
                    <em>PWA</em>
                    <code><strong>integracorp</strong>.tudrgroup.com/app</code>
                </li>
            </ol>
        </section>

        <section class="intra-index__group">
            <div class="intra-index__head">
                <h4><i class="intra-index__dot intra-index__dot--warn"></i>Desarrollo</h4>
                <span>3</span>
            </div>
            <ol class="intra-index__rows">
                <li>
                    <b>5</b>
                    <span>Integracorp</span>
                    <em>Web</em>
                    <code><strong>integracorp</strong>.dev.tudrgroup.com</code>
                </li>
                <li>
                    <b>6</b>
                    <span>Integracorp API</span>
                    <em>API</em>
                    <code><strong>integracorp-api</strong>.dev.tudrgroup.com</code>
                </li>
                <li>
                    <b>7</b>
                    <span>Marketing</span>
                    <em>Web</em>
                    <code><strong>marketing</strong>.dev.tudrgroup.com</code>
                </li>
            </ol>
        </section>
    </div>
</div>
