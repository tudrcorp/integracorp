<?php

declare(strict_types=1);

namespace App\Support\Metrics\System;

/**
 * Catálogo de módulos de negocio y capas técnicas con los que se agrupa el
 * trabajo hecho sobre el código. Clasifica una ruta del repositorio
 * (`app/Filament/Operations/Resources/Suppliers/...`) en un módulo
 * («Operaciones y proveedores») y en una capa («Pantallas»).
 *
 * El módulo sale de las palabras de la ruta: gana la primera palabra (de
 * izquierda a derecha) que pertenezca a un módulo. Los paneles que mezclan
 * varios módulos (Negocios, Operaciones, Administración, red comercial) no
 * cuentan como palabra; solo deciden si ninguna otra palabra coincide.
 */
final class SystemModuleCatalog
{
    public const FALLBACK_MODULE = 'plataforma';

    public const FALLBACK_LAYER = 'otros';

    /**
     * Módulos en el orden en que se muestran. `exact` son palabras completas;
     * `prefix`, comienzos de palabra.
     *
     * @var array<string, array{label: string, color: string, exact: list<string>, prefix: list<string>}>
     */
    private const MODULES = [
        'telemedicina' => [
            'label' => 'Telemedicina y cupos clínicos',
            'color' => '#0ea5e9',
            'exact' => ['amd', 'doctor', 'doctors', 'case', 'cases', 'lab', 'imaging', 'informe'],
            'prefix' => ['telemedic', 'clinical', 'patient', 'pacient', 'consultation', 'prescription', 'receta', 'medical', 'medic'],
        ],
        'operaciones' => [
            'label' => 'Operaciones y proveedores',
            'color' => '#2563eb',
            'exact' => ['os', 'order', 'orders', 'retail', 'tpa'],
            'prefix' => ['operation', 'operacion', 'quality', 'inventor', 'supplier', 'proveedor', 'provider', 'coordination', 'coordinac', 'diagnomovil', 'guardia', 'ally', 'allies', 'nurse'],
        ],
        'afiliaciones' => [
            'label' => 'Afiliaciones y renovaciones',
            'color' => '#16a34a',
            'exact' => ['card', 'cards', 'carnet', 'qr', 'family'],
            'prefix' => ['affiliat', 'afiliac', 'afiliad', 'afilliat', 'renovation', 'renovac', 'certificate', 'certificad', 'identity', 'person', 'tarjeta', 'population', 'poblacion', 'padron', 'vaucher', 'voucher', 'ils', 'cuestionario', 'questionnaire'],
        ],
        'cotizaciones' => [
            'label' => 'Cotizaciones',
            'color' => '#9333ea',
            'exact' => ['dress', 'tylor', 'taylor'],
            'prefix' => ['quote', 'cotiz', 'propuesta'],
        ],
        'planes' => [
            'label' => 'Planes, tarifas y upgrades',
            'color' => '#c026d3',
            'exact' => ['plan', 'plans', 'planes', 'fee', 'fees', 'limit', 'limits', 'sublimit', 'sublimits'],
            'prefix' => ['coverage', 'cobertur', 'benefit', 'benefici', 'upgrade', 'capitad', 'capitation', 'pricing'],
        ],
        'administracion' => [
            'label' => 'Administración y cobranza',
            'color' => '#d97706',
            'exact' => ['sale', 'sales', 'bcv', 'paid', 'cxc', 'cxp', 'bank', 'banks'],
            'prefix' => ['collection', 'cobranz', 'commission', 'comision', 'payment', 'pago', 'invoice', 'factur', 'receivable', 'payable', 'venta', 'administration', 'administrac', 'reconciliation', 'recibo', 'aviso', 'membership', 'domicilia', 'cierre', 'financ', 'settlement', 'liquidac', 'billing', 'cuenta', 'banco'],
        ],
        'red' => [
            'label' => 'Red comercial (agentes y agencias)',
            'color' => '#0d9488',
            'exact' => ['agent', 'agents', 'tdev'],
            'prefix' => ['agente', 'agenc', 'subagent', 'prospect', 'travel', 'captac', 'corretaje', 'viajes', 'hierarchy', 'jerarqu', 'commercial', 'referidor'],
        ],
        'aliadas' => [
            'label' => 'Empresas aliadas',
            'color' => '#4f46e5',
            'exact' => ['white', 'aliada', 'aliadas', 'company', 'companies'],
            'prefix' => ['whitecompan', 'associate', 'asociad', 'viveplus', 'vivepluss'],
        ],
        'marketing' => [
            'label' => 'Marketing y comunicaciones',
            'color' => '#db2777',
            'exact' => ['mass', 'capemiac', 'whatsapp', 'storefront', 'download'],
            'prefix' => ['marketing', 'birthday', 'cumplea', 'anniversar', 'aniversar', 'masiv', 'campaign', 'infofree'],
        ],
        'helpdesk' => [
            'label' => 'Helpdesk',
            'color' => '#e11d48',
            'exact' => ['sla', 'csat'],
            'prefix' => ['helpdesk', 'ticket'],
        ],
        'rrhh' => [
            'label' => 'RRHH y organización',
            'color' => '#ea580c',
            'exact' => ['org', 'tdg'],
            'prefix' => ['rrhh', 'payroll', 'nomin', 'colaborador', 'collaborator', 'organigram', 'organizac', 'vacation', 'vacacion', 'loan', 'prestamo', 'deduction', 'deduccion', 'cargo', 'constancia', 'calendar', 'calendario', 'agenda', 'appointment'],
        ],
        'proyectos' => [
            'label' => 'Proyectos (Scrum)',
            'color' => '#65a30d',
            'exact' => ['epic', 'epics'],
            'prefix' => ['project', 'proyect', 'sprint', 'kanban', 'backlog', 'scrum', 'subproject'],
        ],
        'metricas' => [
            'label' => 'Métricas y reportes',
            'color' => '#0891b2',
            'exact' => ['kpi', 'kpis'],
            'prefix' => ['metric', 'report', 'dashboard', 'export', 'indicador'],
        ],
        'usuarios' => [
            'label' => 'Usuarios, permisos y seguridad',
            'color' => '#475569',
            'exact' => ['user', 'users', 'otp', 'auth', 'login', 'logout', 'live', 'work'],
            'prefix' => ['usuario', 'permission', 'permiso', 'audit', 'security', 'session', 'navigation', 'bitacora', 'password', 'presence', 'blacklist', 'theme', 'hub', 'integracorp'],
        ],
        'chat' => [
            'label' => 'Chat público e IA',
            'color' => '#7c3aed',
            'exact' => ['ai', 'guia'],
            'prefix' => ['chat', 'ollama', 'orchestrat'],
        ],
        self::FALLBACK_MODULE => [
            'label' => 'Plataforma base (comunes)',
            'color' => '#94a3b8',
            'exact' => [],
            'prefix' => [],
        ],
    ];

