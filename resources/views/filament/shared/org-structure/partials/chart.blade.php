{{--
    Organigrama interactivo. Lo usan la página de consulta (modo «view», en todos
    los paneles internos), el estudio de Administración (modo «edit») y el ejemplo
    del estudio (modo «edit» con simulador, sin servidor).

    Recibe $config: el árbol y los catálogos que la pantalla necesita. Viaja una vez
    en el HTML; todo lo demás son llamadas Renderless a la página.
--}}
@assets
    <script src="{{ asset('js/org-chart.js') }}?v={{ filemtime(public_path('js/org-chart.js')) }}"></script>
@endassets

@include('filament.shared.org-structure.partials.styles')

<div
    class="oc-root"
    x-data="integracorpOrgChart()"
    x-on:keydown.window="onGlobalKey($event)"
    :class="{ 'oc-is-edit': mode === 'edit' }"
    wire:ignore
>
    <script type="application/json" x-ref="config">{!! json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>

    {{-- Barra superior --}}
    <div class="oc-toolbar">
        <div class="oc-search" x-on:click.outside="searchOpen = false">
            <x-heroicon-o-magnifying-glass class="oc-icon oc-search-icon" />
            <input
                type="search"
                class="oc-input"
                placeholder="Buscar puesto o persona…"
                aria-label="Buscar puesto o persona"
                x-model="search"
                x-on:input="runSearch()"
                x-on:focus="searchOpen = searchResults.length > 0"
                x-on:keydown.enter.prevent="searchResults[0] && pickSearch(searchResults[0].id)"
                x-on:keydown.escape="searchOpen = false"
            >
            <div class="oc-search-results" x-show="searchOpen && search.length >= 2" x-cloak>
                <template x-for="result in searchResults" :key="result.id">
                    <button type="button" class="oc-search-item" x-on:click="pickSearch(result.id)">
                        <span x-text="result.title"></span>
                        <small x-text="result.people"></small>
                    </button>
                </template>
                <div class="oc-search-item" x-show="searchResults.length === 0">
                    <small>Sin resultados para «<span x-text="search"></span>».</small>
                </div>
            </div>
        </div>

        <template x-if="mode === 'view' && data">
            <div class="oc-tabs" role="tablist">
                <button type="button" class="oc-tab" :class="tab === 'explore' && 'oc-tab-on'" x-on:click="tab = 'explore'" role="tab" :aria-selected="tab === 'explore'">Explorar</button>
                <button type="button" class="oc-tab" :class="tab === 'present' && 'oc-tab-on'" x-on:click="tab = 'present'; drawerOpen = false" role="tab" :aria-selected="tab === 'present'">Presentar y exportar</button>
            </div>
        </template>

        <template x-if="mode === 'view' && versions.length > 1">
            <select class="oc-select" aria-label="Versión del organigrama" x-model="versionId" x-on:change="changeVersion()">
                <template x-for="version in versions" :key="version.id">
                    <option :value="version.id" x-text="version.label" :selected="Number(version.id) === Number(versionId)"></option>
                </template>
            </select>
        </template>

        <template x-if="mode === 'edit'">
            <div class="oc-toolbar-group">
                <div class="oc-tabs" role="radiogroup" aria-label="Vista del estudio">
                    <button type="button" class="oc-tab" :class="layout === 'canvas' && 'oc-tab-on'" x-on:click="layout = 'canvas'" role="radio" :aria-checked="layout === 'canvas'">Lienzo</button>
                    <button type="button" class="oc-tab oc-hide-narrow" :class="layout === 'split' && 'oc-tab-on'" x-on:click="layout = 'split'" role="radio" :aria-checked="layout === 'split'">Lienzo + documento</button>
                    <button type="button" class="oc-tab" :class="layout === 'doc' && 'oc-tab-on'" x-on:click="layout = 'doc'" role="radio" :aria-checked="layout === 'doc'">Documento</button>
                </div>
                <button type="button" class="oc-btn" x-show="! readOnly" :class="paletteOpen && 'oc-btn-on'" x-on:click="paletteOpen = ! paletteOpen" :aria-pressed="paletteOpen">
                    <x-heroicon-o-squares-plus class="oc-icon" />
                    Paleta de RRHH
                </button>
                <button type="button" class="oc-btn" x-show="cfg.hasBase" :class="compareOn && 'oc-btn-on'" x-on:click="toggleCompare()">
                    <x-heroicon-o-arrows-right-left class="oc-icon" />
                    <span x-text="compareOn ? 'Comparando' : 'Comparar'"></span>
                </button>
                <button type="button" class="oc-btn oc-icon-btn" x-show="! readOnly" :disabled="undoStack.length === 0" x-on:click="undo()" title="Deshacer (Ctrl+Z)" aria-label="Deshacer">
                    <x-heroicon-o-arrow-uturn-left class="oc-icon" />
                </button>
            </div>
        </template>

        <span class="oc-spacer"></span>

        <button type="button" class="oc-cmdk" x-on:click="openCommand()" aria-label="Buscar o hacer algo (Ctrl+K)">
            <span>Buscar o hacer algo…</span>
            <kbd>Ctrl K</kbd>
        </button>

        <template x-if="mode === 'view' && data">
            <div class="oc-toolbar-group">
                <span class="oc-chip"><span x-text="stats.positions"></span>&nbsp;puestos</span>
                <span class="oc-chip"><span x-text="stats.people"></span>&nbsp;personas</span>
            </div>
        </template>

        <template x-if="mode === 'view' && cfg.studioUrl">
            <a class="oc-btn" :href="cfg.studioUrl" wire:navigate>
                <x-heroicon-o-pencil-square class="oc-icon" />
                Editar estructura
            </a>
        </template>

        <template x-if="mode === 'edit'">
            <div class="oc-toolbar-group">
                <button type="button" class="oc-btn oc-icon-btn" x-on:click="helpOpen = true" title="Atajos de teclado (?)" aria-label="Atajos de teclado">?</button>
                <button type="button" class="oc-btn oc-btn-primary" x-show="! readOnly || demo" x-on:click="reviewOpen = true">
                    <x-heroicon-o-check-badge class="oc-icon" />
                    Revisar y publicar
                </button>
            </div>
        </template>
    </div>

    {{-- Avisos de contexto --}}
    <template x-if="demo">
        <div class="oc-banner oc-callout-blue">
            <strong>Ejemplo:</strong> prueba todo con libertad, nada se guarda. Cuando quieras, vuelve a «Todos los borradores» y crea el tuyo.
        </div>
    </template>

    <template x-if="readOnlyReason && mode === 'edit' && ! demo">
        <div class="oc-banner oc-callout-amber" x-text="readOnlyReason"></div>
    </template>

    <template x-if="cfg.reviewNotes">
        <div class="oc-banner oc-callout-red">
            <strong>Devuelto para corregir:</strong> <span x-text="cfg.reviewNotes"></span>
        </div>
    </template>

    <template x-if="coach && mode === 'edit' && ! readOnly">
        <div class="oc-coach">
            <span><strong>«+»</strong> bajo una tarjeta agrega un puesto debajo; el de la derecha, un staff.</span>
            <span><strong>Arrastra</strong> desde la paleta un área, un cargo o una persona sobre un puesto.</span>
            <span>En el documento, <kbd>Enter</kbd> crea el siguiente, <kbd>Tab</kbd> lo baja, <code>@</code> asigna una persona.</span>
            <button type="button" class="oc-btn oc-btn-sm" x-on:click="dismissCoach()">Entendido</button>
        </div>
    </template>

    {{-- Consulta sin organigrama publicado --}}
    <template x-if="mode === 'view' && (! data || (data.nodes || []).length === 0)">
        <div class="oc-empty">
            <x-heroicon-o-rectangle-group class="oc-icon" style="width: 40px; height: 40px;" />
            <h3>Todavía no hay un organigrama publicado</h3>
            <p style="max-width: 46ch; margin: 0;">Cuando RRHH publique la estructura de la empresa la verás aquí, con cada puesto, quién lo ocupa y a quién reporta.</p>
            <template x-if="cfg.studioUrl">
                <a class="oc-btn oc-btn-primary" :href="cfg.studioUrl" wire:navigate>Crear el organigrama</a>
            </template>
        </div>
    </template>

    <div
        class="oc-body"
        :class="mode === 'edit' && ['oc-body-edit', 'oc-layout-' + layout, paletteOpen && ! readOnly ? 'oc-with-palette' : '']"
        x-show="mode === 'edit' || (data && (data.nodes || []).length > 0)"
    >
        {{-- Paleta de RRHH (estudio) --}}
        <template x-if="mode === 'edit' && paletteOpen && ! readOnly">
            <aside class="oc-palette" aria-label="Paleta de RRHH">
                <div class="oc-palette-tabs" role="tablist">
                    <button type="button" :class="paletteTab === 'areas' && 'oc-on'" x-on:click="paletteTab = 'areas'" role="tab">Áreas</button>
                    <button type="button" :class="paletteTab === 'cargos' && 'oc-on'" x-on:click="paletteTab = 'cargos'" role="tab">Cargos</button>
                    <button type="button" :class="paletteTab === 'people' && 'oc-on'" x-on:click="paletteTab = 'people'" role="tab">
                        Personas <span class="oc-count" x-show="palettePeople.length" x-text="(data?.unassigned || []).length"></span>
                    </button>
                </div>
                <input class="oc-input oc-palette-search" type="search" placeholder="Filtrar…" x-model="paletteQuery" aria-label="Filtrar la paleta">

                <div class="oc-palette-list">
                    <template x-if="paletteTab === 'areas'">
                        <div>
                            <template x-for="area in paletteAreas" :key="area.id">
                                <button
                                    type="button"
                                    class="oc-pitem"
                                    :class="area.used && 'oc-pitem-used'"
                                    x-on:pointerdown="onPaletteDown($event, { type: 'area', id: area.id, label: area.name })"
                                    x-on:keydown.enter.prevent="usePaletteItem({ type: 'area', id: area.id, label: area.name }, selectedId)"
                                    :title="'Arrastra sobre un puesto, o toca para agregarla debajo del puesto elegido'"
                                >
                                    <span class="oc-grip">⋮⋮</span>
                                    <span class="oc-pname" x-text="area.name"></span>
                                    <small x-text="area.used ? 'en uso' : area.people"></small>
                                </button>
                            </template>
                            <p class="oc-note oc-palette-empty" x-show="paletteAreas.length === 0">No hay áreas con ese nombre.</p>
                        </div>
                    </template>

                    <template x-if="paletteTab === 'cargos'">
                        <div>
                            <template x-for="group in paletteCargoGroups" :key="group.label">
                                <div>
                                    <div class="oc-psect" x-text="group.label"></div>
                                    <template x-for="cargo in group.cargos" :key="cargo.id">
                                        <button
                                            type="button"
                                            class="oc-pitem"
                                            :class="cargo.used && 'oc-pitem-used'"
                                            x-on:pointerdown="onPaletteDown($event, { type: 'cargo', id: cargo.id, label: cargo.name })"
                                            x-on:keydown.enter.prevent="usePaletteItem({ type: 'cargo', id: cargo.id, label: cargo.name }, selectedId)"
                                            :title="'Crea el puesto con ' + cargo.waiting + ' persona(s) sin ubicar que tienen este cargo'"
                                        >
                                            <span class="oc-grip">⋮⋮</span>
                                            <span class="oc-pname" x-text="cargo.name"></span>
                                            <small x-text="cargo.used ? 'en uso' : (cargo.waiting ? cargo.waiting + ' sin ubicar' : 'vacante')"></small>
                                        </button>
                                    </template>
                                </div>
                            </template>
                            <p class="oc-note oc-palette-empty" x-show="paletteCargoGroups.length === 0">No hay cargos con ese nombre.</p>
                        </div>
                    </template>

                    <template x-if="paletteTab === 'people'">
                        <div>
                            <template x-for="person in palettePeople" :key="person.id">
                                <button
                                    type="button"
                                    class="oc-pitem"
                                    x-on:pointerdown="onPaletteDown($event, { type: 'person', id: person.id, label: person.name })"
                                    x-on:keydown.enter.prevent="usePaletteItem({ type: 'person', id: person.id, label: person.name }, selectedId)"
                                    title="Arrastra sobre un puesto, o toca para asignarla al puesto elegido"
                                >
                                    <span class="oc-avatar oc-avatar-sm" x-text="person.initials"></span>
                                    <span class="oc-pname" x-text="person.name"></span>
                                    <small x-text="person.cargo || ''"></small>
                                </button>
                            </template>
                            <p class="oc-note oc-palette-empty" x-show="palettePeople.length === 0 && ! paletteQuery">Todas las personas activas de RRHH ya tienen puesto.</p>
                            <p class="oc-note oc-palette-empty" x-show="palettePeople.length === 0 && paletteQuery">Nadie sin puesto con ese nombre.</p>
                        </div>
                    </template>
                </div>
                <p class="oc-palette-foot">Arrastra sobre un puesto, o elige un puesto y toca el elemento.</p>
            </aside>
        </template>

        {{-- Lienzo --}}
        <div class="oc-stage-wrap" x-show="mode === 'view' || layout !== 'doc'">
            <div
                class="oc-stage"
                x-ref="stage"
                x-on:wheel="onWheel($event)"
                x-on:pointerdown="onPointerDown($event)"
                x-on:pointermove="onPointerMove($event)"
                x-on:pointerup="onPointerUp($event)"
                x-on:pointercancel="pointer = null; clearDrag()"
                x-on:dblclick="onDoubleClick($event)"
                role="application"
                aria-label="Organigrama. Arrastra para desplazarte; Ctrl y la rueda del ratón para acercar."
            >
                <svg class="oc-svg" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <clipPath id="oc-avatar-clip" clipPathUnits="objectBoundingBox">
                            <circle cx=".5" cy=".5" r=".5" />
                        </clipPath>
                    </defs>
                    <g x-ref="layer"></g>
                </svg>
            </div>

            {{-- Lienzo en blanco: un solo cuadro para el puesto principal --}}
            <template x-if="mode === 'edit' && ! hasNodes">
                <div class="oc-seed-wrap">
                    <form class="oc-seed" x-on:submit.prevent :class="readOnly && 'oc-seed-readonly'">
                        <span class="oc-seed-bar"></span>
                        <span class="oc-seed-avatar" aria-hidden="true"></span>
                        <span class="oc-seed-text">
                            <input
                                id="oc-root-input"
                                x-ref="rootTitle"
                                class="oc-seed-input"
                                placeholder="Escribe el puesto principal…"
                                maxlength="150"
                                autocomplete="off"
                                aria-label="Nombre del puesto principal"
                                :disabled="readOnly"
                                x-on:keydown.enter.prevent="addRoot($el.value)"
                                x-init="! readOnly && $nextTick(() => $el.focus())"
                            >
                            <span class="oc-seed-sub">Por asignar</span>
                        </span>
                    </form>
                    <p class="oc-seed-hint" x-show="! readOnly">
                        <kbd>Enter</kbd> para crearlo · después usa <strong>«+»</strong> para agregar debajo<span x-show="paletteOpen"> o arrastra aquí un área o un cargo de la paleta</span>
                    </p>
                </div>
            </template>

            {{-- Ventanita de edición junto a la tarjeta --}}
            <div class="oc-popover" x-ref="popover" x-show="popoverVisible && tab === 'explore'" x-cloak x-on:pointerdown.stop>
                <template x-if="selected">
                    <div class="oc-popover-body">
                        <button type="button" class="oc-popover-title" x-on:click="startRename(selected.id)" title="Cambiar el nombre (doble clic o F2)">
                            <span x-text="selected.title"></span>
                            <x-heroicon-o-pencil class="oc-icon oc-icon-xs" />
                        </button>

                        <div class="oc-popover-people">
                            <template x-for="occupant in selected.occupants.slice(0, 3)" :key="occupant.assignment_id">
                                <span class="oc-person-chip">
                                    <span class="oc-avatar oc-avatar-sm" x-text="occupant.initials"></span>
                                    <span x-text="occupant.name + (occupant.role === 'INTERINO' ? ' (interino)' : '')"></span>
                                    <button type="button" x-on:click="unassign(occupant)" :aria-label="'Retirar a ' + occupant.name">✕</button>
                                </span>
                            </template>
                            <span class="oc-note" x-show="selected.occupants.length > 3" x-text="'y ' + (selected.occupants.length - 3) + ' más'"></span>
                            <button type="button" class="oc-btn oc-btn-sm" x-show="selected.occupants.length < selected.seats && ! popoverSearch" x-on:click="popoverSearch = true; $nextTick(() => document.getElementById('oc-popover-search')?.focus())">
                                <x-heroicon-o-user-plus class="oc-icon" />
                                Asignar persona
                            </button>
                        </div>

                        <template x-if="popoverSearch">
                            <div class="oc-field">
                                <input id="oc-popover-search" class="oc-input" type="search" placeholder="Nombre de la persona…" x-model="personSearch" x-on:input="searchPeople()" x-on:keydown.escape.stop="popoverSearch = false" aria-label="Buscar persona para asignar">
                                <template x-for="person in personResults.slice(0, 6)" :key="person.id">
                                    <div class="oc-result">
                                        <span class="oc-avatar oc-avatar-sm" x-text="person.initials"></span>
                                        <span class="oc-result-name"><strong x-text="person.name"></strong><small x-text="person.cargo || ''"></small></span>
                                        <button type="button" class="oc-btn oc-btn-sm oc-btn-primary" x-on:click="assign(person, 'TITULAR')">Titular</button>
                                        <button type="button" class="oc-btn oc-btn-sm" x-on:click="assign(person, 'INTERINO')" title="Interino">Int.</button>
                                    </div>
                                </template>
                                <p class="oc-note" x-show="personSearching">Buscando…</p>
                                <p class="oc-note" x-show="personSearch.length >= 2 && ! personSearching && personResults.length === 0">Nadie activo con ese nombre.</p>
                            </div>
                        </template>

                        <div class="oc-popover-row">
                            <div class="oc-tabs oc-tabs-sm" role="radiogroup" aria-label="Tipo de puesto" x-show="selected.parent !== null">
                                <button type="button" class="oc-tab" :class="selected.kind !== 'STAFF' && 'oc-tab-on'" x-on:click="updateField('kind', 'LINEA', true)">Línea</button>
                                <button type="button" class="oc-tab" :class="selected.kind === 'STAFF' && 'oc-tab-on'" x-on:click="updateField('kind', 'STAFF', true)">Staff</button>
                            </div>
                            <div class="oc-stepper" aria-label="Plazas">
                                <button type="button" x-on:click="selected.seats > 1 && updateField('seats', selected.seats - 1, true)" :disabled="selected.seats <= 1" aria-label="Quitar una plaza">−</button>
                                <span x-text="selected.seats + (selected.seats === 1 ? ' plaza' : ' plazas')"></span>
                                <button type="button" x-on:click="updateField('seats', selected.seats + 1, true)" aria-label="Agregar una plaza">+</button>
                            </div>
                        </div>

                        <div class="oc-swatches" role="radiogroup" aria-label="Color del área">
                            <template x-for="color in cfg.colors" :key="color">
                                <button
                                    type="button"
                                    class="oc-swatch oc-swatch-sm"
                                    :class="(selected.color || 'navy') === color && 'oc-swatch-on'"
                                    :style="'background: var(--oc-c-' + color + ')'"
                                    :aria-label="'Color ' + colorLabel(color)"
                                    :title="colorLabel(color)"
                                    x-on:click="updateField('color', color, true)"
                                ></button>
                            </template>
                        </div>

                        <div class="oc-popover-foot">
                            <button type="button" class="oc-link" x-on:click="openDetails()">Más detalles</button>
                            <div class="oc-menu-wrap" x-on:click.outside="popoverMenu = false">
                                <button type="button" class="oc-btn oc-btn-sm oc-icon-btn" x-on:click="popoverMenu = ! popoverMenu" aria-label="Más acciones" :aria-expanded="popoverMenu">⋯</button>
                                <div class="oc-menu" x-show="popoverMenu" x-cloak>
                                    <button type="button" x-show="selected.kind !== 'STAFF'" x-on:click="popoverMenu = false; addQuick(selected.id, 'LINEA')">Agregar puesto debajo</button>
                                    <button type="button" x-on:click="popoverMenu = false; addQuick(selected.id, 'STAFF')">Agregar staff al lado</button>
                                    <button type="button" x-on:click="popoverMenu = false; startRename(selected.id)">Cambiar nombre</button>
                                    <button type="button" class="oc-menu-danger" x-show="selected.parent !== null" x-on:click="askRemove(selected.id)">Quitar puesto…</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Cambiar el nombre en la tarjeta --}}
            <div class="oc-rename" x-ref="rename" x-show="renaming !== null" x-cloak x-on:pointerdown.stop>
                <input
                    x-ref="renameInput"
                    class="oc-input"
                    maxlength="150"
                    placeholder="Nombre del puesto"
                    aria-label="Nombre del puesto"
                    x-model="renameValue"
                    x-on:keydown.enter.prevent="finishRename(true)"
                    x-on:keydown.escape.prevent.stop="finishRename(false)"
                    x-on:blur="finishRename(true)"
                >
                <small>Enter para guardar · Esc para cancelar</small>
            </div>

            <template x-if="focusId !== null">
                <div class="oc-crumbs">
                    <button type="button" x-on:click="clearFocus()">Toda la empresa</button>
                    <template x-for="item in focusPath" :key="item.id">
                        <span style="display: inline-flex; align-items: center; gap: 4px;">
                            <span style="color: var(--oc-muted);">›</span>
                            <button type="button" x-on:click="focusTeam(item.id)" x-text="item.title"></button>
                        </span>
                    </template>
                </div>
            </template>

            <div class="oc-zoom" role="group" aria-label="Zoom" x-show="hasNodes">
                <button type="button" x-on:click="zoomBy(1 / 1.2)" aria-label="Alejar">−</button>
                <button type="button" x-on:click="resetZoom()" x-text="zoomLabel" aria-label="Zoom al 100 %"></button>
                <button type="button" x-on:click="zoomBy(1.2)" aria-label="Acercar">+</button>
                <button type="button" x-on:click="fitView()">Ajustar</button>
                <button type="button" x-on:click="expandAll()" title="Desplegar todo">Desplegar</button>
                <button type="button" x-on:click="collapseToLevel(2)" title="Plegar al segundo nivel">Plegar</button>
            </div>

            <div class="oc-minimap" aria-hidden="true" x-show="hasNodes">
                <svg x-ref="minimap" preserveAspectRatio="xMidYMid meet" x-on:click="onMinimapClick($event)"></svg>
            </div>

            <div class="oc-loading" x-show="busy" x-cloak>Cargando…</div>

            {{-- Panel de detalle: consulta o «Más detalles» del estudio --}}
            <aside class="oc-drawer" x-show="(drawerOpen || detailsOpen) && selected && tab === 'explore'" x-cloak aria-label="Detalle del puesto">
                <template x-if="selected">
                    <div class="oc-drawer-inner">
                        <div class="oc-drawer-head">
                            <div style="min-width: 0; flex: 1;">
                                <span class="oc-label" x-text="selected.kind === 'STAFF' ? 'Staff / asesoría' : 'Puesto'"></span>
                                <template x-if="readOnly">
                                    <h3 x-text="selected.title"></h3>
                                </template>
                                <template x-if="! readOnly">
                                    <input
                                        class="oc-input oc-input-title"
                                        aria-label="Nombre del puesto"
                                        x-model="edit.title"
                                        x-on:input="updateField('title', edit.title)"
                                        x-on:blur="updateField('title', edit.title, true)"
                                        maxlength="150"
                                    >
                                </template>
                            </div>
                            <button type="button" class="oc-close" x-on:click="closeDetails()" aria-label="Cerrar panel">
                                <x-heroicon-o-x-mark class="oc-icon" />
                            </button>
                        </div>

                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                            <span class="oc-chip" x-show="selected.depto" x-text="selected.depto"></span>
                            <span class="oc-chip" x-show="selected.cargo && readOnly" x-text="'Cargo: ' + selected.cargo"></span>
                            <span class="oc-chip" x-show="selected.occupants.length === 0">Por asignar</span>
                            <span class="oc-chip" x-show="selected.seats > 1" x-text="selected.seats + ' plazas'"></span>
                        </div>

                        {{-- Personas --}}
                        <section class="oc-sect">
                            <button type="button" class="oc-sect-head" x-on:click="sections.people = ! sections.people" :aria-expanded="sections.people">
                                <span>Personas</span><span x-text="sections.people ? '▾' : '▸'"></span>
                            </button>
                            <div class="oc-sect-body" x-show="sections.people">
                                <template x-for="occupant in selected.occupants" :key="occupant.assignment_id">
                                    <div class="oc-person-row">
                                        <span class="oc-avatar">
                                            <template x-if="occupant.avatar"><img :src="occupant.avatar" alt="" loading="lazy"></template>
                                            <template x-if="! occupant.avatar"><span x-text="occupant.initials"></span></template>
                                        </span>
                                        <div>
                                            <strong x-text="occupant.name"></strong>
                                            <small x-text="occupant.role_label + (occupant.since ? ' · desde ' + occupant.since : '')"></small>
                                            <small x-show="occupant.phone" x-text="occupant.phone"></small>
                                            <small x-show="occupant.email" x-text="occupant.email"></small>
                                            <small x-show="! occupant.active" style="color: var(--oc-red);">Ya no está activo en RRHH</small>
                                            <small x-show="occupant.dual" style="color: var(--oc-amber);">Es titular de más de un puesto</small>
                                        </div>
                                        <button type="button" class="oc-btn oc-btn-sm" x-show="! readOnly" x-on:click="unassign(occupant)">Retirar</button>
                                    </div>
                                </template>
                                <p class="oc-note" x-show="selected.occupants.length === 0">Nadie ocupa este puesto todavía.</p>

                                <template x-if="! readOnly && selected.occupants.length < selected.seats">
                                    <div class="oc-field">
                                        <input class="oc-input" type="search" placeholder="Asignar persona: escribe su nombre…" aria-label="Buscar colaborador para asignar" x-model="personSearch" x-on:input="searchPeople()">
                                        <p class="oc-note" x-show="personSearching">Buscando…</p>
                                        <template x-for="person in personResults" :key="person.id">
                                            <div class="oc-result">
                                                <span class="oc-avatar oc-avatar-sm" x-text="person.initials"></span>
                                                <span class="oc-result-name"><strong x-text="person.name"></strong><small x-text="person.cargo || 'Sin cargo en RRHH'"></small></span>
                                                <button type="button" class="oc-btn oc-btn-sm oc-btn-primary" x-on:click="assign(person, 'TITULAR')">Titular</button>
                                                <button type="button" class="oc-btn oc-btn-sm" x-on:click="assign(person, 'INTERINO')">Interino</button>
                                            </div>
                                        </template>
                                        <p class="oc-note" x-show="personSearch.length >= 2 && ! personSearching && personResults.length === 0">No hay colaboradores activos con ese nombre.</p>
                                    </div>
                                </template>
                            </div>
                        </section>

                        {{-- Datos de RRHH --}}
                        <section class="oc-sect" x-show="! readOnly">
                            <button type="button" class="oc-sect-head" x-on:click="sections.rrhh = ! sections.rrhh" :aria-expanded="sections.rrhh">
                                <span>Datos de RRHH</span><span x-text="sections.rrhh ? '▾' : '▸'"></span>
                            </button>
                            <div class="oc-sect-body" x-show="sections.rrhh">
                                <label class="oc-field">
                                    <span class="oc-label">Cargo</span>
                                    <select class="oc-select" x-model="edit.rrhh_cargo_id" x-on:change="updateField('rrhh_cargo_id', edit.rrhh_cargo_id, true)">
                                        <option value="">Sin cargo vinculado</option>
                                        <template x-for="group in cargoGroups" :key="group.label">
                                            <optgroup :label="group.label">
                                                <template x-for="cargo in group.cargos" :key="cargo.id">
                                                    <option :value="cargo.id" x-text="cargo.name" :selected="String(cargo.id) === String(edit.rrhh_cargo_id)"></option>
                                                </template>
                                            </optgroup>
                                        </template>
                                    </select>
                                </label>
                                <label class="oc-field">
                                    <span class="oc-label">Área</span>
                                    <select class="oc-select" x-model="edit.rrhh_departamento_id" x-on:change="updateField('rrhh_departamento_id', edit.rrhh_departamento_id, true)" :disabled="deptLockedByCargo">
                                        <option value="">Sin área</option>
                                        <template x-for="dept in cfg.deptOptions" :key="dept.id">
                                            <option :value="dept.id" x-text="dept.name" :selected="String(dept.id) === String(edit.rrhh_departamento_id)"></option>
                                        </template>
                                    </select>
                                    <span class="oc-note" x-show="deptLockedByCargo">La define el cargo. Quita el cargo para elegir otra.</span>
                                </label>
                            </div>
                        </section>

                        {{-- Apariencia --}}
                        <section class="oc-sect" x-show="! readOnly">
                            <button type="button" class="oc-sect-head" x-on:click="sections.look = ! sections.look" :aria-expanded="sections.look">
                                <span>Tipo, plazas y color</span><span x-text="sections.look ? '▾' : '▸'"></span>
                            </button>
                            <div class="oc-sect-body" x-show="sections.look">
                                <div class="oc-row2">
                                    <label class="oc-field">
                                        <span class="oc-label">Tipo</span>
                                        <select class="oc-select" x-model="edit.kind" x-on:change="updateField('kind', edit.kind, true)" :disabled="selected.parent === null">
                                            <option value="LINEA">Línea de mando</option>
                                            <option value="STAFF">Staff / asesoría</option>
                                        </select>
                                    </label>
                                    <label class="oc-field">
                                        <span class="oc-label">Plazas</span>
                                        <input class="oc-input" type="number" min="1" max="99" x-model="edit.seats" x-on:change="updateField('seats', Number(edit.seats), true)">
                                    </label>
                                </div>
                                <div class="oc-swatches">
                                    <template x-for="color in cfg.colors" :key="color">
                                        <button
                                            type="button"
                                            class="oc-swatch"
                                            :class="(edit.color || 'navy') === color && 'oc-swatch-on'"
                                            :style="'background: var(--oc-c-' + color + ')'"
                                            :aria-label="'Color ' + colorLabel(color)"
                                            :title="colorLabel(color)"
                                            x-on:click="edit.color = color; updateField('color', color, true)"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </section>

                        {{-- Jefe y equipo --}}
                        <section class="oc-sect">
                            <button type="button" class="oc-sect-head" x-on:click="sections.team = ! sections.team" :aria-expanded="sections.team || readOnly">
                                <span>Jefe y equipo</span><span x-text="(sections.team || readOnly) ? '▾' : '▸'"></span>
                            </button>
                            <div class="oc-sect-body" x-show="sections.team || readOnly">
                                <div class="oc-field">
                                    <span class="oc-label">Reporta a</span>
                                    <template x-if="reportsTo(selected)">
                                        <button type="button" class="oc-link" x-on:click="select(reportsTo(selected).id)">
                                            <span x-text="reportsTo(selected).title"></span>
                                            <span class="oc-note" x-text="reportsTo(selected).occupants.length ? ' · ' + reportsTo(selected).occupants.map(o => o.name).join(', ') : ' · por asignar'"></span>
                                        </button>
                                    </template>
                                    <p class="oc-note" x-show="! reportsTo(selected)">Es el puesto principal.</p>
                                </div>
                                <div class="oc-field" x-show="childrenOf(selected.id).length > 0">
                                    <span class="oc-label">
                                        Equipo directo (<span x-text="childrenOf(selected.id).length"></span>)
                                        <span x-show="descendantCount(selected.id) > childrenOf(selected.id).length" x-text="'· ' + descendantCount(selected.id) + ' puestos en total'"></span>
                                    </span>
                                    <ul class="oc-list">
                                        <template x-for="child in childrenOf(selected.id).slice(0, 25)" :key="child.id">
                                            <li>
                                                <button type="button" class="oc-link" x-on:click="select(child.id)">
                                                    <span x-text="child.title"></span>
                                                    <span class="oc-note" x-text="child.occupants.length ? ' · ' + child.occupants[0].name : ' · por asignar'"></span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>
                                    <button type="button" class="oc-btn oc-btn-sm" x-show="mode === 'view'" x-on:click="focusTeam(selected.id)">
                                        <x-heroicon-o-viewfinder-circle class="oc-icon" />
                                        Ver solo su equipo
                                    </button>
                                </div>
                            </div>
                        </section>

                        {{-- Bitácora del puesto --}}
                        <section class="oc-sect" x-show="mode === 'edit'">
                            <button type="button" class="oc-sect-head" x-on:click="toggleHistory()" :aria-expanded="sections.history">
                                <span>Bitácora del puesto</span><span x-text="sections.history ? '▾' : '▸'"></span>
                            </button>
                            <div class="oc-sect-body" x-show="sections.history">
                                <p class="oc-note" x-show="historyLoading">Cargando…</p>
                                <template x-for="(event, index) in history" :key="index">
                                    <div class="oc-history">
                                        <span x-text="event.summary"></span>
                                        <small x-text="event.user + ' · ' + event.at"></small>
                                    </div>
                                </template>
                                <p class="oc-note" x-show="! historyLoading && history.length === 0">Sin movimientos en este borrador.</p>
                            </div>
                        </section>

                        <button type="button" class="oc-link oc-link-danger" x-show="! readOnly && selected.parent !== null" x-on:click="askRemove(selected.id)">Quitar este puesto…</button>
                    </div>
                </template>
            </aside>

            {{-- Panel de presentación y exportación --}}
            <aside class="oc-drawer" x-show="mode === 'view' && tab === 'present' && data" x-cloak aria-label="Presentar y exportar">
                <div class="oc-drawer-inner">
                    <div class="oc-drawer-head">
                        <h3>Presentar y exportar</h3>
                        <button type="button" class="oc-close" x-on:click="tab = 'explore'" aria-label="Cerrar panel">
                            <x-heroicon-o-x-mark class="oc-icon" />
                        </button>
                    </div>

                    <label class="oc-field" x-show="views.length > 0">
                        <span class="oc-label">Vista guardada</span>
                        <div style="display: flex; gap: 6px;">
                            <select class="oc-select" style="flex: 1;" x-model="selectedViewId" x-on:change="applySavedView()">
                                <option value="">Elige una vista…</option>
                                <template x-for="view in views" :key="view.id">
                                    <option :value="view.id" x-text="view.name"></option>
                                </template>
                            </select>
                            <button type="button" class="oc-btn oc-btn-sm oc-btn-danger" x-show="cfg.canManageViews && selectedViewId" x-on:click="deleteView()" aria-label="Eliminar vista">
                                <x-heroicon-o-trash class="oc-icon" />
                            </button>
                        </div>
                    </label>

                    <div class="oc-field">
                        <span class="oc-label">Desde</span>
                        <span x-text="focusId !== null ? node(focusId)?.title : 'Toda la empresa'"></span>
                        <span class="oc-note">Para empezar en un área, ve a Explorar, toca el puesto y elige «Ver solo su equipo».</span>
                    </div>

                    <label class="oc-field">
                        <span class="oc-label">Niveles</span>
                        <select class="oc-select" x-model="depth" x-on:change="collapsed = new Set(); presentationChanged()">
                            <option value="">Todos</option>
                            <option value="2">2 niveles</option>
                            <option value="3">3 niveles</option>
                            <option value="4">4 niveles</option>
                            <option value="5">5 niveles</option>
                        </select>
                    </label>

                    <div class="oc-field">
                        <span class="oc-label">Qué mostrar en cada tarjeta</span>
                        <label class="oc-toggle-row"><span>Foto o iniciales</span><input type="checkbox" x-model="fields.photo" x-on:change="presentationChanged()"></label>
                        <label class="oc-toggle-row"><span>Área</span><input type="checkbox" x-model="fields.department" x-on:change="presentationChanged()"></label>
                        <label class="oc-toggle-row"><span>Teléfono o correo corporativo</span><input type="checkbox" x-model="fields.contact" x-on:change="presentationChanged()"></label>
                        <label class="oc-toggle-row"><span>Puestos por asignar</span><input type="checkbox" x-model="showVacancies" x-on:change="presentationChanged()"></label>
                    </div>

                    <template x-if="cfg.canExport">
                        <div class="oc-field">
                            <span class="oc-label">Exportar</span>
                            <button type="button" class="oc-btn oc-btn-primary" :disabled="exporting" x-on:click="exportPdf()">
                                <x-heroicon-o-document-arrow-down class="oc-icon" />
                                <span x-text="exporting ? 'Preparando…' : 'PDF por láminas'"></span>
                            </button>
                            <span class="oc-note">Se genera en segundo plano; te avisamos en la campana con el enlace de descarga.</span>
                            <button type="button" class="oc-btn" :disabled="exporting" x-on:click="exportPng()">
                                <x-heroicon-o-photo class="oc-icon" />
                                Imagen PNG de lo que ves
                            </button>
                        </div>
                    </template>

                    <template x-if="cfg.canManageViews">
                        <div class="oc-field">
                            <span class="oc-label">Guardar esta vista para todos</span>
                            <div style="display: flex; gap: 6px;">
                                <input class="oc-input" style="flex: 1;" placeholder="Ej.: Solo Operaciones" maxlength="80" x-model="newViewName">
                                <button type="button" class="oc-btn" x-on:click="saveView()">Guardar</button>
                            </div>
                        </div>
                    </template>
                </div>
            </aside>
        </div>

        {{-- Documento (estudio) --}}
        <template x-if="mode === 'edit' && layout !== 'canvas'">
            <section class="oc-doc" aria-label="Documento del organigrama">
                <div class="oc-doc-head">
                    <span class="oc-label">Documento</span>
                    <span class="oc-note" x-show="! readOnly"><kbd>Enter</kbd> nuevo · <kbd>Tab</kbd> subordinado · <code>@</code> persona · <code>/</code> opciones</span>
                </div>
                <div
                    class="oc-doc-list"
                    tabindex="0"
                    x-ref="doc"
                    aria-label="Esquema editable. Flechas para moverte, Enter para escribir, Tab y Shift+Tab cambian de nivel, Alt y flechas reordenan."
                    x-on:keydown="onDocKey($event)"
                >
                    <template x-for="row in docRows" :key="row.id">
                        <div
                            class="oc-drow"
                            :class="[row.id === selectedId ? 'oc-drow-on' : '', row.diff ? 'oc-drow-' + row.diff : '']"
                            :style="'padding-left:' + (8 + row.level * 18) + 'px'"
                            :data-row="row.id"
                            x-on:click="row.id === selectedId && docEditId === null && ! readOnly ? startDocEdit(row.id) : select(row.id, false)"
                        >
                            <button type="button" class="oc-caret" x-show="row.hasKids" x-on:click.stop="toggleCollapse(row.id)" x-text="row.collapsed ? '▸' : '▾'" :aria-label="row.collapsed ? 'Desplegar' : 'Plegar'" tabindex="-1"></button>
                            <span class="oc-bullet" x-show="! row.hasKids" :class="row.staff && 'oc-bullet-staff'"></span>

                            <template x-if="docEditId === row.id">
                                <div class="oc-doc-edit" x-on:click.stop>
                                    <input
                                        id="oc-doc-input"
                                        class="oc-doc-input"
                                        maxlength="150"
                                        placeholder="Nombre del puesto"
                                        aria-label="Nombre del puesto"
                                        x-model="docValue"
                                        x-on:input="onDocInput()"
                                        x-on:keydown="onDocInputKey($event)"
                                        x-on:blur="onDocBlur()"
                                    >
                                    <div class="oc-menu oc-doc-menu" x-show="mention.open" x-cloak>
                                        <template x-for="(person, index) in mention.results" :key="person.id">
                                            <button type="button" :class="index === mention.index && 'oc-menu-on'" x-on:mousedown.prevent="pickMention(person)">
                                                <span class="oc-avatar oc-avatar-sm" x-text="person.initials"></span>
                                                <span x-text="person.name"></span>
                                                <small x-text="person.waiting ? 'sin puesto' : (person.cargo || '')"></small>
                                            </button>
                                        </template>
                                        <p class="oc-note" x-show="mention.results.length === 0" style="padding: 6px 8px; margin: 0;">Escribe el nombre de la persona…</p>
                                    </div>
                                    <div class="oc-menu oc-doc-menu" x-show="slash.open" x-cloak>
                                        <template x-for="(item, index) in slashItems" :key="item.label">
                                            <button type="button" :class="index === slash.index && 'oc-menu-on'" x-on:mousedown.prevent="slash.open = false; docValue = item.keep ? docValue : (docOriginal === 'Nuevo puesto' ? '' : docOriginal); item.run()" x-text="item.label"></button>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <template x-if="docEditId !== row.id">
                                <span class="oc-dtitle">
                                    <span x-text="row.title"></span>
                                    <small x-text="row.who" x-show="row.who"></small>
                                    <small class="oc-muted" x-show="! row.who">por asignar</small>
                                </span>
                            </template>

                            <span class="oc-dchips">
                                <span class="oc-chip oc-chip-red" x-show="row.error">Inactivo</span>
                                <span class="oc-chip" x-show="row.staff">Staff</span>
                                <span class="oc-chip oc-chip-blue" x-show="row.interim">Interino</span>
                            </span>
                        </div>
                    </template>
                    <template x-if="! hasNodes && ! readOnly">
                        <p class="oc-note" style="padding: 10px 14px;">Escribe el puesto principal en el cuadro del lienzo. Aquí aparecerá cada puesto como una línea.</p>
                    </template>
                </div>
            </section>
        </template>
    </div>

    {{-- Barra inferior --}}
    <div class="oc-statusbar" x-show="mode === 'edit' || (data && (data.nodes || []).length > 0)">
        <template x-if="mode === 'view' && data">
            <span>
                <span x-text="data.version.name"></span>
                <span x-show="data.version.published_at" x-text="' · vigente desde ' + data.version.published_at"></span>
                <span x-show="! isViewingCurrent" style="color: var(--oc-amber);"> · versión del historial</span>
            </span>
        </template>

        <template x-if="mode === 'edit'">
            <span class="oc-status-group">
                <span x-show="saveState === 'saving'">Guardando…</span>
                <span class="oc-ok" x-show="saveState === 'saved'" x-text="savedLabel || 'Guardado'"></span>
                <span class="oc-err" x-show="saveState === 'error'">No se guardó el último cambio</span>
                <span class="oc-progress" :title="placedLabel">
                    <span class="oc-meter"><i :style="'width:' + placedPercent + '%'"></i></span>
                    <span x-text="placedLabel"></span>
                </span>
                <button type="button" class="oc-chip oc-chip-button" x-show="vacantNodes.length > 0" x-on:click="nextVacant()" x-text="vacantNodes.length + ' por asignar · ver siguiente'"></button>
                <button type="button" class="oc-chip oc-chip-button oc-chip-red" x-show="errorCount > 0 && hasNodes" x-on:click="reviewOpen = true" x-text="errorCount + ' por corregir'"></button>
                <span x-show="compareOn && diff" class="oc-status-group">
                    <span class="oc-chip oc-chip-green" x-text="'+' + (diff?.summary?.added || 0) + ' nuevos'"></span>
                    <span class="oc-chip oc-chip-amber" x-text="(diff?.summary?.moved || 0) + ' movidos'"></span>
                    <span class="oc-chip oc-chip-purple" x-text="((diff?.summary?.renamed || 0) + (diff?.summary?.occupants || 0)) + ' cambios'"></span>
                    <span class="oc-chip oc-chip-red" x-text="'−' + (diff?.summary?.removed || 0) + ' salen'"></span>
                </span>
            </span>
        </template>

        <span class="oc-spacer"></span>
        <template x-if="mode === 'edit' && (data?.unassigned || []).length > 0 && ! readOnly">
            <button type="button" class="oc-link" x-on:click="openPalette('people')" x-text="(data?.unassigned || []).length + ' persona(s) activas sin puesto · ubicarlas'"></button>
        </template>
    </div>

    <div class="oc-toasts" aria-live="polite">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="oc-toast" :class="'oc-toast-' + toast.tone">
                <span x-text="toast.message"></span>
                <button type="button" x-on:click="dismissToast(toast.id)" aria-label="Cerrar">✕</button>
            </div>
        </template>
    </div>

    <div class="oc-ghost" x-ref="ghost" hidden>
        <strong data-ghost-title></strong>
        <span data-ghost-hint></span>
    </div>

    {{-- Ctrl K --}}
    <div class="oc-modal" x-show="cmd.open" x-cloak x-on:click.self="closeCommand()" role="dialog" aria-modal="true" aria-label="Buscar o hacer algo">
        <div class="oc-cmd">
            <input
                x-ref="cmdInput"
                class="oc-input oc-input-lg"
                placeholder="Busca un puesto, una persona o una acción…"
                x-model="cmd.q"
                x-on:input="cmd.index = 0"
                x-on:keydown="onCommandKey($event)"
                aria-label="Buscar o hacer algo"
            >
            <div class="oc-cmd-list">
                <template x-for="item in commandItems" :key="item.group + item.label">
                    <button type="button" class="oc-cmd-item" :class="item.index === cmd.index && 'oc-menu-on'" x-on:mouseenter="cmd.index = item.index" x-on:click="runCommand(item)">
                        <small class="oc-cmd-group" x-text="item.group"></small>
                        <span x-text="item.label"></span>
                        <kbd x-show="item.hint" x-text="item.hint"></kbd>
                    </button>
                </template>
                <p class="oc-note" x-show="commandItems.length === 0" style="padding: 10px 14px; margin: 0;">Nada coincide con «<span x-text="cmd.q"></span>».</p>
            </div>
        </div>
    </div>

    {{-- Revisar y publicar --}}
    <div class="oc-modal" x-show="reviewOpen" x-cloak x-on:click.self="reviewOpen = false" role="dialog" aria-modal="true" aria-label="Revisar y publicar">
        <div class="oc-dialog">
            <div class="oc-drawer-head">
                <div>
                    <span class="oc-label">Revisar y publicar</span>
                    <h3 x-text="errorCount > 0 ? 'Falta corregir ' + errorCount + ' cosa(s) antes de publicar' : (cfg.publishAction ? 'Listo para ' + (cfg.publishAction === 'publish' ? 'publicar' : 'enviar a aprobación') : 'Todo en orden')"></h3>
                </div>
                <button type="button" class="oc-close" x-on:click="reviewOpen = false" aria-label="Cerrar">
                    <x-heroicon-o-x-mark class="oc-icon" />
                </button>
            </div>

            <div class="oc-checklist">
                <template x-for="check in reviewChecks" :key="check.label">
                    <div class="oc-check">
                        <i :class="check.ok ? 'oc-check-ok' : 'oc-check-bad'" x-text="check.ok ? '✓' : '!'"></i>
                        <div>
                            <strong x-text="check.label"></strong>
                            <span class="oc-note" x-show="check.detail" x-text="check.detail"></span>
                            <button type="button" class="oc-link" x-show="check.fix && ! readOnly" x-on:click="fixInactive()">Retirar a los inactivos</button>
                        </div>
                    </div>
                </template>

                <div class="oc-check" x-show="(data?.unassigned || []).length > 0">
                    <i class="oc-check-warn">!</i>
                    <div>
                        <strong x-text="(data?.unassigned || []).length + ' persona(s) activa(s) sin puesto'"></strong>
                        <span class="oc-note">No impide publicar.</span>
                        <button type="button" class="oc-link" x-on:click="openPalette('people')">Ubicarlas</button>
                    </div>
                </div>

                <div class="oc-check" x-show="vacantNodes.length > 0">
                    <i class="oc-check-warn">!</i>
                    <div>
                        <strong x-text="vacantNodes.length + ' puesto(s) por asignar'"></strong>
                        <span class="oc-note">No impide publicar.</span>
                        <button type="button" class="oc-link" x-on:click="nextVacant()">Ver el siguiente</button>
                    </div>
                </div>

                <template x-for="(issue, index) in reviewErrors" :key="'e' + index">
                    <div class="oc-check oc-check-detail">
                        <span class="oc-issue-dot" style="background: var(--oc-red);"></span>
                        <span>
                            <span x-text="issue.message"></span>
                            <button type="button" class="oc-link" x-show="issue.key" x-on:click="selectByKey(issue.key)">Ver puesto</button>
                        </span>
                    </div>
                </template>

                <template x-for="(issue, index) in reviewWarnings" :key="'w' + index">
                    <div class="oc-check oc-check-detail">
                        <span class="oc-issue-dot" style="background: var(--oc-amber);"></span>
                        <span>
                            <span x-text="issue.message"></span>
                            <button type="button" class="oc-link" x-show="issue.key" x-on:click="selectByKey(issue.key)">Ver puesto</button>
                        </span>
                    </div>
                </template>

                <div class="oc-check">
                    <i class="oc-check-info">i</i>
                    <div>
                        <strong>Cargo en RRHH</strong>
                        <span class="oc-note">Al publicar verás quién cambiaría de cargo y decides si se actualiza.</span>
                    </div>
                </div>
            </div>

            <div class="oc-dialog-foot">
                <button type="button" class="oc-btn" x-on:click="reviewOpen = false">Seguir editando</button>
                <button type="button" class="oc-btn oc-btn-primary" x-show="cfg.publishAction" :disabled="errorCount > 0" x-on:click="publish()" x-text="publishLabel"></button>
                <span class="oc-note" x-show="demo">En el ejemplo no se publica.</span>
            </div>
        </div>
    </div>

    {{-- Quitar un puesto --}}
    <div class="oc-modal" x-show="removeDialog !== null" x-cloak x-on:click.self="removeDialog = null" role="alertdialog" aria-modal="true" aria-label="Quitar puesto">
        <template x-if="removeInfo">
            <div class="oc-dialog oc-dialog-sm">
                <h3 x-text="'Quitar «' + removeInfo?.title + '»'"></h3>
                <ul class="oc-consequences">
                    <li x-show="removeInfo?.children > 0" x-text="'Sus ' + removeInfo?.children + ' subordinado(s) pasarán a reportar a «' + removeInfo?.parentTitle + '».'"></li>
                    <li x-show="removeInfo?.children === 0">No tiene subordinados.</li>
                    <li x-show="(removeInfo?.occupants.length || 0) > 0" x-text="(removeInfo?.occupants || []).join(', ') + ' quedará(n) sin puesto en este borrador. RRHH no cambia.'"></li>
                    <li>Solo cambia este borrador; el organigrama vigente sigue igual hasta que publiques.</li>
                </ul>
                <label class="oc-toggle-row oc-confirm-row" x-show="(removeInfo?.occupants.length || 0) > 0">
                    <span>Sí, retirar también a las personas</span>
                    <input type="checkbox" x-model="removeRelease">
                </label>
                <div class="oc-dialog-foot">
                    <button type="button" class="oc-btn" x-ref="removeCancel" x-on:click="removeDialog = null">Cancelar</button>
                    <button type="button" class="oc-btn oc-btn-danger-solid" :disabled="(removeInfo?.occupants.length || 0) > 0 && ! removeRelease" x-on:click="confirmRemove()">Quitar puesto</button>
                </div>
            </div>
        </template>
    </div>

    {{-- Atajos --}}
    <div class="oc-modal" x-show="helpOpen" x-cloak x-on:click.self="helpOpen = false" role="dialog" aria-modal="true" aria-label="Atajos de teclado">
        <div class="oc-dialog oc-dialog-sm">
            <div class="oc-drawer-head">
                <h3>Atajos de teclado</h3>
                <button type="button" class="oc-close" x-on:click="helpOpen = false" aria-label="Cerrar"><x-heroicon-o-x-mark class="oc-icon" /></button>
            </div>
            <dl class="oc-keys">
                <dt><kbd>Ctrl K</kbd></dt><dd>Buscar o hacer algo</dd>
                <dt>Doble clic · <kbd>F2</kbd></dt><dd>Cambiar el nombre del puesto</dd>
                <dt><kbd>Ctrl Z</kbd></dt><dd>Deshacer</dd>
                <dt><kbd>Enter</kbd></dt><dd>En el documento: escribir o crear el siguiente puesto</dd>
                <dt><kbd>Tab</kbd> · <kbd>Shift Tab</kbd></dt><dd>Bajar o subir un nivel</dd>
                <dt><kbd>Alt ↑</kbd> · <kbd>Alt ↓</kbd></dt><dd>Cambiar el orden entre hermanos</dd>
                <dt><code>@nombre</code></dt><dd>Asignar una persona mientras escribes</dd>
                <dt><code>/</code></dt><dd>Opciones: staff, plazas, color, quitar</dd>
                <dt><kbd>Supr</kbd></dt><dd>Quitar el puesto elegido en el documento</dd>
                <dt>Ctrl + rueda</dt><dd>Acercar o alejar el lienzo</dd>
            </dl>
        </div>
    </div>
</div>
