{{-- Controles de estilo del QR (editor de destino y QR libre). Comparten el estado `style` del componente Alpine del centro de QR. --}}
<div class="qrc-pills" role="radiogroup" aria-label="Estilo del QR">
    <button type="button" class="qrc-pill" role="radio" x-bind:aria-pressed="style.preset === 'marca'" x-bind:aria-checked="style.preset === 'marca'" x-on:click="applyPreset('marca')">Marca Tu Doctor en Casa</button>
    <button type="button" class="qrc-pill" role="radio" x-bind:aria-pressed="style.preset === 'clasico'" x-bind:aria-checked="style.preset === 'clasico'" x-on:click="applyPreset('clasico')">Clásico</button>
    <button type="button" class="qrc-pill" role="radio" x-bind:aria-pressed="style.preset === 'contraste'" x-bind:aria-checked="style.preset === 'contraste'" x-on:click="applyPreset('contraste')">Alto contraste</button>
</div>
<details class="qrc-adv">
    <summary>Ajustes avanzados: colores, forma y logo</summary>
    <div class="qrc-adv-body">
        <div class="qrc-field">
            <label for="qrc-{{ $prefix }}-dot">Color de los puntos</label>
            <div class="qrc-color">
                <input id="qrc-{{ $prefix }}-dot" type="color" x-model="style.dotColor" x-on:input="style.preset = 'personalizado'">
                <span class="qrc-mono qrc-sub" x-text="style.dotColor"></span>
            </div>
        </div>
        <div class="qrc-field">
            <label for="qrc-{{ $prefix }}-bg">Color de fondo</label>
            <div class="qrc-color">
                <input id="qrc-{{ $prefix }}-bg" type="color" x-model="style.bgColor" x-on:input="style.preset = 'personalizado'">
                <span class="qrc-mono qrc-sub" x-text="style.bgColor"></span>
            </div>
        </div>
        <div class="qrc-field">
            <label for="qrc-{{ $prefix }}-dots">Forma de los puntos</label>
            <select id="qrc-{{ $prefix }}-dots" class="qrc-select" x-model="style.dotStyle" x-on:change="style.preset = 'personalizado'">
                <option value="square">Cuadrados</option>
                <option value="dots">Círculos</option>
                <option value="rounded">Redondeados</option>
                <option value="extra-rounded">Muy redondeados</option>
                <option value="classy">Elegante</option>
                <option value="classy-rounded">Elegante redondeado</option>
            </select>
        </div>
        <div class="qrc-field">
            <label for="qrc-{{ $prefix }}-corner">Esquinas</label>
            <select id="qrc-{{ $prefix }}-corner" class="qrc-select" x-model="style.cornerSquareStyle" x-on:change="style.preset = 'personalizado'">
                <option value="square">Cuadradas</option>
                <option value="extra-rounded">Redondeadas</option>
                <option value="dot">Circulares</option>
            </select>
        </div>
        <div class="qrc-field">
            <label for="qrc-{{ $prefix }}-corner-dot">Centro de las esquinas</label>
            <select id="qrc-{{ $prefix }}-corner-dot" class="qrc-select" x-model="style.cornerDotStyle" x-on:change="style.preset = 'personalizado'">
                <option value="square">Cuadrado</option>
                <option value="dot">Círculo</option>
            </select>
        </div>
        <div class="qrc-field">
            <span class="qrc-label">Logo en el centro</span>
            <label for="qrc-{{ $prefix }}-logo" style="display: flex; align-items: center; gap: 8px; min-height: 44px; font-weight: 500;">
                <input id="qrc-{{ $prefix }}-logo" type="checkbox" x-model="style.logo" style="width: 18px; height: 18px;">
                Mostrar logo
            </label>
        </div>
        <div class="qrc-field" x-show="style.logo">
            <label for="qrc-{{ $prefix }}-logo-size">Tamaño del logo: <span x-text="Math.round(style.logoSize * 100) + ' %'"></span></label>
            <input id="qrc-{{ $prefix }}-logo-size" type="range" min="0.1" x-bind:max="config.logoMax" step="0.01" x-model.number="style.logoSize">
            <span class="qrc-hint">Hasta el <span x-text="Math.round(config.logoMax * 100)"></span> %: más grande, los móviles dejan de leerlo.</span>
        </div>
        <div class="qrc-field" x-show="style.logo">
            <label for="qrc-{{ $prefix }}-logo-file">Otro logo (opcional)</label>
            <input id="qrc-{{ $prefix }}-logo-file" type="file" accept="image/png,image/jpeg,image/webp" x-on:change="readLogo($event)" class="qrc-hint">
            <button type="button" class="qrc-btn" x-show="style.customLogo" x-on:click="style.customLogo = null">Usar el logo de la marca</button>
        </div>
    </div>
</details>