    /**
     * Paneles que mezclan módulos: su nombre en la ruta solo decide al final.
     *
     * @var array<string, string>
     */
    private const MIXED_PANEL_FALLBACK = [
        'Business' => self::FALLBACK_MODULE,
        'Admin' => self::FALLBACK_MODULE,
        'Operations' => 'operaciones',
        'Administration' => 'administracion',
        'Agents' => 'red',
        'Master' => 'red',
        'General' => 'red',
    ];

    /**
     * Capas técnicas en el orden en que se muestran: [etiqueta, descripción, patrón].
     *
     * @var array<string, array{label: string, hint: string, pattern: string}>
     */
    private const LAYERS = [
        'pantallas' => ['label' => 'Pantallas de los paneles', 'hint' => 'Recursos, páginas, formularios, tablas y widgets de Filament.', 'pattern' => '#^app/Filament/#'],
        'reglas' => ['label' => 'Reglas de negocio', 'hint' => 'Lógica reutilizable del dominio (app/Support).', 'pattern' => '#^app/Support/#'],
        'servicios' => ['label' => 'Servicios e integraciones', 'hint' => 'PDF, exportes, WhatsApp, APIs externas (app/Services).', 'pattern' => '#^app/Services/#'],
        'datos' => ['label' => 'Modelos de datos', 'hint' => 'Modelos Eloquent, enums, casts y observers.', 'pattern' => '#^app/(Models|Enums|Casts|Observers)/#'],
        'procesos' => ['label' => 'Procesos en segundo plano', 'hint' => 'Jobs en cola y comandos programados.', 'pattern' => '#^app/(Jobs|Console)/#'],
        'web' => ['label' => 'Rutas, controladores y flujos públicos', 'hint' => 'Controladores, middleware, Livewire público y rutas.', 'pattern' => '#^(app/(Http|Livewire)/|routes/)#'],
        'comunicaciones' => ['label' => 'Correos y notificaciones', 'hint' => 'Mailables y notificaciones.', 'pattern' => '#^app/(Mail|Notifications)/#'],
        'vistas' => ['label' => 'Vistas y documentos PDF', 'hint' => 'Plantillas Blade: pantallas, correos y PDF.', 'pattern' => '#^resources/views/#'],
        'estilos' => ['label' => 'Estilos y frontend', 'hint' => 'CSS, JavaScript y temas de los paneles.', 'pattern' => '#^(resources/(css|js)/|vite\.config|package\.json)#'],
        'base_de_datos' => ['label' => 'Base de datos', 'hint' => 'Migraciones, factories y seeders.', 'pattern' => '#^database/#'],
        'pruebas' => ['label' => 'Pruebas automatizadas', 'hint' => 'Tests de Pest (unitarios y de integración).', 'pattern' => '#^tests/#'],
        'configuracion' => ['label' => 'Configuración', 'hint' => 'Archivos de config, providers y arranque.', 'pattern' => '#^(config/|bootstrap/|app/Providers/|composer\.json)#'],
        self::FALLBACK_LAYER => ['label' => 'Otros', 'hint' => 'Documentación, utilidades y archivos sueltos.', 'pattern' => '#.^#'],
    ];

