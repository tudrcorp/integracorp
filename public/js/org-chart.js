/**
 * Organigrama de IntegraCorp.
 *
 * - Consulta (todos los paneles internos): lienzo SVG con zoom, minimapa, búsqueda,
 *   plegado, panel de detalle, presentación y exportación.
 * - Estudio (Administración): paleta de RRHH, lienzo con «+» en cada tarjeta,
 *   documento editable con teclado (Enter, Tab, @ y /), ventanita de edición junto
 *   a la tarjeta, «Más detalles» por secciones, Ctrl+K, revisión antes de publicar
 *   y deshacer. Lienzo y documento son dos vistas del mismo árbol.
 * - Ejemplo: el mismo estudio con un simulador local; nada viaja al servidor.
 *
 * Sin librerías: el árbol se acomoda solo y se dibuja como texto SVG en una pasada.
 * Desplazar y hacer zoom solo cambia una transformación; redibujar ocurre cuando
 * cambian los datos o el plegado. Se carga con @assets desde la vista Blade.
 */
(function () {
    if (window.integracorpOrgChart) {
        return;
    }

    const CARD_W = 232;
    const H_GAP = 28;
    const V_GAP = 64;
    const STAFF_GAP = 40;
    const STAFF_STACK_GAP = 14;
    const PILL_SPACE = 30;
    const STACK_MIN = 6;
    const STACK_ROWS = 8;
    const STACK_INDENT = 30;
    const STACK_GAP = 12;
    const MIN_ZOOM = 0.12;
    const MAX_ZOOM = 2.2;
    const DRAG_THRESHOLD = 6;
    const AREA_COLORS = ['navy', 'teal', 'green', 'amber', 'red', 'purple', 'slate'];
    const COLOR_LABELS = { navy: 'Azul', teal: 'Turquesa', green: 'Verde', amber: 'Ámbar', red: 'Rojo', purple: 'Morado', slate: 'Gris' };
    const NEW_TITLE = 'Nuevo puesto';

    /**
     * Excluye un objeto del sistema reactivo de Alpine. El árbol puede tener cientos
     * de puestos: envolverlos en proxies haría lento cada dibujo. Las plantillas se
     * enteran de los cambios porque el objeto se reemplaza, no se muta.
     */
    const raw = (value) => {
        if (value && typeof value === 'object' && ! Object.prototype.hasOwnProperty.call(value, '__v_skip')) {
            Object.defineProperty(value, '__v_skip', { value: true, enumerable: false });
        }

        return value;
    };

    const esc = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const normalize = (value) => String(value ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();

    const storage = {
        get(key, fallback) {
            try {
                return window.localStorage.getItem(key) ?? fallback;
            } catch (error) {
                return fallback;
            }
        },
        set(key, value) {
            try {
                window.localStorage.setItem(key, value);
            } catch (error) {
                // Navegación privada: la preferencia no se recuerda y no pasa nada.
            }
        },
    };

    let measureContext = null;
    const textWidthCache = new Map();

    const textWidth = (text, font) => {
        const key = font + '|' + text;

        if (textWidthCache.has(key)) {
            return textWidthCache.get(key);
        }

        if (! measureContext) {
            measureContext = document.createElement('canvas').getContext('2d');
        }

        measureContext.font = font;
        const width = measureContext.measureText(text).width;

        if (textWidthCache.size > 5000) {
            textWidthCache.clear();
        }

        textWidthCache.set(key, width);

        return width;
    };

    const fit = (text, maxWidth, font) => {
        text = String(text ?? '');

        if (textWidth(text, font) <= maxWidth) {
            return text;
        }

        let low = 0;
        let high = text.length;

        while (low < high) {
            const middle = Math.ceil((low + high) / 2);

            if (textWidth(text.slice(0, middle) + '…', font) <= maxWidth) {
                low = middle;
            } else {
                high = middle - 1;
            }
        }

        return text.slice(0, Math.max(1, low)).trimEnd() + '…';
    };

    const FONT_FAMILY = 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
    const FONT_TITLE = '600 13px ' + FONT_FAMILY;
    const FONT_PERSON = '400 12px ' + FONT_FAMILY;
    const FONT_META = '500 10.5px ' + FONT_FAMILY;

    /**
     * Simulador del ejemplo: mismas respuestas que el servidor, sobre una copia local.
     * Aplica las mismas reglas básicas (sin ciclos, staff sin subordinados, plazas).
     */
    const createSimulator = (initialPayload, palette) => {
        let state = JSON.parse(JSON.stringify(initialPayload));
        let lock = 0;
        let nextId = 1000;
        const pool = [
            { id: 9101, name: 'Gabriela Fuentes', cargo: 'Analista' },
            { id: 9102, name: 'Martín Ochoa', cargo: 'Coordinador' },
            { id: 9103, name: 'Paula Rivas', cargo: 'Asistente' },
        ];

        const node = (id) => state.nodes.find((item) => item.id === id) || null;
        const kidsOf = (id) => state.nodes.filter((item) => item.parent === id).sort((a, b) => a.sort - b.sort);
        const isDescendant = (candidate, ancestor) => {
            let current = node(candidate)?.parent ?? null;
            let guard = 0;

            while (current !== null && guard++ < 500) {
                if (current === ancestor) {
                    return true;
                }

                current = node(current)?.parent ?? null;
            }

            return false;
        };
        const initials = (name) => {
            const parts = String(name).trim().split(/\s+/);

            return ((parts[0] || '').charAt(0) + (parts[1] || '').charAt(0)).toUpperCase() || '?';
        };
        const restats = () => {
            const people = new Set();
            let vacancies = 0;
            let interims = 0;

            state.nodes.forEach((item) => {
                item.vacancies = Math.max(0, item.seats - item.occupants.length);
                vacancies += item.vacancies;
                item.occupants.forEach((occupant) => {
                    people.add(occupant.id);
                    interims += occupant.role === 'INTERINO' ? 1 : 0;
                });
            });

            const roots = state.nodes.filter((item) => item.parent === null).length;
            state.issues = [];

            if (state.nodes.length === 0) {
                state.issues.push({ level: 'error', code: 'empty', message: 'El organigrama no tiene puestos. Agrega el puesto principal.', key: null });
            } else if (roots > 1) {
                state.issues.push({ level: 'error', code: 'multiple_roots', message: 'Hay ' + roots + ' puestos sin jefe. Debe haber un solo puesto principal.', key: null });
            }

            if (state.unassigned.length) {
                state.issues.push({ level: 'warning', code: 'unassigned', message: state.unassigned.length + ' colaborador(es) activo(s) sin puesto: ' + state.unassigned.map((person) => person.name).join(', ') + '.', key: null });
            }

            state.stats = {
                positions: state.nodes.length,
                occupied: state.nodes.filter((item) => item.occupants.length).length,
                vacancies,
                interims,
                people: people.size,
                unassigned: state.unassigned.length,
                errors: state.issues.filter((issue) => issue.level === 'error').length,
                warnings: state.issues.filter((issue) => issue.level === 'warning').length,
            };
        };
        const resequence = (parent) => kidsOf(parent).forEach((item, index) => { item.sort = index; });
        const done = (extra = {}) => {
            lock++;
            state.version.lock_version = lock;
            restats();

            return Promise.resolve({ ok: true, lock_version: lock, ...extra, payload: JSON.parse(JSON.stringify(state)) });
        };
        const fail = (message) => Promise.resolve({ ok: false, message });
        const insert = (parent, title, kind, index, extra = {}) => {
            const id = nextId++;
            const siblings = kidsOf(parent);
            const item = {
                id,
                key: '00000000-0000-4000-9000-' + String(id).padStart(12, '0'),
                parent,
                title,
                kind,
                seats: 1,
                color: parent === null ? null : (node(parent)?.color ?? null),
                sort: siblings.length,
                cargo_id: null,
                cargo: null,
                depto_id: null,
                depto: null,
                occupants: [],
                vacancies: 1,
                ...extra,
            };

            state.nodes.push(item);

            if (index !== null && index !== undefined && parent !== null) {
                siblings.splice(Math.max(0, Math.min(index, siblings.length)), 0, item);
                siblings.forEach((sibling, position) => { sibling.sort = position; });
            }

            return item;
        };
        const parentAllows = (parent) => {
            if (parent === null) {
                return state.nodes.length === 0 ? null : 'El organigrama ya tiene un puesto principal. Agrega el nuevo puesto debajo de otro.';
            }

            const target = node(parent);

            if (! target) {
                return 'Ese puesto ya no existe.';
            }

            return target.kind === 'STAFF' ? '«' + target.title + '» es staff: un puesto staff no tiene subordinados.' : null;
        };

        restats();

        return {
            addPosition(_lock, parent, title, kind = 'LINEA', index = null, deptId = null) {
                const problem = parentAllows(parent);

                if (problem) {
                    return fail(problem);
                }

                title = String(title || '').trim();

                if (title.length < 2) {
                    return fail('Escribe el nombre del puesto (al menos 2 caracteres).');
                }

                const area = (palette.areas || []).find((item) => item.id === deptId);
                const created = insert(parent, title, kind, index, { depto_id: area ? area.id : null, depto: area ? area.name : null });

                return done({ position_id: created.id });
            },
            addFromCargo(_lock, parent, cargoId, index = null) {
                const problem = parentAllows(parent);

                if (problem) {
                    return fail(problem);
                }

                const cargo = (palette.cargos || []).find((item) => item.id === cargoId);

                if (! cargo) {
                    return fail('El cargo ya no existe en RRHH.');
                }

                const area = (palette.areas || []).find((item) => item.id === cargo.departamento_id);
                const created = insert(parent, cargo.name, 'LINEA', index, { cargo_id: cargo.id, cargo: cargo.name, depto_id: area?.id ?? null, depto: area?.name ?? null });

                return done({ position_id: created.id, assigned: 0 });
            },
            updatePosition(_lock, id, attributes) {
                const item = node(id);

                if (! item) {
                    return fail('Ese puesto ya no existe.');
                }

                if (attributes.title !== undefined) {
                    const title = String(attributes.title || '').trim();

                    if (title.length < 2) {
                        return fail('Escribe el nombre del puesto (al menos 2 caracteres).');
                    }

                    item.title = title;
                }

                if (attributes.kind !== undefined) {
                    if (attributes.kind === 'STAFF' && (item.parent === null || kidsOf(id).length)) {
                        return fail(item.parent === null ? 'El puesto principal no puede ser staff.' : 'Un puesto staff no tiene subordinados.');
                    }

                    item.kind = attributes.kind;
                }

                if (attributes.seats !== undefined) {
                    const seats = Number(attributes.seats);

                    if (! Number.isInteger(seats) || seats < 1 || seats > 99) {
                        return fail('Las plazas deben ser un número entre 1 y 99.');
                    }

                    if (seats < item.occupants.length) {
                        return fail('«' + item.title + '» tiene ' + item.occupants.length + ' ocupantes. Retira primero a alguien.');
                    }

                    item.seats = seats;
                }

                if (attributes.color !== undefined) {
                    item.color = attributes.color || null;
                }

                if (attributes.rrhh_cargo_id !== undefined) {
                    const cargo = (palette.cargos || []).find((entry) => entry.id === Number(attributes.rrhh_cargo_id));
                    item.cargo_id = cargo ? cargo.id : null;
                    item.cargo = cargo ? cargo.name : null;

                    if (cargo && cargo.departamento_id) {
                        const area = (palette.areas || []).find((entry) => entry.id === cargo.departamento_id);
                        item.depto_id = area?.id ?? null;
                        item.depto = area?.name ?? null;
                    }
                }

                if (attributes.rrhh_departamento_id !== undefined && attributes.rrhh_cargo_id === undefined) {
                    const area = (palette.areas || []).find((entry) => entry.id === Number(attributes.rrhh_departamento_id));
                    item.depto_id = area ? area.id : null;
                    item.depto = area ? area.name : null;
                }

                return done();
            },
            movePosition(_lock, id, parent, index) {
                const item = node(id);
                const target = node(parent);

                if (! item || ! target) {
                    return fail('Ese puesto ya no existe.');
                }

                if (item.parent === null) {
                    return fail('El puesto principal no se puede mover.');
                }

                if (parent === id || isDescendant(parent, id)) {
                    return fail('«' + item.title + '» no puede quedar bajo «' + target.title + '»: este depende de él.');
                }

                if (target.kind === 'STAFF') {
                    return fail('«' + target.title + '» es staff: un puesto staff no tiene subordinados.');
                }

                const previous = item.parent;
                const siblings = kidsOf(parent).filter((sibling) => sibling.id !== id);
                siblings.splice(Math.max(0, Math.min(index, siblings.length)), 0, item);
                item.parent = parent;
                siblings.forEach((sibling, position) => { sibling.sort = position; });
                resequence(previous);

                return done();
            },
            removePosition(_lock, id, release = false) {
                const item = node(id);

                if (! item || item.parent === null) {
                    return fail(item ? 'El puesto principal no se puede quitar.' : 'Ese puesto ya no existe.');
                }

                if (item.occupants.length && ! release) {
                    return fail('«' + item.title + '» tiene ocupantes. Retíralos antes de quitar el puesto.');
                }

                item.occupants.forEach((occupant) => state.unassigned.push({ id: occupant.id, name: occupant.name, initials: occupant.initials, avatar: null, cargo_id: null, cargo: null, depto_id: null }));
                const children = kidsOf(id);
                const siblings = kidsOf(item.parent);
                const ordered = [];

                siblings.forEach((sibling) => {
                    if (sibling.id === id) {
                        children.forEach((child) => {
                            child.parent = item.parent;
                            ordered.push(child);
                        });
                    } else {
                        ordered.push(sibling);
                    }
                });

                state.nodes = state.nodes.filter((entry) => entry.id !== id);
                ordered.forEach((entry, position) => { entry.sort = position; });

                return done();
            },
            assignOccupant(_lock, id, colaboradorId, role) {
                const item = node(id);

                if (! item) {
                    return fail('Ese puesto ya no existe.');
                }

                if (item.occupants.length >= item.seats) {
                    return fail('«' + item.title + '» no tiene plazas libres (' + item.seats + '). Aumenta las plazas o retira a alguien.');
                }

                const fromPool = state.unassigned.find((person) => person.id === colaboradorId) || pool.find((person) => person.id === colaboradorId);

                if (! fromPool) {
                    return fail('Esa persona no está disponible.');
                }

                state.unassigned = state.unassigned.filter((person) => person.id !== colaboradorId);
                item.occupants.push({
                    assignment_id: nextId++,
                    id: colaboradorId,
                    name: fromPool.name,
                    initials: initials(fromPool.name),
                    avatar: null,
                    role,
                    role_label: role === 'INTERINO' ? 'Interino' : 'Titular',
                    phone: null,
                    email: null,
                    since: null,
                    active: true,
                    dual: state.nodes.some((other) => other.id !== id && other.occupants.some((occupant) => occupant.id === colaboradorId && occupant.role === 'TITULAR')),
                    rrhh_cargo_id: null,
                    rrhh_cargo: null,
                });

                return done({ dual: false });
            },
            removeOccupant(_lock, assignmentId) {
                state.nodes.forEach((item) => {
                    const leaving = item.occupants.find((occupant) => occupant.assignment_id === assignmentId);

                    if (leaving) {
                        state.unassigned.push({ id: leaving.id, name: leaving.name, initials: leaving.initials, avatar: null, cargo_id: null, cargo: null, depto_id: null });
                    }

                    item.occupants = item.occupants.filter((occupant) => occupant.assignment_id !== assignmentId);
                });

                return done();
            },
            removeInactiveOccupants() {
                return done({ removed: 0 });
            },
            searchColaboradores(term) {
                const query = normalize(term);
                const results = [...state.unassigned, ...pool.filter((person) => ! state.nodes.some((item) => item.occupants.some((occupant) => occupant.id === person.id)))]
                    .filter((person) => normalize(person.name).includes(query))
                    .slice(0, 10)
                    .map((person) => ({ id: person.id, name: person.name, cargo: person.cargo || null, initials: initials(person.name), avatar: null }));

                return Promise.resolve(results);
            },
            compareWithBase() {
                return Promise.resolve({ ok: false, message: 'El ejemplo no tiene una versión con la cual comparar.' });
            },
            positionHistory() {
                return Promise.resolve([]);
            },
            heartbeat() {
                return Promise.resolve({ ok: true });
            },
            releaseLock() {
                return Promise.resolve();
            },
        };
    };

    window.integracorpOrgChart = function () {
        return {
            cfg: {},
            mode: 'view',
            demo: false,
            sim: null,
            readOnly: true,
            readOnlyReason: null,
            data: null,
            byId: raw(new Map()),
            kids: raw(new Map()),
            rootId: null,
            collapsed: new Set(),
            selectedId: null,
            focusId: null,
            pos: raw(new Map()),
            bounds: { minX: 0, minY: 0, maxX: 0, maxY: 0 },
            view: { x: 0, y: 0, k: 1 },
            viewport: { w: 800, h: 560 },
            cardH: 86,
            tab: 'explore',
            layout: 'canvas',
            paletteOpen: false,
            paletteTab: 'cargos',
            paletteQuery: '',
            fields: { photo: true, department: true, contact: false },
            depth: '',
            showVacancies: true,
            versionId: null,
            versions: [],
            views: [],
            selectedViewId: '',
            newViewName: '',
            search: '',
            searchResults: [],
            searchOpen: false,
            matchId: null,
            lock: 0,
            saveState: 'idle',
            savedAt: null,
            savedLabel: '',
            queue: Promise.resolve(),
            undoStack: [],
            toasts: [],
            toastSeq: 0,
            compareOn: false,
            diff: null,
            compareInfo: null,
            edit: {},
            personSearch: '',
            personResults: [],
            personSearching: false,
            popoverSearch: false,
            popoverMenu: false,
            detailsOpen: false,
            drawerOpen: false,
            sections: { people: true, rrhh: true, look: false, team: false, history: false },
            history: [],
            historyLoading: false,
            renaming: null,
            renameValue: '',
            docEditId: null,
            docValue: '',
            docOriginal: '',
            mention: { open: false, term: '', results: [], index: 0 },
            slash: { open: false, index: 0 },
            cmd: { open: false, q: '', index: 0 },
            reviewOpen: false,
            removeDialog: null,
            removeRelease: false,
            helpOpen: false,
            coach: false,
            busy: false,
            exporting: false,
            heartbeatTimer: null,
            savedTicker: null,
            resizeObserver: null,
            pointer: null,
            paletteDrag: null,
            dragTargetId: null,
            avatarCache: raw(new Map()),
            timers: {},
            windowListeners: [],

            // ------------------------------------------------------------ arranque

            init() {
                let cfg = {};

                try {
                    cfg = JSON.parse(this.$refs.config.textContent || '{}');
                } catch (error) {
                    cfg = {};
                }

                const initialPayload = cfg.payload || null;
                delete cfg.payload;
                this.cfg = raw(cfg);
                this.mode = cfg.mode || 'view';
                this.demo = Boolean(cfg.demo);
                this.readOnly = this.mode !== 'edit' || Boolean(cfg.readOnly);
                this.readOnlyReason = cfg.readOnlyReason || null;
                this.versions = cfg.versions || [];
                this.views = cfg.views || [];
                this.versionId = cfg.currentVersionId || null;

                if (this.demo && initialPayload) {
                    this.sim = raw(createSimulator(initialPayload, cfg.palette || {}));
                }

                if (this.mode === 'edit') {
                    const wide = window.innerWidth >= 1280;
                    const saved = storage.get('oc-layout', '');
                    this.layout = ['canvas', 'split', 'doc'].includes(saved) ? saved : (wide ? 'split' : 'canvas');
                    this.paletteOpen = ! this.readOnly && window.innerWidth >= 1024 && storage.get('oc-palette', '1') === '1';
                    this.coach = ! this.readOnly && storage.get('oc-coach-v1', '') !== 'visto';
                }

                if (initialPayload) {
                    this.setData(initialPayload, false);
                    this.applyDefaultCollapse();
                }

                this.$nextTick(() => {
                    this.measureViewport();
                    this.relayout();
                    this.fitView(true);
                });

                this.$watch('tab', () => this.$nextTick(() => this.fitView()));
                this.$watch('layout', (value) => {
                    storage.set('oc-layout', value);
                    this.$nextTick(() => {
                        this.measureViewport();
                        this.fitView();
                    });
                });
                this.$watch('paletteOpen', (value) => {
                    storage.set('oc-palette', value ? '1' : '0');
                    this.$nextTick(() => this.measureViewport());
                });

                this.resizeObserver = new ResizeObserver(() => {
                    const before = { ...this.viewport };
                    this.measureViewport();

                    if (before.w !== this.viewport.w || before.h !== this.viewport.h) {
                        this.applyTransform();
                        this.renderMinimap();
                    }
                });

                if (this.$refs.stage) {
                    this.resizeObserver.observe(this.$refs.stage);
                }

                const onMove = (event) => this.onWindowPointerMove(event);
                const onUp = (event) => this.onWindowPointerUp(event);
                window.addEventListener('pointermove', onMove);
                window.addEventListener('pointerup', onUp);
                this.windowListeners = [['pointermove', onMove], ['pointerup', onUp]];

                this.savedTicker = setInterval(() => this.refreshSavedLabel(), 5000);

                if (this.mode === 'edit' && ! this.readOnly && ! this.demo && cfg.heartbeatMs) {
                    this.heartbeatTimer = setInterval(() => this.heartbeat(), cfg.heartbeatMs);
                }
            },

            destroy() {
                clearInterval(this.heartbeatTimer);
                clearInterval(this.savedTicker);
                Object.values(this.timers).forEach((timer) => clearTimeout(timer));
                this.resizeObserver?.disconnect();
                this.windowListeners.forEach(([name, handler]) => window.removeEventListener(name, handler));

                if (this.mode === 'edit' && ! this.readOnly && ! this.demo) {
                    try {
                        this.$wire.releaseLock();
                    } catch (error) {
                        // El candado vence solo a los dos minutos.
                    }
                }
            },

            server(method, ...args) {
                if (this.demo && this.sim && typeof this.sim[method] === 'function') {
                    return this.sim[method](...args);
                }

                return this.$wire[method](...args);
            },

            // ---------------------------------------------------------------- datos

            setData(payload, keepState = true) {
                const byId = raw(new Map());
                const kids = raw(new Map());
                let rootId = null;

                for (const node of payload?.nodes || []) {
                    byId.set(node.id, node);
                }

                for (const node of payload?.nodes || []) {
                    const parent = node.parent !== null && byId.has(node.parent) ? node.parent : null;

                    if (parent === null) {
                        if (rootId === null) {
                            rootId = node.id;
                        }

                        continue;
                    }

                    if (! kids.has(parent)) {
                        kids.set(parent, []);
                    }

                    kids.get(parent).push(node.id);
                }

                for (const list of kids.values()) {
                    list.sort((a, b) => (byId.get(a).sort - byId.get(b).sort) || (a - b));
                }

                this.byId = byId;
                this.kids = kids;
                this.rootId = rootId;
                this.data = raw(payload);
                this.lock = payload?.version?.lock_version ?? this.lock;

                if (! keepState) {
                    this.collapsed = new Set();
                }

                for (const id of [...this.collapsed]) {
                    if (! this.byId.has(id)) {
                        this.collapsed.delete(id);
                    }
                }

                if (this.selectedId !== null && ! this.byId.has(this.selectedId)) {
                    this.selectedId = null;
                    this.drawerOpen = false;
                    this.detailsOpen = false;
                }

                if (this.focusId !== null && ! this.byId.has(this.focusId)) {
                    this.focusId = null;
                }

                if (this.docEditId !== null && ! this.byId.has(this.docEditId)) {
                    this.docEditId = null;
                }

                this.syncEdit();
            },

            applyDefaultCollapse() {
                const total = this.data?.nodes?.length || 0;
                const limit = this.mode === 'edit' ? (total > 150 ? 3 : 6) : (total > 40 ? 2 : 3);

                const walk = (id, level) => {
                    const children = this.kids.get(id) || [];

                    if (children.length && level >= limit) {
                        this.collapsed.add(id);

                        return;
                    }

                    children.forEach((child) => walk(child, level + 1));
                };

                if (this.rootId !== null) {
                    walk(this.rootId, 1);
                }
            },

            node(id) {
                return this.byId.get(id) || null;
            },

            get selected() {
                return this.selectedId !== null ? this.node(this.selectedId) : null;
            },

            get stats() {
                return this.data?.stats || {};
            },

            get issues() {
                return this.data?.issues || [];
            },

            get hasNodes() {
                return (this.data?.nodes || []).length > 0;
            },

            get errorCount() {
                return this.stats.errors || 0;
            },

            get warningCount() {
                return this.stats.warnings || 0;
            },

            get placedLabel() {
                const placed = this.stats.people || 0;
                const total = placed + (this.stats.unassigned || 0);

                return total ? placed + ' de ' + total + ' personas ubicadas' : 'Sin personas en RRHH';
            },

            get placedPercent() {
                const placed = this.stats.people || 0;
                const total = placed + (this.stats.unassigned || 0);

                return total ? Math.round((placed / total) * 100) : 0;
            },

            get vacantNodes() {
                return (this.data?.nodes || []).filter((node) => node.occupants.length === 0);
            },

            descendantCount(id) {
                let count = 0;
                const stack = [...(this.kids.get(id) || [])];

                while (stack.length) {
                    const current = stack.pop();
                    count++;
                    stack.push(...(this.kids.get(current) || []));
                }

                return count;
            },

            isDescendant(candidateId, ancestorId) {
                let current = this.node(candidateId)?.parent ?? null;
                let guard = 0;

                while (current !== null && guard++ < 1000) {
                    if (current === ancestorId) {
                        return true;
                    }

                    current = this.node(current)?.parent ?? null;
                }

                return false;
            },

            pathTo(id) {
                const path = [];
                let current = id;
                let guard = 0;

                while (current !== null && this.byId.has(current) && guard++ < 1000) {
                    path.unshift(this.byId.get(current));
                    current = this.byId.get(current).parent;
                }

                return path;
            },

            parentOf(id) {
                const parentId = this.node(id)?.parent ?? null;

                return parentId !== null ? this.node(parentId) : null;
            },

            childrenOf(id) {
                return (this.kids.get(id) || []).map((childId) => this.node(childId));
            },

            reportsTo(node) {
                return node ? this.parentOf(node.id) : null;
            },

            isVisibleNode(node) {
                if (this.showVacancies || this.mode === 'edit') {
                    return true;
                }

                return node.occupants.length > 0 || (this.kids.get(node.id) || []).length > 0;
            },

            visibleKids(id, level) {
                if (this.collapsed.has(id)) {
                    return [];
                }

                if (this.mode !== 'edit' && this.depth !== '' && level >= Number(this.depth)) {
                    return [];
                }

                return (this.kids.get(id) || []).filter((childId) => this.isVisibleNode(this.node(childId)));
            },

            hiddenKidsCount(id, level) {
                const all = (this.kids.get(id) || []).length;

                return all > 0 && this.visibleKids(id, level).length === 0 ? this.descendantCount(id) : 0;
            },

            // ---------------------------------------------------------- disposición

            computeCardHeight() {
                this.cardH = 86 + (this.fields.contact ? 16 : 0);
            },

            /**
             * Disposición automática. Un jefe con muchos subordinados sin equipo propio
             * los apila en columnas bajo él: así no aparece una fila infinita que
             * obligue a alejar el zoom hasta volver ilegible el organigrama.
             */
            relayout() {
                this.computeCardHeight();
                this.pos = raw(new Map());
                const start = this.focusId ?? this.rootId;

                if (start === null || ! this.byId.has(start)) {
                    this.bounds = { minX: 0, minY: 0, maxX: 0, maxY: 0 };
                    this.render();

                    return;
                }

                const H = this.cardH;
                const widths = new Map();
                const layouts = new Map();

                const split = (id, level) => {
                    if (layouts.has(id)) {
                        return layouts.get(id);
                    }

                    const visible = this.visibleKids(id, level);
                    const staff = visible.filter((childId) => this.node(childId).kind === 'STAFF');
                    const line = visible.filter((childId) => this.node(childId).kind !== 'STAFF');
                    const stacked = line.length >= STACK_MIN && line.every((childId) => this.visibleKids(childId, level + 1).length === 0);
                    const result = { staff, line, stacked, columns: stacked ? Math.ceil(line.length / STACK_ROWS) : 0 };
                    layouts.set(id, result);

                    return result;
                };

                const measure = (id, level) => {
                    const { staff, line, stacked, columns } = split(id, level);
                    let lineWidth = 0;

                    if (stacked) {
                        lineWidth = STACK_INDENT + columns * CARD_W + (columns - 1) * H_GAP;
                    } else {
                        line.forEach((childId, index) => {
                            lineWidth += measure(childId, level + 1) + (index > 0 ? H_GAP : 0);
                        });
                    }

                    staff.forEach((childId) => measure(childId, level + 1));

                    const own = staff.length ? CARD_W + 2 * (STAFF_GAP + CARD_W) : CARD_W;
                    const width = Math.max(own, lineWidth);
                    widths.set(id, width);

                    return width;
                };

                const place = (id, left, top, level, extra = {}) => {
                    const width = widths.get(id) ?? CARD_W;
                    const center = left + width / 2;
                    this.pos.set(id, { x: center - CARD_W / 2, y: top, level, ...extra });

                    const { staff, line, stacked, columns } = split(id, level);

                    staff.forEach((childId, index) => {
                        this.pos.set(childId, {
                            x: center + CARD_W / 2 + STAFF_GAP,
                            y: top + index * (H + STAFF_STACK_GAP),
                            level: level + 1,
                            staff: true,
                        });
                    });

                    const staffExtra = staff.length > 1 ? (staff.length - 1) * (H + STAFF_STACK_GAP) : 0;
                    const childTop = top + H + V_GAP + staffExtra;

                    if (stacked) {
                        const blockWidth = STACK_INDENT + columns * CARD_W + (columns - 1) * H_GAP;
                        const blockLeft = center - blockWidth / 2;

                        line.forEach((childId, index) => {
                            const column = Math.floor(index / STACK_ROWS);
                            const row = index % STACK_ROWS;
                            const columnLeft = blockLeft + STACK_INDENT + column * (CARD_W + H_GAP);

                            this.pos.set(childId, {
                                x: columnLeft,
                                y: childTop + row * (H + STACK_GAP),
                                level: level + 1,
                                stack: true,
                                stackTop: childTop,
                                spineX: columnLeft - STACK_INDENT / 2,
                            });
                        });

                        return;
                    }

                    let lineWidth = 0;

                    line.forEach((childId, index) => {
                        lineWidth += widths.get(childId) + (index > 0 ? H_GAP : 0);
                    });

                    let cursor = center - lineWidth / 2;

                    line.forEach((childId) => {
                        place(childId, cursor, childTop, level + 1);
                        cursor += widths.get(childId) + H_GAP;
                    });
                };

                measure(start, 1);
                place(start, 0, 0, 1);

                let minX = Infinity;
                let minY = Infinity;
                let maxX = -Infinity;
                let maxY = -Infinity;

                for (const point of this.pos.values()) {
                    minX = Math.min(minX, point.x);
                    minY = Math.min(minY, point.y);
                    maxX = Math.max(maxX, point.x + CARD_W + (this.mode === 'edit' ? 30 : 0));
                    maxY = Math.max(maxY, point.y + H + PILL_SPACE);
                }

                this.bounds = { minX, minY, maxX, maxY };
                this.render();
            },

            // ------------------------------------------------------------- dibujo

            areaClass(color) {
                return AREA_COLORS.includes(color) ? color : 'navy';
            },

            render() {
                const layer = this.$refs.layer;

                if (! layer) {
                    return;
                }

                const H = this.cardH;
                const edges = [];
                const cards = [];

                for (const [id, point] of this.pos) {
                    const node = this.node(id);

                    if (node.parent !== null && this.pos.has(node.parent)) {
                        const parent = this.pos.get(node.parent);
                        const fromX = parent.x + CARD_W / 2;
                        const fromY = parent.y + H;

                        if (point.staff) {
                            const staffFromX = parent.x + CARD_W;
                            const staffFromY = parent.y + H / 2;
                            const midX = staffFromX + STAFF_GAP / 2;
                            edges.push('<path class="oc-edge oc-edge-staff" d="M' + staffFromX + ' ' + staffFromY + ' H' + midX + ' V' + (point.y + H / 2) + ' H' + point.x + '"/>');
                        } else if (point.stack) {
                            const midY = point.stackTop - V_GAP / 2;
                            edges.push('<path class="oc-edge" d="M' + fromX + ' ' + fromY + ' V' + midY + ' H' + point.spineX + ' V' + (point.y + H / 2) + ' H' + point.x + '"/>');
                        } else {
                            const toX = point.x + CARD_W / 2;
                            const midY = point.y - V_GAP / 2;
                            edges.push('<path class="oc-edge" d="M' + fromX + ' ' + fromY + ' V' + midY + ' H' + toX + ' V' + point.y + '"/>');
                        }
                    }

                    cards.push(this.cardSvg(node, point));
                }

                layer.innerHTML = edges.join('') + cards.join('');
                this.applyTransform();
                this.renderMinimap();
            },

            cardSvg(node, point) {
                const H = this.cardH;
                const editing = this.mode === 'edit' && ! this.readOnly;
                const classes = ['oc-node'];
                const occupants = node.occupants || [];
                const first = occupants[0] || null;
                const vacant = occupants.length === 0;
                const diffState = this.diff?.status_by_key?.[node.key] || null;

                if (vacant) {
                    classes.push('oc-vacant');
                }

                if (node.kind === 'STAFF') {
                    classes.push('oc-staff');
                }

                if (node.id === this.selectedId) {
                    classes.push('oc-selected');
                }

                if (node.id === this.matchId) {
                    classes.push('oc-match');
                }

                if (diffState) {
                    classes.push('oc-diff-' + diffState);
                }

                const photo = this.fields.photo;
                const textX = photo ? 60 : 14;
                const maxText = CARD_W - textX - 14;
                let out = '<g class="' + classes.join(' ') + '" data-node="' + node.id + '" transform="translate(' + point.x + ' ' + point.y + ')">';

                out += '<rect class="oc-card" width="' + CARD_W + '" height="' + H + '" rx="12"/>';
                out += '<rect class="oc-bar oc-area-' + this.areaClass(node.color) + '" width="' + CARD_W + '" height="4" rx="2"/>';

                if (photo) {
                    if (first && first.avatar) {
                        out += '<circle class="oc-avatar-bg" cx="32" cy="' + (H / 2) + '" r="19"/>';
                        out += '<image href="' + esc(this.avatarCache.get(first.avatar) || first.avatar) + '" x="13" y="' + (H / 2 - 19) + '" width="38" height="38" clip-path="url(#oc-avatar-clip)" preserveAspectRatio="xMidYMid slice"/>';
                    } else if (first) {
                        out += '<circle class="oc-avatar-bg" cx="32" cy="' + (H / 2) + '" r="19"/>';
                        out += '<text class="oc-initials" x="32" y="' + (H / 2 + 4) + '" text-anchor="middle">' + esc(first.initials) + '</text>';
                    } else {
                        out += '<circle class="oc-avatar-empty" cx="32" cy="' + (H / 2) + '" r="18"/>';
                    }
                }

                out += '<text class="oc-title" x="' + textX + '" y="28">' + esc(fit(node.title, maxText, FONT_TITLE)) + '</text>';

                if (vacant) {
                    out += '<text class="oc-person oc-vacant-label" x="' + textX + '" y="47">' + (node.seats > 1 ? node.seats + ' plazas por asignar' : 'Por asignar') + '</text>';
                } else {
                    const extra = occupants.length > 1 ? ' +' + (occupants.length - 1) : '';
                    const interim = first.role === 'INTERINO' ? ' (interino)' : '';
                    out += '<text class="oc-person" x="' + textX + '" y="47">' + esc(fit(first.name + interim, maxText - textWidth(extra, FONT_PERSON), FONT_PERSON) + extra) + '</text>';
                }

                const meta = [];

                if (node.kind === 'STAFF') {
                    meta.push('Staff');
                }

                if (this.fields.department && node.depto) {
                    meta.push(node.depto);
                }

                if (! vacant && node.vacancies > 0) {
                    meta.push(node.vacancies + ' por asignar');
                }

                if (meta.length) {
                    out += '<text class="oc-meta" x="' + textX + '" y="66">' + esc(fit(meta.join(' · '), maxText, FONT_META)) + '</text>';
                }

                if (this.fields.contact && first && (first.phone || first.email)) {
                    out += '<text class="oc-meta oc-contact" x="' + textX + '" y="82">' + esc(fit(first.phone || first.email, maxText, FONT_META)) + '</text>';
                }

                if (occupants.some((occupant) => ! occupant.active)) {
                    out += '<circle class="oc-flag-error" cx="' + (CARD_W - 12) + '" cy="16" r="5"><title>Ocupante inactivo en RRHH</title></circle>';
                } else if (occupants.some((occupant) => occupant.dual)) {
                    out += '<circle class="oc-flag-warning" cx="' + (CARD_W - 12) + '" cy="16" r="5"><title>Titular de más de un puesto</title></circle>';
                }

                const totalKids = (this.kids.get(node.id) || []).length;

                if (totalKids > 0) {
                    const hidden = this.hiddenKidsCount(node.id, point.level);
                    const label = hidden > 0 ? '+' + hidden : '−';
                    const pillWidth = Math.max(28, textWidth(label, FONT_META) + 16);
                    const pillX = editing ? CARD_W - pillWidth - 8 : CARD_W / 2 - pillWidth / 2;
                    out += '<g class="oc-pill' + (hidden > 0 ? ' oc-pill-collapsed' : '') + '" data-toggle="' + node.id + '" transform="translate(' + pillX + ' ' + (H + 5) + ')">';
                    out += '<rect width="' + pillWidth + '" height="18" rx="9"/>';
                    out += '<text x="' + (pillWidth / 2) + '" y="13" text-anchor="middle">' + label + '</text>';
                    out += '<title>' + (hidden > 0 ? 'Mostrar ' + hidden + ' puesto(s)' : 'Plegar') + '</title>';
                    out += '</g>';
                }

                if (editing && node.kind !== 'STAFF') {
                    out += '<g class="oc-add" data-add="' + node.id + '" data-kind="LINEA" transform="translate(' + (CARD_W / 2) + ' ' + (H + 14) + ')"><circle r="11"/><path d="M-5 0 H5 M0 -5 V5"/><title>Agregar puesto debajo</title></g>';
                    out += '<g class="oc-add oc-add-staff" data-add="' + node.id + '" data-kind="STAFF" transform="translate(' + (CARD_W + 14) + ' ' + (H / 2) + ')"><circle r="10"/><path d="M-4.5 0 H4.5 M0 -4.5 V4.5"/><title>Agregar staff o asesoría al lado</title></g>';
                }

                out += '</g>';

                return out;
            },

            applyTransform() {
                if (this.$refs.layer) {
                    this.$refs.layer.setAttribute('transform', 'translate(' + this.view.x + ' ' + this.view.y + ') scale(' + this.view.k + ')');
                }

                this.renderMinimapViewport();
                this.positionOverlays();
            },

            measureViewport() {
                const stage = this.$refs.stage;

                if (stage) {
                    const rect = stage.getBoundingClientRect();
                    this.viewport = { w: Math.max(200, rect.width), h: Math.max(200, rect.height) };
                }
            },

            get panelOpen() {
                return this.drawerOpen || this.detailsOpen || (this.mode === 'view' && this.tab === 'present');
            },

            get panelWidth() {
                return this.panelOpen && this.viewport.w > 767 ? 340 : 0;
            },

            get panelHeight() {
                return this.panelOpen && this.viewport.w <= 767 ? Math.round(this.viewport.h * 0.6) : 0;
            },

            /**
             * Encuadra el árbol. En la carga inicial no baja del 50 %: una empresa grande
             * encuadrada entera queda ilegible, así que se muestra la cabeza centrada
             * arriba y el resto se recorre arrastrando o con el minimapa.
             */
            fitView(initial = false) {
                this.measureViewport();
                const b = this.bounds;
                const available = Math.max(200, this.viewport.w - this.panelWidth);
                const width = Math.max(1, b.maxX - b.minX);
                const height = Math.max(1, b.maxY - b.minY);
                const k = Math.min(1, Math.max(MIN_ZOOM, Math.min((available - 48) / width, (this.viewport.h - 48) / height)));

                if (initial && k < 0.5) {
                    const head = this.pos.get(this.focusId ?? this.rootId);
                    const zoom = 0.5;

                    this.view = {
                        k: zoom,
                        x: available / 2 - ((head ? head.x : b.minX) + CARD_W / 2) * zoom,
                        y: 24 - b.minY * zoom,
                    };

                    this.applyTransform();

                    return;
                }

                this.view = {
                    k,
                    x: (available - width * k) / 2 - b.minX * k,
                    y: Math.max(24, (this.viewport.h - height * k) / 2) - b.minY * k,
                };

                this.applyTransform();
            },

            zoomBy(factor, cx = this.viewport.w / 2, cy = this.viewport.h / 2) {
                const k = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, this.view.k * factor));
                const ratio = k / this.view.k;

                this.view = {
                    k,
                    x: cx - (cx - this.view.x) * ratio,
                    y: cy - (cy - this.view.y) * ratio,
                };

                this.applyTransform();
            },

            resetZoom() {
                this.zoomBy(1 / this.view.k);
            },

            get zoomLabel() {
                return Math.round(this.view.k * 100) + ' %';
            },

            centerOn(id, minZoom = 0.8) {
                const point = this.pos.get(id);

                if (! point) {
                    return;
                }

                const k = Math.max(this.view.k, minZoom);
                const available = Math.max(200, this.viewport.w - this.panelWidth);
                const availableHeight = Math.max(120, this.viewport.h - this.panelHeight);
                this.view = {
                    k,
                    x: available / 2 - (point.x + CARD_W / 2) * k,
                    y: availableHeight / 2 - (point.y + this.cardH / 2) * k,
                };

                this.applyTransform();
            },

            /**
             * Si la tarjeta elegida quedó fuera de la vista, la trae; si se ve, no mueve nada.
             */
            ensureVisible(id) {
                const point = this.pos.get(id);

                if (! point) {
                    return;
                }

                const x = this.view.x + point.x * this.view.k;
                const y = this.view.y + point.y * this.view.k;
                const right = this.viewport.w - this.panelWidth;

                if (x < 0 || y < 0 || x + CARD_W * this.view.k > right || y + this.cardH * this.view.k > this.viewport.h - this.panelHeight) {
                    this.centerOn(id, Math.max(this.view.k, 0.7));
                }
            },

            screenRectOf(id) {
                const point = this.pos.get(id);

                if (! point) {
                    return null;
                }

                return {
                    x: this.view.x + point.x * this.view.k,
                    y: this.view.y + point.y * this.view.k,
                    w: CARD_W * this.view.k,
                    h: this.cardH * this.view.k,
                };
            },

            get popoverVisible() {
                return this.mode === 'edit' && ! this.readOnly && this.selected !== null && ! this.detailsOpen && this.layout !== 'doc' && this.renaming === null && this.docEditId === null;
            },

            positionOverlays() {
                const popover = this.$refs.popover;

                if (popover && this.selectedId !== null) {
                    const rect = this.screenRectOf(this.selectedId);

                    if (rect) {
                        const width = 268;
                        let left = rect.x + rect.w + 36;

                        if (left + width > this.viewport.w - 8) {
                            left = Math.max(8, rect.x - width - 16);
                        }

                        const top = Math.min(Math.max(8, rect.y), Math.max(8, this.viewport.h - 340));
                        popover.style.transform = 'translate(' + Math.round(left) + 'px, ' + Math.round(top) + 'px)';
                    }
                }

                const rename = this.$refs.rename;

                if (rename && this.renaming !== null) {
                    const rect = this.screenRectOf(this.renaming);

                    if (rect) {
                        rename.style.transform = 'translate(' + Math.round(rect.x) + 'px, ' + Math.round(rect.y + 8 * this.view.k) + 'px)';
                        rename.style.width = Math.max(220, Math.round(rect.w)) + 'px';
                    }
                }
            },

            // ------------------------------------------------------------- minimapa

            renderMinimap() {
                const svg = this.$refs.minimap;

                if (! svg) {
                    return;
                }

                const b = this.bounds;
                const width = Math.max(1, b.maxX - b.minX);
                const height = Math.max(1, b.maxY - b.minY);
                svg.setAttribute('viewBox', b.minX + ' ' + b.minY + ' ' + width + ' ' + height);

                let out = '';

                for (const [id, point] of this.pos) {
                    out += '<rect class="oc-mini-node' + (id === this.selectedId ? ' oc-mini-selected' : '') + '" x="' + point.x + '" y="' + point.y + '" width="' + CARD_W + '" height="' + this.cardH + '" rx="16"/>';
                }

                out += '<rect class="oc-mini-viewport" x="0" y="0" width="0" height="0"/>';
                svg.innerHTML = out;
                this.renderMinimapViewport();
            },

            renderMinimapViewport() {
                const rect = this.$refs.minimap?.querySelector('.oc-mini-viewport');

                if (! rect) {
                    return;
                }

                rect.setAttribute('x', String(-this.view.x / this.view.k));
                rect.setAttribute('y', String(-this.view.y / this.view.k));
                rect.setAttribute('width', String(this.viewport.w / this.view.k));
                rect.setAttribute('height', String(this.viewport.h / this.view.k));
            },

            onMinimapClick(event) {
                const svg = this.$refs.minimap;
                const point = svg.createSVGPoint();
                point.x = event.clientX;
                point.y = event.clientY;
                const local = point.matrixTransform(svg.getScreenCTM().inverse());

                this.view.x = this.viewport.w / 2 - local.x * this.view.k;
                this.view.y = this.viewport.h / 2 - local.y * this.view.k;
                this.applyTransform();
            },

            // ------------------------------------------------------- lienzo: ratón

            stagePoint(event) {
                const rect = this.$refs.stage.getBoundingClientRect();

                return { x: event.clientX - rect.left, y: event.clientY - rect.top };
            },

            onWheel(event) {
                event.preventDefault();
                const point = this.stagePoint(event);

                if (event.ctrlKey || event.metaKey) {
                    this.zoomBy(Math.exp(-event.deltaY * 0.0022), point.x, point.y);

                    return;
                }

                this.view.x -= event.deltaX;
                this.view.y -= event.deltaY;
                this.applyTransform();
            },

            onPointerDown(event) {
                if (event.button !== undefined && event.button !== 0) {
                    return;
                }

                const add = event.target.closest('[data-add]');

                if (add) {
                    event.stopPropagation();
                    this.addQuick(Number(add.dataset.add), add.dataset.kind);

                    return;
                }

                const toggle = event.target.closest('[data-toggle]');

                if (toggle) {
                    this.toggleCollapse(Number(toggle.dataset.toggle));
                    event.stopPropagation();

                    return;
                }

                const nodeEl = event.target.closest('[data-node]');
                const point = this.stagePoint(event);

                this.pointer = {
                    id: event.pointerId,
                    startX: point.x,
                    startY: point.y,
                    viewX: this.view.x,
                    viewY: this.view.y,
                    nodeId: nodeEl ? Number(nodeEl.dataset.node) : null,
                    dragging: false,
                    moved: false,
                };

                try {
                    this.$refs.stage.setPointerCapture(event.pointerId);
                } catch (error) {
                    // Sin captura el arrastre igual funciona mientras el puntero siga en el lienzo.
                }
            },

            onPointerMove(event) {
                if (! this.pointer || this.pointer.id !== event.pointerId) {
                    return;
                }

                const point = this.stagePoint(event);
                const dx = point.x - this.pointer.startX;
                const dy = point.y - this.pointer.startY;

                if (! this.pointer.moved && Math.hypot(dx, dy) < DRAG_THRESHOLD) {
                    return;
                }

                this.pointer.moved = true;
                const canDrag = this.mode === 'edit' && ! this.readOnly && this.pointer.nodeId !== null && this.node(this.pointer.nodeId)?.parent !== null;

                if (canDrag) {
                    this.pointer.dragging = true;
                    this.updateDrag(event, { type: 'node', id: this.pointer.nodeId, label: this.node(this.pointer.nodeId).title });

                    return;
                }

                this.view.x = this.pointer.viewX + dx;
                this.view.y = this.pointer.viewY + dy;
                this.applyTransform();
            },

            onPointerUp(event) {
                if (! this.pointer || this.pointer.id !== event.pointerId) {
                    return;
                }

                const pointer = this.pointer;
                this.pointer = null;

                try {
                    this.$refs.stage.releasePointerCapture(event.pointerId);
                } catch (error) {
                    // Nada que liberar.
                }

                if (pointer.dragging) {
                    const target = this.dragTargetId;
                    this.clearDrag();

                    if (target !== null && this.canDropNodeOn(pointer.nodeId, target)) {
                        this.move(pointer.nodeId, target, (this.kids.get(target) || []).filter((id) => id !== pointer.nodeId).length);
                    }

                    return;
                }

                if (! pointer.moved) {
                    this.select(pointer.nodeId, false);
                }
            },

            onDoubleClick(event) {
                const nodeEl = event.target.closest('[data-node]');

                if (nodeEl && this.mode === 'edit' && ! this.readOnly) {
                    this.startRename(Number(nodeEl.dataset.node));
                }
            },

            canDropNodeOn(dragId, targetId) {
                const target = this.node(targetId);

                return target !== null
                    && targetId !== dragId
                    && target.kind !== 'STAFF'
                    && ! this.isDescendant(targetId, dragId);
            },

            canDropItemOn(item, targetId) {
                const target = this.node(targetId);

                if (! target) {
                    return false;
                }

                if (item.type === 'person') {
                    return target.occupants.length < target.seats;
                }

                return target.kind !== 'STAFF';
            },

            /**
             * Arrastre compartido: una tarjeta del lienzo o un elemento de la paleta. El
             * destino puede ser una tarjeta o una línea del documento.
             */
            updateDrag(event, item) {
                const ghost = this.$refs.ghost;

                if (ghost) {
                    ghost.hidden = false;
                    ghost.style.transform = 'translate(' + (event.clientX + 14) + 'px, ' + (event.clientY + 14) + 'px)';
                    ghost.querySelector('[data-ghost-title]').textContent = item.label;
                }

                const under = document.elementFromPoint(event.clientX, event.clientY);
                const target = under?.closest?.('[data-node]') || under?.closest?.('[data-row]');
                const targetId = target ? Number(target.dataset.node || target.dataset.row) : null;
                const valid = targetId !== null && (item.type === 'node' ? this.canDropNodeOn(item.id, targetId) : this.canDropItemOn(item, targetId));
                const overEmptyStage = targetId === null && ! this.hasNodes && under?.closest?.('.oc-stage') && item.type !== 'person';

                this.$root.querySelectorAll('.oc-drop-ok, .oc-drop-bad').forEach((el) => el.classList.remove('oc-drop-ok', 'oc-drop-bad'));

                if (targetId !== null && ! (item.type === 'node' && targetId === item.id)) {
                    this.$root.querySelectorAll('[data-node="' + targetId + '"], [data-row="' + targetId + '"]').forEach((el) => el.classList.add(valid ? 'oc-drop-ok' : 'oc-drop-bad'));
                }

                this.dragTargetId = targetId;

                if (ghost) {
                    const hint = ghost.querySelector('[data-ghost-hint]');
                    const targetTitle = targetId !== null ? this.node(targetId)?.title : '';

                    if (overEmptyStage) {
                        hint.textContent = 'Soltar: será el puesto principal';
                    } else if (targetId === null || (item.type === 'node' && targetId === item.id)) {
                        hint.textContent = 'Suelta sobre un puesto';
                    } else if (! valid) {
                        hint.textContent = item.type === 'person' ? '«' + targetTitle + '» no tiene plazas libres' : 'No puede quedar ahí';
                    } else if (item.type === 'person') {
                        hint.textContent = 'Asignar a «' + targetTitle + '»';
                    } else {
                        hint.textContent = (item.type === 'node' ? 'Pasa a reportar a «' : 'Agregar debajo de «') + targetTitle + '»';
                    }
                }
            },

            clearDrag() {
                if (this.$refs.ghost) {
                    this.$refs.ghost.hidden = true;
                }

                this.$root.querySelectorAll('.oc-drop-ok, .oc-drop-bad').forEach((el) => el.classList.remove('oc-drop-ok', 'oc-drop-bad'));
                this.dragTargetId = null;
            },

            // ------------------------------------------------------------- paleta

            get paletteAreas() {
                const query = normalize(this.paletteQuery);
                const used = new Set((this.data?.nodes || []).map((node) => node.depto_id).filter((id) => id !== null));

                return (this.cfg.palette?.areas || [])
                    .filter((area) => ! query || normalize(area.name).includes(query))
                    .map((area) => ({ ...area, used: used.has(area.id) }));
            },

            get paletteCargoGroups() {
                const query = normalize(this.paletteQuery);
                const used = new Set((this.data?.nodes || []).map((node) => node.cargo_id).filter((id) => id !== null));
                const waiting = new Map();

                (this.data?.unassigned || []).forEach((person) => {
                    if (person.cargo_id) {
                        waiting.set(person.cargo_id, (waiting.get(person.cargo_id) || 0) + 1);
                    }
                });

                const areaNames = new Map((this.cfg.palette?.areas || []).map((area) => [area.id, area.name]));
                const groups = new Map();

                (this.cfg.palette?.cargos || [])
                    .filter((cargo) => ! query || normalize(cargo.name).includes(query))
                    .forEach((cargo) => {
                        const label = areaNames.get(cargo.departamento_id) || 'Sin departamento';

                        if (! groups.has(label)) {
                            groups.set(label, []);
                        }

                        groups.get(label).push({ ...cargo, used: used.has(cargo.id), waiting: waiting.get(cargo.id) || 0 });
                    });

                return [...groups.entries()].map(([label, cargos]) => ({ label, cargos }));
            },

            get palettePeople() {
                const query = normalize(this.paletteQuery);

                return (this.data?.unassigned || []).filter((person) => ! query || normalize(person.name + ' ' + (person.cargo || '')).includes(query));
            },

            onPaletteDown(event, item) {
                if (this.readOnly || (event.button !== undefined && event.button !== 0)) {
                    return;
                }

                event.preventDefault();
                this.paletteDrag = { item, startX: event.clientX, startY: event.clientY, moved: false, pointerId: event.pointerId };
            },

            onWindowPointerMove(event) {
                const drag = this.paletteDrag;

                if (! drag || drag.pointerId !== event.pointerId) {
                    return;
                }

                if (! drag.moved && Math.hypot(event.clientX - drag.startX, event.clientY - drag.startY) < DRAG_THRESHOLD) {
                    return;
                }

                drag.moved = true;
                this.updateDrag(event, drag.item);
            },

            onWindowPointerUp(event) {
                const drag = this.paletteDrag;

                if (! drag || drag.pointerId !== event.pointerId) {
                    return;
                }

                this.paletteDrag = null;

                if (! drag.moved) {
                    this.usePaletteItem(drag.item, this.selectedId);

                    return;
                }

                const target = this.dragTargetId;
                const under = document.elementFromPoint(event.clientX, event.clientY);
                const onStage = Boolean(under?.closest?.('.oc-stage'));
                this.clearDrag();

                if (target !== null) {
                    if (this.canDropItemOn(drag.item, target)) {
                        this.usePaletteItem(drag.item, target);
                    } else {
                        this.toast(drag.item.type === 'person' ? 'Ese puesto no tiene plazas libres. Agrega una plaza primero.' : 'Un puesto staff no tiene subordinados.', 'warning');
                    }
                } else if (onStage && ! this.hasNodes && drag.item.type !== 'person') {
                    this.usePaletteItem(drag.item, null);
                }
            },

            /**
             * Aplica un elemento de la paleta: soltado sobre un puesto o tocado con un
             * puesto elegido. Un cargo crea el puesto con su gente; un área crea el
             * puesto con ese departamento; una persona queda asignada.
             */
            usePaletteItem(item, targetId) {
                if (this.readOnly) {
                    return;
                }

                if (targetId === null && this.hasNodes) {
                    this.toast(item.type === 'person' ? 'Elige primero el puesto donde va esta persona, o arrástrala sobre él.' : 'Elige primero de qué puesto depende, o arrástralo sobre él.', 'info');

                    return;
                }

                if (item.type === 'person') {
                    if (! this.node(targetId)) {
                        this.toast('Agrega primero el puesto principal.', 'info');

                        return;
                    }

                    this.assign({ id: item.id, name: item.label }, 'TITULAR', targetId);

                    return;
                }

                const parent = targetId;
                const parentNode = parent !== null ? this.node(parent) : null;

                if (parentNode && parentNode.kind === 'STAFF') {
                    this.toast('«' + parentNode.title + '» es staff: un puesto staff no tiene subordinados.', 'warning');

                    return;
                }

                const after = (result) => {
                    if (result?.position_id) {
                        if (parent !== null) {
                            this.collapsed.delete(parent);
                        }

                        this.relayout();

                        if (parent === null) {
                            this.$nextTick(() => {
                                this.measureViewport();
                                this.fitView();
                            });
                        }

                        this.select(result.position_id, false);
                        this.ensureVisible(result.position_id);

                        if (item.type === 'cargo') {
                            this.toast(result.assigned ? '«' + item.label + '» agregado con ' + result.assigned + ' persona(s).' : '«' + item.label + '» agregado.', 'success');
                        }
                    }
                };

                if (item.type === 'cargo') {
                    this.call('addFromCargo', [parent, item.id, null], {
                        undo: (result) => ({ type: 'remove', id: result.position_id, release: true, label: 'agregar «' + item.label + '»' }),
                    }).then(after);

                    return;
                }

                this.call('addPosition', [parent, item.label, 'LINEA', null, item.id], {
                    undo: (result) => ({ type: 'remove', id: result.position_id, label: 'agregar «' + item.label + '»' }),
                }).then(after);
            },

            // ------------------------------------------------------------ plegado

            toggleCollapse(id) {
                if (this.collapsed.has(id) || this.hiddenKidsCount(id, this.pos.get(id)?.level ?? 1) > 0) {
                    this.collapsed.delete(id);

                    if (this.mode !== 'edit' && this.depth !== '') {
                        this.depth = '';
                    }
                } else {
                    this.collapsed.add(id);
                }

                this.relayout();
            },

            expandAll() {
                this.collapsed = new Set();
                this.depth = '';
                this.relayout();
                this.fitView();
            },

            collapseToLevel(level) {
                this.collapsed = new Set();

                const walk = (id, current) => {
                    const children = this.kids.get(id) || [];

                    if (children.length && current >= level) {
                        this.collapsed.add(id);

                        return;
                    }

                    children.forEach((child) => walk(child, current + 1));
                };

                if (this.rootId !== null) {
                    walk(this.rootId, 1);
                }

                this.relayout();
                this.fitView();
            },

            reveal(id) {
                let changed = false;

                for (const ancestor of this.pathTo(id).slice(0, -1)) {
                    if (this.collapsed.delete(ancestor.id)) {
                        changed = true;
                    }
                }

                if (this.focusId !== null && id !== this.focusId && ! this.isDescendant(id, this.focusId)) {
                    this.focusId = null;
                    changed = true;
                }

                if (this.mode !== 'edit' && this.depth !== '' && this.pathTo(id).length > Number(this.depth)) {
                    this.depth = '';
                    changed = true;
                }

                if (this.node(id) && ! this.isVisibleNode(this.node(id))) {
                    this.showVacancies = true;
                    changed = true;
                }

                if (changed) {
                    this.relayout();
                }
            },

            // ------------------------------------------------------------ selección

            select(id, center = true) {
                if (this.docEditId !== null && this.docEditId !== id) {
                    this.stopDocEdit(true);
                }

                if (this.renaming !== null && this.renaming !== id) {
                    this.finishRename(true);
                }

                const previous = this.selectedId;
                this.selectedId = id;
                this.personSearch = '';
                this.personResults = [];
                this.popoverSearch = false;
                this.popoverMenu = false;
                this.history = [];
                this.sections.history = false;

                if (id === null) {
                    this.drawerOpen = false;
                    this.detailsOpen = false;
                    this.refreshSelectionClass(previous, null);

                    return;
                }

                if (this.mode === 'view') {
                    this.drawerOpen = true;
                }

                this.syncEdit();
                this.reveal(id);
                this.refreshSelectionClass(previous, id);

                if (center) {
                    this.centerOn(id, this.view.k);
                } else {
                    this.ensureVisible(id);
                }

                this.$nextTick(() => {
                    this.positionOverlays();
                    this.$refs.doc?.querySelector('[data-row="' + id + '"]')?.scrollIntoView({ block: 'nearest' });
                });
            },

            refreshSelectionClass(previous, next) {
                const layer = this.$refs.layer;

                if (! layer) {
                    return;
                }

                if (previous !== null) {
                    layer.querySelector('[data-node="' + previous + '"]')?.classList.remove('oc-selected');
                }

                if (next !== null) {
                    layer.querySelector('[data-node="' + next + '"]')?.classList.add('oc-selected');
                }

                this.renderMinimap();
            },

            openDetails() {
                if (this.selectedId === null) {
                    return;
                }

                this.detailsOpen = true;
                this.popoverMenu = false;
                this.$nextTick(() => this.ensureVisible(this.selectedId));
            },

            closeDetails() {
                this.detailsOpen = false;
                this.drawerOpen = false;
                this.$nextTick(() => this.positionOverlays());
            },

            async toggleHistory() {
                this.sections.history = ! this.sections.history;

                if (! this.sections.history || ! this.selected) {
                    return;
                }

                this.historyLoading = true;

                try {
                    this.history = await this.server('positionHistory', this.selected.key);
                } catch (error) {
                    this.history = [];
                } finally {
                    this.historyLoading = false;
                }
            },

            focusTeam(id) {
                this.focusId = id;
                this.collapsed.delete(id);
                this.relayout();
                this.fitView();
            },

            clearFocus() {
                this.focusId = null;
                this.relayout();
                this.fitView();
            },

            get focusPath() {
                return this.focusId !== null ? this.pathTo(this.focusId) : [];
            },

            // -------------------------------------------------------------- búsqueda

            runSearch() {
                clearTimeout(this.timers.search);
                this.timers.search = setTimeout(() => {
                    const term = normalize(this.search);

                    if (term.length < 2) {
                        this.searchResults = [];

                        return;
                    }

                    this.searchResults = this.findNodes(term, 12).map((node) => ({
                        id: node.id,
                        title: node.title,
                        people: node.occupants.map((occupant) => occupant.name).join(' · ') || 'Por asignar',
                    }));
                    this.searchOpen = true;
                }, 200);
            },

            findNodes(term, limit) {
                const results = [];

                for (const node of this.data?.nodes || []) {
                    const haystack = normalize(node.title + ' ' + node.occupants.map((occupant) => occupant.name).join(' ') + ' ' + (node.depto || '') + ' ' + (node.cargo || ''));

                    if (haystack.includes(term)) {
                        results.push(node);
                    }

                    if (results.length >= limit) {
                        break;
                    }
                }

                return results;
            },

            pickSearch(id) {
                this.searchOpen = false;
                this.search = '';
                this.searchResults = [];
                this.matchId = id;
                this.select(id, true);
                this.centerOn(id, 0.9);
                clearTimeout(this.timers.match);
                this.$refs.layer?.querySelector('[data-node="' + id + '"]')?.classList.add('oc-match');
                this.timers.match = setTimeout(() => {
                    this.matchId = null;
                    this.$refs.layer?.querySelector('[data-node="' + id + '"]')?.classList.remove('oc-match');
                }, 2400);
            },

            // ------------------------------------------------------- Ctrl K

            openCommand() {
                this.cmd = { open: true, q: '', index: 0 };
                this.stopDocEdit(true);
                this.focusSoon(() => this.$refs.cmdInput);
            },

            /**
             * Enfoca un campo que acaba de aparecer: x-show lo hace visible en el
             * siguiente ciclo, así que se reintenta unos milisegundos.
             */
            focusSoon(resolve) {
                let tries = 0;
                const attempt = () => {
                    const element = resolve();

                    if (element && element.getClientRects().length > 0) {
                        element.focus();

                        return;
                    }

                    if (tries++ < 10) {
                        setTimeout(attempt, 20);
                    }
                };

                this.$nextTick(attempt);
            },

            closeCommand() {
                this.cmd.open = false;
            },

            get commandItems() {
                const query = normalize(this.cmd.q);
                const selected = this.selected;
                const editable = this.mode === 'edit' && ! this.readOnly;
                const items = [];
                const action = (label, run, hint = '') => items.push({ group: 'Acciones', label, hint, run });

                if (editable && selected) {
                    if (selected.kind !== 'STAFF') {
                        action('Agregar puesto debajo de «' + selected.title + '»', () => this.addQuick(selected.id, 'LINEA'));
                    }

                    action('Agregar staff a «' + selected.title + '»', () => this.addQuick(selected.id, 'STAFF'));
                    action('Renombrar «' + selected.title + '»', () => this.startRename(selected.id), 'F2');
                    action('Asignar persona a «' + selected.title + '»', () => {
                        this.popoverSearch = true;
                        this.$nextTick(() => document.getElementById('oc-popover-search')?.focus());
                    });
                    action('Más detalles de «' + selected.title + '»', () => this.openDetails());

                    if (selected.parent !== null) {
                        action('Quitar «' + selected.title + '»', () => this.askRemove(selected.id));
                    }
                }

                if (editable && ! this.hasNodes) {
                    action('Agregar el puesto principal', () => this.$nextTick(() => document.getElementById('oc-root-input')?.focus()));
                }

                if (editable) {
                    action('Revisar y publicar', () => { this.reviewOpen = true; });
                    action('Deshacer', () => this.undo(), 'Ctrl Z');
                    action('Vista: lienzo', () => { this.layout = 'canvas'; });
                    action('Vista: lienzo + documento', () => { this.layout = 'split'; });
                    action('Vista: documento', () => { this.layout = 'doc'; });
                    action(this.paletteOpen ? 'Ocultar la paleta de RRHH' : 'Mostrar la paleta de RRHH', () => { this.paletteOpen = ! this.paletteOpen; });
                }

                action('Desplegar todo', () => this.expandAll());
                action('Plegar al segundo nivel', () => this.collapseToLevel(2));
                action('Ajustar a la pantalla', () => this.fitView());

                if (this.mode === 'view') {
                    action('Presentar y exportar', () => { this.tab = 'present'; });
                }

                action('Atajos de teclado', () => { this.helpOpen = true; }, '?');

                let filtered = items.filter((item) => ! query || normalize(item.label).includes(query));

                if (query.length >= 2) {
                    this.findNodes(query, 8).forEach((node) => filtered.push({
                        group: 'Puestos',
                        label: 'Ir a «' + node.title + '»',
                        hint: node.occupants[0]?.name || 'Por asignar',
                        run: () => this.pickSearch(node.id),
                    }));

                    if (editable) {
                        (this.data?.unassigned || [])
                            .filter((person) => normalize(person.name).includes(query))
                            .slice(0, 5)
                            .forEach((person) => filtered.push({
                                group: 'Personas sin puesto',
                                label: selected ? 'Ubicar a ' + person.name + ' en «' + selected.title + '»' : 'Ubicar a ' + person.name,
                                hint: person.cargo || '',
                                run: () => (selected ? this.assign(person, 'TITULAR', selected.id) : this.openPalette('people', person.name)),
                            }));
                    }
                }

                filtered = filtered.slice(0, 30);
                filtered.forEach((item, index) => { item.index = index; });

                return filtered;
            },

            onCommandKey(event) {
                const items = this.commandItems;
                this.cmd.index = Math.max(0, Math.min(this.cmd.index, items.length - 1));

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    this.cmd.index = Math.min(items.length - 1, this.cmd.index + 1);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    this.cmd.index = Math.max(0, this.cmd.index - 1);
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    this.runCommand(items[this.cmd.index]);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    this.closeCommand();
                }
            },

            runCommand(item) {
                if (! item) {
                    return;
                }

                this.closeCommand();
                item.run();
            },

            openPalette(tab, query = '') {
                this.paletteOpen = true;
                this.paletteTab = tab;
                this.paletteQuery = query;
                this.reviewOpen = false;
            },

            // --------------------------------------------------- modo presentar

            applySavedView() {
                const view = this.views.find((item) => String(item.id) === String(this.selectedViewId));

                if (! view) {
                    return;
                }

                const root = view.root_key ? (this.data?.nodes || []).find((node) => node.key === view.root_key) : null;
                this.focusId = root ? root.id : null;
                this.depth = view.depth ? String(view.depth) : '';
                this.showVacancies = view.show_vacancies !== false;
                this.fields = {
                    photo: (view.card_fields || []).includes('photo'),
                    department: (view.card_fields || []).includes('department'),
                    contact: (view.card_fields || []).includes('contact'),
                };
                this.collapsed = new Set();
                this.newViewName = view.name;
                this.relayout();
                this.fitView();
            },

            presentationChanged() {
                this.relayout();
                this.fitView();
            },

            async saveView() {
                const result = await this.$wire.saveView({
                    name: this.newViewName,
                    root_key: this.focusId !== null ? this.node(this.focusId)?.key : null,
                    depth: this.depth === '' ? null : Number(this.depth),
                    card_fields: Object.keys(this.fields).filter((key) => this.fields[key]),
                    show_vacancies: this.showVacancies,
                });

                this.toast(result.message, result.ok ? 'success' : 'danger');

                if (result.ok) {
                    this.views = result.views || this.views;
                }
            },

            async deleteView() {
                if (! this.selectedViewId) {
                    return;
                }

                const result = await this.$wire.deleteView(Number(this.selectedViewId));
                this.toast(result.message, result.ok ? 'success' : 'danger');

                if (result.ok) {
                    this.views = result.views || [];
                    this.selectedViewId = '';
                }
            },

            async changeVersion() {
                if (! this.versionId) {
                    return;
                }

                this.busy = true;

                try {
                    const result = await this.$wire.loadVersion(Number(this.versionId));

                    if (! result.ok) {
                        this.toast(result.message, 'danger');

                        return;
                    }

                    this.focusId = null;
                    this.selectedId = null;
                    this.drawerOpen = false;
                    this.setData(result.payload, false);
                    this.applyDefaultCollapse();
                    this.relayout();
                    this.fitView(true);
                } finally {
                    this.busy = false;
                }
            },

            get isViewingCurrent() {
                return Number(this.versionId) === Number(this.cfg.currentVersionId);
            },

            async exportPdf() {
                if (this.exporting || ! this.data) {
                    return;
                }

                this.exporting = true;

                try {
                    const result = await this.$wire.requestPdfExport(Number(this.data.version.id), {
                        root_key: this.focusId !== null ? this.node(this.focusId)?.key : null,
                        depth: this.depth === '' ? null : Number(this.depth),
                        show_contact: this.fields.contact,
                        show_vacancies: this.showVacancies,
                    });

                    this.toast(result.message, result.ok ? 'success' : 'danger');
                } catch (error) {
                    this.toast('No se pudo pedir la exportación. Intenta de nuevo.', 'danger');
                } finally {
                    this.exporting = false;
                }
            },

            async inlineAvatars(svgText) {
                const urls = [...new Set([...svgText.matchAll(/href="([^"]+)"/g)].map((match) => match[1].replace(/&amp;/g, '&')))]
                    .filter((url) => ! url.startsWith('data:'));

                const toDataUrl = async (url) => {
                    const controller = new AbortController();
                    const timer = setTimeout(() => controller.abort(), 5000);

                    try {
                        const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin' });

                        if (! response.ok) {
                            return null;
                        }

                        const blob = await response.blob();

                        return await new Promise((resolve) => {
                            const reader = new FileReader();
                            reader.onload = () => resolve(reader.result);
                            reader.onerror = () => resolve(null);
                            reader.readAsDataURL(blob);
                        });
                    } catch (error) {
                        return null;
                    } finally {
                        clearTimeout(timer);
                    }
                };

                const pending = urls.slice(0, 400);

                for (let index = 0; index < pending.length; index += 6) {
                    const batch = pending.slice(index, index + 6);
                    const results = await Promise.all(batch.map(toDataUrl));

                    batch.forEach((url, position) => {
                        const replacement = results[position];
                        const escaped = esc(url);
                        svgText = svgText.split('href="' + escaped + '"').join(replacement ? 'href="' + replacement + '"' : 'href="" visibility="hidden"');
                    });
                }

                return svgText;
            },

            async exportPng() {
                if (this.exporting || ! this.data) {
                    return;
                }

                this.exporting = true;

                try {
                    const b = this.bounds;
                    const padding = 40;
                    const width = b.maxX - b.minX + padding * 2;
                    const height = b.maxY - b.minY + padding * 2 + 50;
                    const scale = Math.min(2, 8000 / Math.max(width, height));
                    const style = document.getElementById('oc-export-style')?.textContent || '';
                    const title = esc((this.focusId !== null ? this.node(this.focusId).title + ' · ' : '') + (this.data.version.name || 'Organigrama'));

                    let svg = '<svg class="oc-export" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="' + width + '" height="' + height + '" viewBox="0 0 ' + width + ' ' + height + '">'
                        + '<style>' + style + '</style>'
                        + '<defs><clipPath id="oc-avatar-clip" clipPathUnits="objectBoundingBox"><circle cx=".5" cy=".5" r=".5"/></clipPath></defs>'
                        + '<rect width="100%" height="100%" fill="#ffffff"/>'
                        + '<text x="' + padding + '" y="34" class="oc-export-title">' + title + '</text>'
                        + '<g transform="translate(' + (padding - b.minX) + ' ' + (padding + 50 - b.minY) + ')">' + this.$refs.layer.innerHTML + '</g>'
                        + '</svg>';

                    svg = await this.inlineAvatars(svg);

                    const image = new Image();
                    const blobUrl = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml;charset=utf-8' }));

                    await new Promise((resolve, reject) => {
                        image.onload = resolve;
                        image.onerror = reject;
                        image.src = blobUrl;
                    });

                    const canvas = document.createElement('canvas');
                    canvas.width = Math.round(width * scale);
                    canvas.height = Math.round(height * scale);
                    const context = canvas.getContext('2d');
                    context.scale(scale, scale);
                    context.drawImage(image, 0, 0);
                    URL.revokeObjectURL(blobUrl);

                    const pngBlob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));

                    if (! pngBlob) {
                        throw new Error('canvas');
                    }

                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(pngBlob);
                    link.download = 'organigrama-' + new Date().toISOString().slice(0, 10) + '.png';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(() => URL.revokeObjectURL(link.href), 4000);

                    this.toast('Imagen descargada.', 'success');
                } catch (error) {
                    this.toast('No se pudo generar la imagen. Prueba con un área más pequeña o usa el PDF.', 'danger');
                } finally {
                    this.exporting = false;
                }
            },

            // ------------------------------------------------------------ documento

            get docRows() {
                const rows = [];

                const walk = (id, level) => {
                    const node = this.node(id);

                    if (! node) {
                        return;
                    }

                    const children = this.kids.get(id) || [];
                    const first = node.occupants[0];
                    rows.push({
                        id,
                        level,
                        title: node.title,
                        who: first ? first.name + (node.occupants.length > 1 ? ' +' + (node.occupants.length - 1) : '') : '',
                        vacant: node.occupants.length === 0,
                        staff: node.kind === 'STAFF',
                        interim: node.occupants.some((occupant) => occupant.role === 'INTERINO'),
                        error: node.occupants.some((occupant) => ! occupant.active),
                        hasKids: children.length > 0,
                        collapsed: this.collapsed.has(id),
                        diff: this.diff?.status_by_key?.[node.key] || null,
                    });

                    if (! this.collapsed.has(id)) {
                        children.forEach((child) => walk(child, level + 1));
                    }
                };

                if (this.rootId !== null) {
                    walk(this.rootId, 0);
                }

                return rows;
            },

            startDocEdit(id, value = null) {
                if (this.readOnly || ! this.node(id)) {
                    return;
                }

                this.renaming = null;
                this.docEditId = id;
                this.docOriginal = this.node(id).title;
                this.docValue = value ?? (this.docOriginal === NEW_TITLE ? '' : this.docOriginal);
                this.mention = { open: false, term: '', results: [], index: 0 };
                this.slash = { open: false, index: 0 };

                if (this.selectedId !== id) {
                    this.select(id, false);
                }

                this.$nextTick(() => {
                    const input = document.getElementById('oc-doc-input');

                    if (input) {
                        input.focus();
                        input.select();
                    }
                });
            },

            stopDocEdit(commit = true) {
                const id = this.docEditId;

                if (id === null) {
                    return;
                }

                if (commit) {
                    this.commitDocTitle(id);
                }

                this.docEditId = null;
                this.mention.open = false;
                this.slash.open = false;
            },

            commitDocTitle(id) {
                const node = this.node(id);
                let value = this.docValue.replace(/@[^\s@]*$/, '').trim();

                if (value.startsWith('/')) {
                    value = '';
                }

                if (node && value.length >= 2 && value !== node.title) {
                    this.updateTitle(id, value);
                }
            },

            onDocKey(event) {
                if (this.docEditId !== null || event.target?.id === 'oc-doc-input') {
                    return;
                }

                const rows = this.docRows;

                if (this.selectedId === null) {
                    if (['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key) && rows[0]) {
                        event.preventDefault();
                        this.select(rows[0].id, false);
                    }

                    return;
                }

                const index = rows.findIndex((row) => row.id === this.selectedId);
                const id = this.selectedId;
                const node = this.node(id);

                if (event.key === 'ArrowDown' && ! event.altKey) {
                    event.preventDefault();

                    if (rows[index + 1]) {
                        this.select(rows[index + 1].id, false);
                    }
                } else if (event.key === 'ArrowUp' && ! event.altKey) {
                    event.preventDefault();

                    if (rows[index - 1]) {
                        this.select(rows[index - 1].id, false);
                    }
                } else if (event.key === 'ArrowRight') {
                    event.preventDefault();

                    if (this.collapsed.has(id)) {
                        this.toggleCollapse(id);
                    }
                } else if (event.key === 'ArrowLeft') {
                    event.preventDefault();

                    if (! this.collapsed.has(id) && (this.kids.get(id) || []).length) {
                        this.toggleCollapse(id);
                    } else if (node.parent !== null) {
                        this.select(node.parent, false);
                    }
                } else if (this.readOnly) {
                    return;
                } else if (event.key === 'Enter' || event.key === 'F2') {
                    event.preventDefault();
                    this.startDocEdit(id);
                } else if (event.key === 'Tab') {
                    event.preventDefault();
                    event.shiftKey ? this.outdent(id) : this.indent(id);
                } else if (event.altKey && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
                    event.preventDefault();
                    this.reorder(id, event.key === 'ArrowUp' ? -1 : 1);
                } else if ((event.key === 'Delete' || event.key === 'Backspace') && node.parent !== null) {
                    event.preventDefault();
                    this.askRemove(id);
                }
            },

            onDocInput() {
                const mention = this.docValue.match(/@([^\s@]{0,30})$/);

                if (mention) {
                    this.mention.open = true;
                    this.mention.term = mention[1];
                    this.mention.index = 0;
                    this.searchMention();
                } else {
                    this.mention.open = false;
                }

                this.slash.open = this.docValue.startsWith('/');
                this.slash.index = 0;
            },

            searchMention() {
                const local = (this.data?.unassigned || [])
                    .filter((person) => normalize(person.name).includes(normalize(this.mention.term)))
                    .slice(0, 6)
                    .map((person) => ({ id: person.id, name: person.name, cargo: person.cargo, initials: person.initials, waiting: true }));

                this.mention.results = local;
                clearTimeout(this.timers.mention);

                if (this.mention.term.length < 2) {
                    return;
                }

                this.timers.mention = setTimeout(async () => {
                    try {
                        const remote = await this.server('searchColaboradores', this.mention.term);
                        const known = new Set(this.mention.results.map((person) => person.id));
                        this.mention.results = [...this.mention.results, ...remote.filter((person) => ! known.has(person.id))].slice(0, 8);
                    } catch (error) {
                        // La lista local ya sirve.
                    }
                }, 280);
            },

            get slashItems() {
                const query = normalize(this.docValue.slice(1));
                const node = this.docEditId !== null ? this.node(this.docEditId) : null;
                const items = [];

                if (! node) {
                    return items;
                }

                if (node.parent !== null) {
                    items.push({ label: node.kind === 'STAFF' ? 'Hacer línea de mando' : 'Hacer staff o asesoría', run: () => this.updateField('kind', node.kind === 'STAFF' ? 'LINEA' : 'STAFF', true, node.id) });
                }

                items.push({ label: 'Agregar una plaza', run: () => this.updateField('seats', node.seats + 1, true, node.id) });

                if (node.seats > 1) {
                    items.push({ label: 'Quitar una plaza', run: () => this.updateField('seats', node.seats - 1, true, node.id) });
                }

                items.push({ label: 'Asignar persona…', run: () => { this.docValue = (this.docOriginal === NEW_TITLE ? '' : this.docOriginal) + ' @'; this.onDocInput(); }, keep: true });
                AREA_COLORS.forEach((color) => items.push({ label: 'Color ' + COLOR_LABELS[color].toLowerCase(), run: () => this.updateField('color', color, true, node.id) }));
                items.push({ label: 'Más detalles', run: () => { this.stopDocEdit(false); this.openDetails(); } });

                if (node.parent !== null) {
                    items.push({ label: 'Quitar puesto…', run: () => { this.stopDocEdit(false); this.askRemove(node.id); } });
                }

                return items.filter((item) => ! query || normalize(item.label).includes(query)).slice(0, 12);
            },

            onDocInputKey(event) {
                const id = this.docEditId;

                if (id === null) {
                    return;
                }

                const menu = this.mention.open ? this.mention.results : (this.slash.open ? this.slashItems : null);

                if (menu && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
                    event.preventDefault();
                    const state = this.mention.open ? this.mention : this.slash;
                    state.index = Math.max(0, Math.min(menu.length - 1, state.index + (event.key === 'ArrowDown' ? 1 : -1)));

                    return;
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();

                    if (this.mention.open || this.slash.open) {
                        this.mention.open = false;
                        this.slash.open = false;
                        this.docValue = this.docValue.replace(/@[^\s@]*$/, '').replace(/^\/.*$/, this.docOriginal === NEW_TITLE ? '' : this.docOriginal);

                        return;
                    }

                    this.docValue = this.docOriginal;
                    this.stopDocEdit(false);
                    this.$nextTick(() => this.$refs.doc?.focus());

                    return;
                }

                if (event.key === 'Enter') {
                    event.preventDefault();

                    if (this.mention.open && menu?.length) {
                        this.pickMention(menu[this.mention.index] || menu[0]);

                        return;
                    }

                    if (this.slash.open) {
                        const item = menu?.[this.slash.index] || menu?.[0];
                        this.slash.open = false;

                        if (! item?.keep) {
                            this.docValue = this.docOriginal === NEW_TITLE ? '' : this.docOriginal;
                        }

                        item?.run();

                        return;
                    }

                    this.commitDocTitle(id);
                    this.insertAfter(id);

                    return;
                }

                if (event.key === 'Tab') {
                    event.preventDefault();
                    this.commitDocTitle(id);
                    const moved = event.shiftKey ? this.outdent(id) : this.indent(id);

                    if (moved) {
                        moved.then(() => this.startDocEdit(id));
                    }

                    return;
                }

                if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && ! event.altKey) {
                    event.preventDefault();
                    const rows = this.docRows;
                    const index = rows.findIndex((row) => row.id === id);
                    const next = rows[index + (event.key === 'ArrowDown' ? 1 : -1)];
                    this.commitDocTitle(id);

                    if (next) {
                        this.startDocEdit(next.id);
                    }
                }
            },

            onDocBlur() {
                setTimeout(() => {
                    if (this.docEditId !== null && document.activeElement?.id !== 'oc-doc-input' && ! this.mention.open && ! this.slash.open) {
                        this.stopDocEdit(true);
                    }
                }, 150);
            },

            pickMention(person) {
                const id = this.docEditId;

                if (! person || id === null) {
                    return;
                }

                this.docValue = this.docValue.replace(/@[^\s@]*$/, '').trimEnd();
                this.mention.open = false;
                this.assign(person, 'TITULAR', id);
                this.$nextTick(() => document.getElementById('oc-doc-input')?.focus());
            },

            /**
             * Enter en el documento: el siguiente puesto, al mismo nivel. Debajo del
             * principal si se está en él.
             */
            insertAfter(id) {
                const node = this.node(id);

                if (! node) {
                    return;
                }

                const parent = node.parent ?? node.id;
                const index = node.parent === null ? 0 : (this.kids.get(node.parent) || []).indexOf(id) + 1;

                this.call('addPosition', [parent, NEW_TITLE, 'LINEA', index, null], {
                    undo: (result) => ({ type: 'remove', id: result.position_id, label: 'agregar un puesto' }),
                }).then((result) => {
                    if (result?.position_id) {
                        this.collapsed.delete(parent);
                        this.relayout();
                        this.startDocEdit(result.position_id, '');
                    } else {
                        this.docEditId = null;
                    }
                });
            },

            // ------------------------------------------------------------- edición

            syncEdit() {
                const node = this.selected;

                this.edit = node ? {
                    title: node.title,
                    kind: node.kind,
                    seats: node.seats,
                    color: node.color || '',
                    rrhh_cargo_id: node.cargo_id ?? '',
                    rrhh_departamento_id: node.depto_id ?? '',
                } : {};
            },

            get cargoGroups() {
                const departments = new Map((this.cfg.deptOptions || []).map((dept) => [dept.id, dept.name]));
                const groups = new Map();

                for (const cargo of this.cfg.cargoOptions || []) {
                    const label = departments.get(cargo.departamento_id) || 'Sin departamento';

                    if (! groups.has(label)) {
                        groups.set(label, []);
                    }

                    groups.get(label).push(cargo);
                }

                return [...groups.entries()].map(([label, cargos]) => ({ label, cargos }));
            },

            /**
             * El área la define el cargo cuando el cargo tiene departamento en RRHH.
             */
            get deptLockedByCargo() {
                const node = this.selected;

                if (! node || ! node.cargo_id) {
                    return false;
                }

                return Boolean((this.cfg.cargoOptions || []).find((cargo) => cargo.id === node.cargo_id)?.departamento_id);
            },

            colorLabel(color) {
                return COLOR_LABELS[color] || color;
            },

            call(method, args, options = {}) {
                if (this.readOnly) {
                    this.toast(this.readOnlyReason || 'Este borrador está en modo lectura.', 'warning');

                    return Promise.resolve(null);
                }

                const run = async () => {
                    this.saveState = 'saving';

                    try {
                        const result = await this.server(method, this.lock, ...args);

                        if (! result) {
                            this.saveState = 'error';

                            return null;
                        }

                        if (result.payload) {
                            this.setData(result.payload, true);
                            this.relayout();
                            this.refreshCompareSoon();
                        }

                        if (! result.ok) {
                            this.saveState = result.conflict ? 'saved' : 'error';
                            this.toast(result.message, result.conflict ? 'warning' : 'danger');

                            if (! result.payload) {
                                this.syncEdit();
                            }

                            return null;
                        }

                        this.lock = result.lock_version ?? this.lock;
                        this.saveState = 'saved';
                        this.savedAt = Date.now();
                        this.refreshSavedLabel();

                        if (options.undo) {
                            this.undoStack.push(options.undo(result));

                            if (this.undoStack.length > 50) {
                                this.undoStack.shift();
                            }
                        }

                        return result;
                    } catch (error) {
                        this.saveState = 'error';
                        this.toast('Se perdió la conexión al guardar. Revisa tu red; el cambio no se guardó.', 'danger');

                        return null;
                    }
                };

                const next = this.queue.then(run, run);
                this.queue = next.catch(() => null);

                return next;
            },

            move(id, parentId, index, skipUndo = false) {
                const node = this.node(id);

                if (! node) {
                    return Promise.resolve(null);
                }

                const oldParent = node.parent;
                const oldIndex = (this.kids.get(oldParent) || []).indexOf(id);

                return this.call('movePosition', [id, parentId, index], {
                    undo: skipUndo ? null : () => ({ type: 'move', id, parent: oldParent, index: oldIndex, label: 'mover «' + node.title + '»' }),
                }).then((result) => {
                    if (result) {
                        this.reveal(id);
                        this.refreshSelectionClass(null, this.selectedId);
                    }

                    return result;
                });
            },

            indent(id) {
                const node = this.node(id);

                if (! node || node.parent === null) {
                    return null;
                }

                const siblings = this.kids.get(node.parent) || [];
                const previous = siblings.indexOf(id) > 0 ? this.node(siblings[siblings.indexOf(id) - 1]) : null;

                if (! previous) {
                    this.toast('No hay un puesto arriba para colgarlo debajo.', 'info');

                    return null;
                }

                if (previous.kind === 'STAFF') {
                    this.toast('«' + previous.title + '» es staff: no puede tener subordinados.', 'warning');

                    return null;
                }

                this.collapsed.delete(previous.id);

                return this.move(id, previous.id, (this.kids.get(previous.id) || []).length);
            },

            outdent(id) {
                const parent = this.parentOf(id);

                if (! parent || parent.parent === null) {
                    this.toast('Ya está en el nivel más alto posible.', 'info');

                    return null;
                }

                const grandSiblings = this.kids.get(parent.parent) || [];

                return this.move(id, parent.parent, grandSiblings.indexOf(parent.id) + 1);
            },

            reorder(id, delta) {
                const node = this.node(id);

                if (! node || node.parent === null) {
                    return null;
                }

                const siblings = this.kids.get(node.parent) || [];
                const target = siblings.indexOf(id) + delta;

                if (target < 0 || target >= siblings.length) {
                    return null;
                }

                return this.move(id, node.parent, target);
            },

            updateTitle(id, title) {
                const node = this.node(id);

                if (! node) {
                    return Promise.resolve(null);
                }

                const previous = node.title;

                return this.call('updatePosition', [id, { title }], {
                    undo: () => ({ type: 'update', id, attributes: { title: previous }, label: 'renombrar «' + previous + '»' }),
                });
            },

            updateField(field, value, immediate = false, targetId = null) {
                const id = targetId ?? this.selectedId;
                const node = this.node(id);

                if (! node) {
                    return;
                }

                const key = 'field:' + id + ':' + field;
                clearTimeout(this.timers[key]);

                const run = () => {
                    const current = field === 'rrhh_cargo_id' ? node.cargo_id : (field === 'rrhh_departamento_id' ? node.depto_id : node[field]);

                    if (String(current ?? '') === String(value ?? '')) {
                        return;
                    }

                    const attributes = {};
                    attributes[field] = value === '' ? null : value;

                    this.call('updatePosition', [id, attributes], {
                        undo: () => {
                            const previous = {};
                            previous[field] = current ?? null;

                            return { type: 'update', id, attributes: previous, label: 'editar «' + node.title + '»' };
                        },
                    });
                };

                if (immediate) {
                    run();
                } else {
                    this.timers[key] = setTimeout(run, 800);
                }
            },

            /**
             * «+» de una tarjeta: crea el puesto y abre el nombre para escribirlo.
             */
            addQuick(parentId, kind = 'LINEA') {
                const parent = this.node(parentId);

                if (! parent || this.readOnly) {
                    return;
                }

                if (kind === 'LINEA' && parent.kind === 'STAFF') {
                    this.toast('Un puesto staff no tiene subordinados.', 'warning');

                    return;
                }

                const title = kind === 'STAFF' ? 'Asesoría' : NEW_TITLE;

                this.call('addPosition', [parentId, title, kind, null, null], {
                    undo: (result) => ({ type: 'remove', id: result.position_id, label: 'agregar un puesto' }),
                }).then((result) => {
                    if (result?.position_id) {
                        this.collapsed.delete(parentId);
                        this.relayout();
                        this.select(result.position_id, false);

                        if (this.layout === 'doc') {
                            this.startDocEdit(result.position_id, '');
                        } else {
                            this.startRename(result.position_id, true);
                        }
                    }
                });
            },

            addRoot(title) {
                title = (title || '').trim();

                if (title.length < 2) {
                    this.toast('Escribe el nombre del puesto principal.', 'warning');

                    return;
                }

                this.call('addPosition', [null, title, 'LINEA', null, null]).then((result) => {
                    if (result?.position_id) {
                        this.$nextTick(() => {
                            this.measureViewport();
                            this.relayout();
                            this.fitView();
                            this.select(result.position_id, false);
                        });
                    }
                });
            },

            startRename(id, fresh = false) {
                const node = this.node(id);

                if (! node || this.readOnly) {
                    return;
                }

                if (this.layout === 'doc') {
                    this.startDocEdit(id);

                    return;
                }

                this.ensureVisible(id);
                this.renaming = id;
                this.renameValue = fresh || node.title === NEW_TITLE ? '' : node.title;
                this.$nextTick(() => {
                    this.positionOverlays();
                    const input = this.$refs.renameInput;

                    if (input) {
                        input.focus();
                        input.select();
                    }
                });
            },

            finishRename(commit = true) {
                const id = this.renaming;

                if (id === null) {
                    return;
                }

                this.renaming = null;
                const value = this.renameValue.trim();

                if (commit && value.length >= 2 && value !== this.node(id)?.title) {
                    this.updateTitle(id, value);
                }

                this.$nextTick(() => this.positionOverlays());
            },

            searchPeople() {
                clearTimeout(this.timers.people);
                this.timers.people = setTimeout(async () => {
                    const term = this.personSearch.trim();

                    if (term.length < 2) {
                        this.personResults = [];

                        return;
                    }

                    this.personSearching = true;

                    try {
                        this.personResults = await this.server('searchColaboradores', term);
                    } finally {
                        this.personSearching = false;
                    }
                }, 300);
            },

            assign(person, role, targetId = null) {
                const id = targetId ?? this.selectedId;
                const node = this.node(id);

                if (! node) {
                    return;
                }

                if (node.occupants.length >= node.seats) {
                    this.toast('«' + node.title + '» no tiene plazas libres. Agrega una plaza o retira a alguien.', 'warning');

                    return;
                }

                this.call('assignOccupant', [id, person.id, role], {
                    undo: () => ({ type: 'unassign-person', positionId: id, colaboradorId: person.id, label: 'asignar a ' + person.name }),
                }).then((result) => {
                    if (result) {
                        this.personSearch = '';
                        this.personResults = [];
                        this.popoverSearch = false;
                        const title = this.node(id)?.title || node.title;
                        this.toast(result.dual ? person.name + ' quedó como titular en más de un puesto.' : person.name + ' asignado a «' + title + '».', result.dual ? 'warning' : 'success');
                    }
                });
            },

            unassign(occupant) {
                const positionId = this.selectedId;

                this.call('removeOccupant', [occupant.assignment_id], {
                    undo: () => ({ type: 'assign', positionId, colaboradorId: occupant.id, role: occupant.role, label: 'retirar a ' + occupant.name }),
                });
            },

            fixInactive() {
                this.call('removeInactiveOccupants', []).then((result) => {
                    if (result) {
                        this.toast(result.removed + ' ocupante(s) inactivo(s) retirado(s).', 'success');
                    }
                });
            },

            askRemove(id) {
                const node = this.node(id);

                if (! node || node.parent === null || this.readOnly) {
                    return;
                }

                this.popoverMenu = false;
                this.removeRelease = false;
                this.removeDialog = { id };
                this.focusSoon(() => this.$refs.removeCancel);
            },

            get removeInfo() {
                const node = this.removeDialog ? this.node(this.removeDialog.id) : null;

                if (! node) {
                    return null;
                }

                return {
                    title: node.title,
                    occupants: node.occupants.map((occupant) => occupant.name),
                    children: (this.kids.get(node.id) || []).length,
                    parentTitle: this.parentOf(node.id)?.title || '',
                };
            },

            confirmRemove() {
                const dialog = this.removeDialog;
                const info = this.removeInfo;

                if (! dialog || ! info) {
                    this.removeDialog = null;

                    return;
                }

                const release = this.removeRelease;

                if (info.occupants.length && ! release) {
                    return;
                }

                this.removeDialog = null;
                this.call('removePosition', [dialog.id, release]).then((result) => {
                    if (result) {
                        this.select(null);
                        this.toast('Puesto quitado.', 'success');
                    }
                });
            },

            undo() {
                const step = this.undoStack.pop();

                if (! step) {
                    this.toast('No hay nada que deshacer.', 'info');

                    return;
                }

                const done = () => this.toast('Se deshizo: ' + step.label + '.', 'info');

                if (step.type === 'move') {
                    if (step.parent === null) {
                        return;
                    }

                    this.move(step.id, step.parent, Math.max(0, step.index), true).then((result) => result && done());
                } else if (step.type === 'update') {
                    this.call('updatePosition', [step.id, step.attributes]).then((result) => {
                        if (result) {
                            this.syncEdit();
                            done();
                        }
                    });
                } else if (step.type === 'remove') {
                    this.call('removePosition', [step.id, Boolean(step.release)]).then((result) => {
                        if (result) {
                            if (this.selectedId === step.id) {
                                this.select(null);
                            }

                            done();
                        }
                    });
                } else if (step.type === 'assign') {
                    this.call('assignOccupant', [step.positionId, step.colaboradorId, step.role]).then((result) => result && done());
                } else if (step.type === 'unassign-person') {
                    const occupant = (this.node(step.positionId)?.occupants || []).find((item) => item.id === step.colaboradorId);

                    if (occupant) {
                        this.call('removeOccupant', [occupant.assignment_id]).then((result) => result && done());
                    }
                }
            },

            onGlobalKey(event) {
                if (! this.$root.isConnected || this.$root.offsetParent === null) {
                    return;
                }

                const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target?.tagName);
                const modifier = event.ctrlKey || event.metaKey;

                if (modifier && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    event.stopPropagation();
                    this.cmd.open ? this.closeCommand() : this.openCommand();

                    return;
                }

                if (modifier && event.key.toLowerCase() === 'z' && ! typing && this.mode === 'edit') {
                    event.preventDefault();
                    this.undo();

                    return;
                }

                if (event.key === 'Escape' && ! typing) {
                    if (this.cmd.open || this.reviewOpen || this.helpOpen || this.removeDialog) {
                        this.cmd.open = false;
                        this.reviewOpen = false;
                        this.helpOpen = false;
                        this.removeDialog = null;

                        return;
                    }

                    this.select(null);
                    this.searchOpen = false;

                    return;
                }

                if (typing || modifier) {
                    return;
                }

                if (event.key === '?' && this.mode === 'edit') {
                    this.helpOpen = true;
                } else if (event.key === 'F2' && this.selectedId !== null && this.mode === 'edit') {
                    event.preventDefault();
                    this.startRename(this.selectedId);
                }
            },

            dismissCoach() {
                this.coach = false;
                storage.set('oc-coach-v1', 'visto');
            },

            async heartbeat() {
                try {
                    const result = await this.$wire.heartbeat();

                    if (result && result.locked) {
                        this.readOnly = true;
                        this.readOnlyReason = result.message;
                        clearInterval(this.heartbeatTimer);
                        this.toast(result.message, 'warning', 10000);
                        this.relayout();
                    }
                } catch (error) {
                    // Sin red: el siguiente latido lo reintenta.
                }
            },

            refreshSavedLabel() {
                if (! this.savedAt) {
                    this.savedLabel = '';

                    return;
                }

                const seconds = Math.round((Date.now() - this.savedAt) / 1000);
                this.savedLabel = seconds < 5 ? 'Guardado' : (seconds < 60 ? 'Guardado hace ' + seconds + ' s' : 'Guardado hace ' + Math.round(seconds / 60) + ' min');
            },

            // --------------------------------------------------- revisar y publicar

            get reviewChecks() {
                const codes = new Set(this.issues.filter((issue) => issue.level === 'error').map((issue) => issue.code));

                return [
                    { ok: ! codes.has('empty') && ! codes.has('multiple_roots') && ! codes.has('orphan'), label: 'Un solo puesto principal', detail: this.rootId !== null ? this.node(this.rootId)?.title : 'Agrega el puesto principal' },
                    { ok: ! codes.has('cycle') && ! codes.has('staff_children'), label: 'Sin ciclos ni staff con subordinados', detail: '' },
                    { ok: ! codes.has('over_seats'), label: 'Ningún puesto con más personas que plazas', detail: '' },
                    { ok: ! codes.has('inactive_occupant'), label: 'Todos los ocupantes siguen activos en RRHH', detail: '', fix: codes.has('inactive_occupant') },
                ];
            },

            get reviewErrors() {
                return this.issues.filter((issue) => issue.level === 'error');
            },

            get reviewWarnings() {
                return this.issues.filter((issue) => issue.level === 'warning' && issue.code !== 'unassigned');
            },

            nextVacant() {
                const vacant = this.vacantNodes;

                if (! vacant.length) {
                    return;
                }

                const index = vacant.findIndex((node) => node.id === this.selectedId);
                const next = vacant[(index + 1) % vacant.length];
                this.reviewOpen = false;
                this.select(next.id, true);
            },

            selectByKey(key) {
                const node = (this.data?.nodes || []).find((item) => item.key === key);

                if (node) {
                    this.reviewOpen = false;
                    this.select(node.id);
                }
            },

            get publishLabel() {
                return this.cfg.publishAction === 'publish' ? 'Publicar' : 'Enviar a aprobación';
            },

            publish() {
                if (! this.cfg.publishAction || this.demo || this.errorCount > 0) {
                    return;
                }

                this.reviewOpen = false;
                this.$wire.mountAction(this.cfg.publishAction);
            },

            // ------------------------------------------------------------ comparar

            async toggleCompare() {
                this.compareOn = ! this.compareOn;

                if (! this.compareOn) {
                    this.diff = null;
                    this.compareInfo = null;
                    this.relayout();

                    return;
                }

                await this.loadCompare();
            },

            async loadCompare() {
                const result = await this.server('compareWithBase');

                if (! result.ok) {
                    this.compareOn = false;
                    this.toast(result.message, 'warning');

                    return;
                }

                this.diff = result.diff;
                this.compareInfo = { base: result.base_name };
                this.relayout();
            },

            refreshCompareSoon() {
                if (! this.compareOn) {
                    return;
                }

                clearTimeout(this.timers.compare);
                this.timers.compare = setTimeout(() => this.loadCompare(), 900);
            },

            // -------------------------------------------------------------- avisos

            toast(message, tone = 'info', duration = 5000) {
                if (! message) {
                    return;
                }

                const id = ++this.toastSeq;
                this.toasts.push({ id, message, tone });

                if (this.toasts.length > 4) {
                    this.toasts.shift();
                }

                setTimeout(() => {
                    this.toasts = this.toasts.filter((toast) => toast.id !== id);
                }, duration);
            },

            dismissToast(id) {
                this.toasts = this.toasts.filter((toast) => toast.id !== id);
            },
        };
    };
})();