    /**
     * Piezas del sistema que se cuentan en el inventario: [etiqueta, patrón].
     *
     * @var array<string, array{label: string, icon: string, pattern: string}>
     */
    private const INVENTORY = [
        'resources' => ['label' => 'Recursos (CRUD)', 'icon' => 'heroicon-o-rectangle-stack', 'pattern' => '#^app/Filament/.+Resource\.php$#'],
        'pages' => ['label' => 'Páginas de panel', 'icon' => 'heroicon-o-window', 'pattern' => '#^app/Filament/[^/]+/(Pages|Clusters/.+/Pages)/[^/]+\.php$#'],
        'widgets' => ['label' => 'Widgets y gráficos', 'icon' => 'heroicon-o-chart-pie', 'pattern' => '#^app/Filament/.*Widgets/.+\.php$#'],
        'models' => ['label' => 'Modelos de datos', 'icon' => 'heroicon-o-cube', 'pattern' => '#^app/Models/.+\.php$#'],
        'support' => ['label' => 'Reglas y servicios', 'icon' => 'heroicon-o-puzzle-piece', 'pattern' => '#^app/(Support|Services)/.+\.php$#'],
        'jobs' => ['label' => 'Jobs en cola', 'icon' => 'heroicon-o-queue-list', 'pattern' => '#^app/Jobs/.+\.php$#'],
        'commands' => ['label' => 'Comandos', 'icon' => 'heroicon-o-command-line', 'pattern' => '#^app/Console/Commands/.+\.php$#'],
        'views' => ['label' => 'Vistas Blade', 'icon' => 'heroicon-o-document-text', 'pattern' => '#^resources/views/.+\.blade\.php$#'],
        'migrations' => ['label' => 'Migraciones', 'icon' => 'heroicon-o-circle-stack', 'pattern' => '#^database/migrations/.+\.php$#'],
        'tests' => ['label' => 'Pruebas', 'icon' => 'heroicon-o-beaker', 'pattern' => '#^tests/.+Test\.php$#'],
    ];

    /**
     * Archivos generados o de terceros: no son trabajo escrito a mano.
     */
    private const GENERATED_PATTERN = '#^(vendor/|node_modules/|public/|storage/|bootstrap/cache/|\.phpunit|\.trunk/)|(\.lock|package-lock\.json|\.min\.(js|css)|\.map)$#';

    /** @var array<string, string> */
    private static array $moduleCache = [];

    /**
     * @return array<string, array{label: string, color: string}>
     */
    public static function modules(): array
    {
        return array_map(
            static fn (array $module): array => ['label' => $module['label'], 'color' => $module['color']],
            self::MODULES,
        );
    }

    /**
     * @return array<string, array{label: string, hint: string}>
     */
    public static function layers(): array
    {
        return array_map(
            static fn (array $layer): array => ['label' => $layer['label'], 'hint' => $layer['hint']],
            self::LAYERS,
        );
    }

    /**
     * @return array<string, array{label: string, icon: string}>
     */
    public static function inventoryKinds(): array
    {
        return array_map(
            static fn (array $kind): array => ['label' => $kind['label'], 'icon' => $kind['icon']],
            self::INVENTORY,
        );
    }

    public static function isGenerated(string $path): bool
    {
        return preg_match(self::GENERATED_PATTERN, $path) === 1;
    }

    public static function layerFor(string $path): string
    {
        foreach (self::LAYERS as $key => $layer) {
            if (preg_match($layer['pattern'], $path) === 1) {
                return $key;
            }
        }

        return self::FALLBACK_LAYER;
    }

    /**
     * Piezas del inventario a las que pertenece el archivo (puede ser más de una:
     * un widget dentro de `Pages/` no existe, pero un recurso sí es Filament).
     *
     * @return list<string>
     */
    public static function inventoryKindsFor(string $path): array
    {
        $kinds = [];

        foreach (self::INVENTORY as $key => $kind) {
            if (preg_match($kind['pattern'], $path) === 1) {
                $kinds[] = $key;
            }
        }

        return $kinds;
    }

    public static function moduleFor(string $path): string
    {
        return self::$moduleCache[$path] ??= self::resolveModule($path);
    }

    /**
     * Módulo de una tabla de base de datos (`operation_service_invoices`).
     */
    public static function moduleForTable(string $table): string
    {
        return self::moduleFor('database/'.$table);
    }

    private static function resolveModule(string $path): string
    {
        $panelFallback = null;

        if (preg_match('#^app/Filament/([^/]+)/(.*)$#', $path, $matches) === 1 && isset(self::MIXED_PANEL_FALLBACK[$matches[1]])) {
            $panelFallback = self::MIXED_PANEL_FALLBACK[$matches[1]];
            $path = 'app/Filament/'.$matches[2];
        }

        foreach (self::words($path) as $word) {
            foreach (self::MODULES as $key => $module) {
                if (in_array($word, $module['exact'], true)) {
                    return $key;
                }

                foreach ($module['prefix'] as $prefix) {
                    if (str_starts_with($word, $prefix)) {
                        return $key;
                    }
                }
            }
        }

        return $panelFallback ?? self::FALLBACK_MODULE;
    }

    /**
     * Palabras de una ruta: separa carpetas, guiones, puntos y CamelCase.
     *
     * @return list<string>
     */
    private static function words(string $path): array
    {
        $parts = preg_split('/[^A-Za-z0-9]+|(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', $path) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $part): string => strtolower($part), $parts),
            static fn (string $word): bool => $word !== '' && ! ctype_digit($word),
        ));
    }
}
