<?php

declare(strict_types=1);

namespace App\Support;

final class TechnologyAdvancesPresentationSlides
{
    /**
     * @return list<array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     subtitle: string,
     *     module: string,
     *     icon: string,
     *     color: string,
     *     speaker_note: string|null,
     *     highlights: list<string>,
     *     tags: list<string>,
     *     data: array<string, mixed>
     * }>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'portada',
                'type' => 'cover',
                'title' => 'Avances Tecnológicos',
                'subtitle' => 'El departamento de Tecnología de tuDrGroup construye la plataforma que sostiene el crecimiento de la empresa.',
                'module' => 'Portada',
                'icon' => '◆',
                'color' => '#007AFF',
                'speaker_note' => null,
                'highlights' => [
                    'INTEGRACORP como núcleo operativo',
                    'Ecosistema: Operaciones · Proyectos · Métricas · Portal · Marketing · API',
                    'Infraestructura lista para escalar',
                ],
                'tags' => ['Tecnología', 'INTEGRACORP', '2026'],
                'data' => [
                    'eyebrow' => 'tuDrGroup · Departamento de Tecnología',
                    'badge' => 'Sesión ejecutiva',
                ],
            ],
            [
                'id' => 'operaciones',
                'type' => 'pillars',
                'title' => 'Panel de Operaciones',
                'subtitle' => 'El centro de mando clínico-operativo: afiliados, telemedicina, coordinación e inventario en un solo flujo robusto.',
                'module' => 'Operaciones',
                'icon' => '◎',
                'color' => '#007AFF',
                'speaker_note' => 'La robustez no está solo en pantallas: está en trazabilidad, documentos, notificaciones y procesos auditables.',
                'highlights' => [
                    'Afiliados individuales y corporativos con visión operativa completa',
                    'Telemedicina: pacientes, casos, historial y documentos centralizados',
                    'Coordinación de servicios y citas médicas con control documental',
                    'Inventario Diagnomóvil y zona de descarga para el día a día',
                ],
                'tags' => ['Operaciones', 'Telemedicina', 'Coordinación'],
                'data' => [
                    'pillars' => [
                        [
                            'title' => 'Trazabilidad',
                            'detail' => 'Cada caso, documento y servicio deja rastro. Los analistas operan con contexto, no con conjeturas.',
                        ],
                        [
                            'title' => 'Integración clínica',
                            'detail' => 'Telemedicina, afiliados y coordinación viven en el mismo panel: menos saltos entre sistemas.',
                        ],
                        [
                            'title' => 'Control operativo',
                            'detail' => 'Inventario, descargas y auditorías documentales fortalecen la continuidad del servicio.',
                        ],
                        [
                            'title' => 'Escalabilidad humana',
                            'detail' => 'Permisos, búsqueda global y notificaciones permiten crecer el equipo sin perder orden.',
                        ],
                    ],
                    'robustness' => 'La robustez radica en un panel unificado, con flujos auditables y datos listos para analítica.',
                ],
            ],
            [
                'id' => 'proyectos',
                'type' => 'pillars',
                'title' => 'Panel de Proyectos',
                'subtitle' => 'Gestión ágil interna: epics, sprints, kanban y backlog para convertir ideas en entregas medibles.',
                'module' => 'Proyectos',
                'icon' => '▣',
                'color' => '#34C759',
                'speaker_note' => 'Este panel profesionaliza cómo Tecnología y las áreas priorizan, ejecutan y reportan avance.',
                'highlights' => [
                    'Proyectos, subproyectos, epics, sprints y actividades en un flujo Scrum',
                    'Kanban y backlog visuales para priorizar el trabajo del equipo',
                    'Departamentos y grupos con asignación clara de responsabilidad',
                    'Visibilidad ejecutiva del avance sin depender de hojas sueltas',
                ],
                'tags' => ['Scrum', 'Kanban', 'Gobierno'],
                'data' => [
                    'pillars' => [
                        [
                            'title' => 'Prioridad clara',
                            'detail' => 'El backlog ordena el valor: lo que importa a la empresa se trabaja primero.',
                        ],
                        [
                            'title' => 'Ritmo de entrega',
                            'detail' => 'Sprints y ceremonias crean cadencia predecible: menos improvisación, más resultados.',
                        ],
                        [
                            'title' => 'Responsabilidad',
                            'detail' => 'Departamentos, grupos y colaboradores quedan asignados con transparencia.',
                        ],
                        [
                            'title' => 'Memoria institucional',
                            'detail' => 'El historial de actividades y epics preserva el conocimiento del equipo.',
                        ],
                    ],
                    'company_help' => [
                        'Alinea Tecnología con las prioridades del negocio',
                        'Reduce cuellos de botella por falta de visibilidad',
                        'Acelera la entrega de mejoras a Operaciones, Negocios y Marketing',
                        'Convierte el esfuerzo técnico en avance medible y comunicable',
                    ],
                    'robustness' => 'La robustez radica en estructura Scrum + visibilidad operativa + trazabilidad de asignación.',
                ],
            ],
            [
                'id' => 'metricas',
                'type' => 'preview',
                'title' => 'Panel de Métricas',
                'subtitle' => 'En construcción: el tablero ejecutivo que convertirá la operación en inteligencia de negocio.',
                'module' => 'Métricas',
                'icon' => '◍',
                'color' => '#5856D6',
                'speaker_note' => 'No es un dashboard más: es la capa de decisión sobre afiliaciones, cotizaciones, corretaje y operaciones.',
                'highlights' => [
                    'Módulos previstos: Negocios, Cotizaciones, Afiliaciones, Administración, Operaciones y Proyectos',
                    'Visualizaciones MoM, mapas de actividad y drill-down por estado',
                    'Clientes de INTEGRACORP-API alimentando KPIs en tiempo casi real',
                    'Diseño liquid glass orientado a lectura ejecutiva',
                ],
                'tags' => ['KPI', 'En construcción', 'API'],
                'data' => [
                    'status' => 'En construcción',
                    'modules' => [
                        'Negocios / Corretaje',
                        'Cotizaciones',
                        'Afiliaciones',
                        'Administración',
                        'Operaciones',
                        'Proyectos',
                    ],
                    'promise' => 'Cuando esté completo, la dirección podrá ver el pulso de la empresa sin esperar reportes manuales.',
                ],
            ],
            [
                'id' => 'portal-paciente',
                'type' => 'value',
                'title' => 'Portal del Paciente',
                'subtitle' => 'HERT/PORTAL-PACIENTE: la cara digital del servicio. Robustez para el afiliado y claridad para Operaciones.',
                'module' => 'Portal Paciente',
                'icon' => '◌',
                'color' => '#FF2D55',
                'speaker_note' => 'Cada interacción del paciente que se digitaliza libera tiempo analítico y reduce fricción operativa.',
                'highlights' => [
                    'Experiencia self-service para el afiliado sobre Laravel + Livewire/Flux',
                    'Canal directo de atención y consulta que descongestiona canales internos',
                    'Datos estructurados que alimentan el análisis operativo',
                    'Separación de carga: el portal vive en su propio servidor de producción',
                ],
                'tags' => ['Portal', 'CX', 'Operaciones'],
                'data' => [
                    'for_company' => [
                        'Mejora la percepción de servicio y modernidad de la marca',
                        'Escala atención sin escalar headcount en la misma proporción',
                        'Reduce tickets repetitivos al empoderar al paciente',
                    ],
                    'for_analysts' => [
                        'Menos interrupciones por consultas básicas',
                        'Mayor calidad de datos al origen (el paciente reporta con contexto)',
                        'Tiempo liberado para casos complejos y coordinación real',
                    ],
                    'robustness' => 'Arquitectura dedicada, stack moderno y acoplamiento controlado con el ecosistema INTEGRACORP.',
                ],
            ],
            [
                'id' => 'marketing',
                'type' => 'tests',
                'title' => 'Pruebas del Sistema de Marketing',
                'subtitle' => 'HERT/TDG-MARKETING: calidad automatizada para campañas, afiliados, proveedores y salud de API.',
                'module' => 'Marketing',
                'icon' => '◈',
                'color' => '#FF9500',
                'speaker_note' => 'Las pruebas no son burocracia: son el seguro de que Marketing puede innovar sin romper producción.',
                'highlights' => [
                    'Cobertura de recursos Filament: afiliados, agencias, agentes y proveedores',
                    'Pruebas de notificaciones masivas y de cumpleaños',
                    'Validación de servicios API (health, affiliates, agencies, RRHH)',
                    'Widgets de salud, heatmap y progreso de despacho bajo prueba',
                ],
                'tags' => ['Pest', 'Calidad', 'Marketing'],
                'data' => [
                    'suites' => [
                        ['name' => 'Recursos comerciales', 'items' => ['Agencias', 'Agentes', 'Afiliados ind./corp.', 'Proveedores']],
                        ['name' => 'Campañas', 'items' => ['Notificaciones masivas', 'Cumpleaños', 'Eventos corporativos']],
                        ['name' => 'Integraciones', 'items' => ['Health API', 'Affiliates API', 'Agencies/Agents API', 'RRHH']],
                        ['name' => 'Operación panel', 'items' => ['Dashboard', 'Heatmap', 'Dispatch progress', 'Login']],
                    ],
                    'message' => 'Cada suite protege el canal comercial: si Marketing crece, la plataforma acompaña sin sorpresas.',
                ],
            ],
            [
                'id' => 'api',
                'type' => 'api',
                'title' => 'INTEGRACORP-API',
                'subtitle' => 'La capa que desacopla, acelera y escala: rendimiento para el sistema, músculo para la empresa.',
                'module' => 'API',
                'icon' => '⬡',
                'color' => '#5856D6',
                'speaker_note' => 'Sin API, cada panel reinventaría consultas. Con API, un solo contrato sirve a Métricas, Marketing y más.',
                'highlights' => [
                    'Centraliza lecturas analíticas y contratos de datos entre sistemas',
                    'Reduce carga directa sobre la base operativa de INTEGRACORP',
                    'Habilita paneles (Métricas, Marketing) con clientes tipados y cacheables',
                    'Prepara integraciones futuras sin reescribir el monolito',
                ],
                'tags' => ['Rendimiento', 'Escalabilidad', 'Contratos'],
                'data' => [
                    'improvements' => [
                        [
                            'title' => 'Rendimiento',
                            'detail' => 'Consultas especializadas, menos N+1 en paneles y respuestas optimizadas para dashboards.',
                        ],
                        [
                            'title' => 'Escalabilidad',
                            'detail' => 'Servidor dedicado (SRV-PROD-INTEGRACORP-API) para crecer en tráfico sin saturar la app principal.',
                        ],
                        [
                            'title' => 'Gobierno de datos',
                            'detail' => 'Un contrato estable entre Operaciones, Negocios, Marketing y Métricas.',
                        ],
                        [
                            'title' => 'Velocidad de producto',
                            'detail' => 'Nuevos módulos consumen la API en vez de duplicar lógica SQL en cada panel.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'helpdesk',
                'type' => 'feature',
                'title' => 'Helpdesk activado y mejorado',
                'subtitle' => 'Soporte interno unificado: tickets, grupos de trabajo y visibilidad por panel.',
                'module' => 'Helpdesk',
                'icon' => '◉',
                'color' => '#007AFF',
                'speaker_note' => 'El helpdesk conecta a quien pide ayuda con quien puede resolver — con reglas claras de acceso.',
                'highlights' => [
                    'Activado en paneles clave: Negocios, Operaciones, Administración y Marketing',
                    'Grupos de trabajo y validación de creación de tickets',
                    'Visibilidad y seguimiento de notas no leídas',
                    'Flujos y adjuntos para documentar la resolución',
                ],
                'tags' => ['Soporte', 'Tickets', 'Colaboración'],
                'data' => [
                    'upgrades' => [
                        'Activación transversal del helpdesk en el ecosistema INTEGRACORP',
                        'Mejor gobernanza: quién puede crear tickets y quién los ve',
                        'Grupos de trabajo para enrutar solicitudes al equipo correcto',
                        'Experiencia más clara para el usuario interno y para quien atiende',
                    ],
                ],
            ],
            [
                'id' => 'notificaciones',
                'type' => 'feature',
                'title' => 'Centro de notificaciones · Negocios',
                'subtitle' => 'Alertas en tiempo real dentro del panel de negocios: lo importante llega sin perder el flujo de trabajo.',
                'module' => 'Negocios',
                'icon' => '◎',
                'color' => '#34C759',
                'speaker_note' => 'Polling cada 10s: el equipo comercial se entera al instante, no al final del día.',
                'highlights' => [
                    'Notificaciones de base de datos activadas en el panel de negocios',
                    'Polling en vivo cada 10 segundos',
                    'Menos dependencia del correo para eventos críticos del día a día',
                    'Complementa el helpdesk y los flujos comerciales',
                ],
                'tags' => ['Negocios', 'Realtime', 'UX'],
                'data' => [
                    'upgrades' => [
                        'Centro de notificaciones nativo en el panel de negocios',
                        'Actualización continua sin recargar la página',
                        'Mayor velocidad de respuesta del equipo comercial',
                        'Señal clara de eventos: afiliaciones, tickets y tareas pendientes',
                    ],
                ],
            ],
            [
                'id' => 'infraestructura',
                'type' => 'infra',
                'title' => 'Infraestructura de servidores',
                'subtitle' => 'Producción segregada por carga + desarrollo unificado. Diseño pensado para claridad, resiliencia y escala.',
                'module' => 'Infraestructura',
                'icon' => '⬡',
                'color' => '#007AFF',
                'speaker_note' => 'Cada servicio crítico tiene su propio servidor de producción; el desarrollo concentra ambientes y bases.',
                'highlights' => [
                    'Producción: apps y API en nodos dedicados',
                    'Base de datos de producción aislada',
                    'Desarrollo: un servidor con todos los ambientes y DBs',
                ],
                'tags' => ['Prod', 'Dev', 'Arquitectura'],
                'data' => [
                    'layers' => [
                        [
                            'id' => 'apps',
                            'label' => 'Capa de aplicaciones',
                            'kind' => 'server',
                            'nodes' => [
                                [
                                    'id' => 'SRV-PROD-INTEGRACORP',
                                    'role' => 'Núcleo INTEGRACORP',
                                    'detail' => 'Paneles Filament, operaciones, negocios, proyectos y métricas.',
                                    'kind' => 'server',
                                ],
                                [
                                    'id' => 'SRV-PROD-PORTALPACIENTE',
                                    'role' => 'Portal del paciente',
                                    'detail' => 'Experiencia afiliado; carga segregada del núcleo.',
                                    'kind' => 'server',
                                ],
                                [
                                    'id' => 'SRV-PROD-MARKETING',
                                    'role' => 'Marketing TDG',
                                    'detail' => 'Campañas, eventos y operación comercial digital.',
                                    'kind' => 'server',
                                ],
                            ],
                        ],
                        [
                            'id' => 'api',
                            'label' => 'Capa de integración',
                            'kind' => 'api',
                            'nodes' => [
                                [
                                    'id' => 'SRV-PROD-INTEGRACORP-API',
                                    'role' => 'Capa API',
                                    'detail' => 'Contratos, métricas y consumo entre sistemas.',
                                    'kind' => 'api',
                                ],
                            ],
                        ],
                        [
                            'id' => 'database',
                            'label' => 'Capa de datos',
                            'kind' => 'database',
                            'nodes' => [
                                [
                                    'id' => 'SRV-PROD-BD',
                                    'role' => 'Base de datos',
                                    'detail' => 'Persistencia de producción aislada y reforzada.',
                                    'kind' => 'database',
                                ],
                            ],
                        ],
                    ],
                    'prod' => [
                        [
                            'id' => 'SRV-PROD-INTEGRACORP',
                            'role' => 'Núcleo INTEGRACORP',
                            'detail' => 'Paneles Filament, operaciones, negocios, proyectos y métricas.',
                            'kind' => 'server',
                        ],
                        [
                            'id' => 'SRV-PROD-PORTALPACIENTE',
                            'role' => 'Portal del paciente',
                            'detail' => 'Experiencia afiliado; carga segregada del núcleo.',
                            'kind' => 'server',
                        ],
                        [
                            'id' => 'SRV-PROD-MARKETING',
                            'role' => 'Marketing TDG',
                            'detail' => 'Campañas, eventos y operación comercial digital.',
                            'kind' => 'server',
                        ],
                        [
                            'id' => 'SRV-PROD-INTEGRACORP-API',
                            'role' => 'Capa API',
                            'detail' => 'Contratos, métricas y consumo entre sistemas.',
                            'kind' => 'api',
                        ],
                        [
                            'id' => 'SRV-PROD-BD',
                            'role' => 'Base de datos',
                            'detail' => 'Persistencia de producción aislada y reforzada.',
                            'kind' => 'database',
                        ],
                    ],
                    'dev' => [
                        'id' => 'SRV-DES-INTEGRACORP',
                        'role' => 'Desarrollo unificado',
                        'detail' => 'Contiene todos los ambientes y bases de datos de desarrollo.',
                        'kind' => 'server',
                    ],
                ],
            ],
            [
                'id' => 'futuro',
                'type' => 'future',
                'title' => 'Un futuro muy cercano',
                'subtitle' => 'La siguiente ola de innovación: comunicación, comunidad e inteligencia operativa para toda tuDrGroup.',
                'module' => 'Roadmap',
                'icon' => '✦',
                'color' => '#AF52DE',
                'speaker_note' => 'Esto no es ciencia ficción: son iniciativas ya alineadas con la plataforma INTEGRACORP y automatización con N8N + IA.',
                'highlights' => [
                    'Mensajería Instantánea TuDrGroup',
                    'Red Social TuDrGroup',
                    'Seguimiento y auto-responder con IA + N8N',
                    'Automatización de procesos internos con O&M, IA y N8N',
                ],
                'tags' => ['IA', 'N8N', 'Comunidad'],
                'data' => [
                    'items' => [
                        [
                            'title' => 'Mensajería Instantánea TuDrGroup',
                            'detail' => 'Canal propio de comunicación en tiempo real entre equipos, agencias y áreas internas — con trazabilidad y seguridad corporativa.',
                            'tag' => 'Comunicación',
                        ],
                        [
                            'title' => 'Red Social TuDrGroup',
                            'detail' => 'Espacio colaborativo para cultura, reconocimiento, anuncios y conexión entre colaboradores de toda la organización.',
                            'tag' => 'Comunidad',
                        ],
                        [
                            'title' => 'Seguimiento y auto-responder con IA + N8N',
                            'detail' => 'Respuestas asistidas, seguimiento automático de conversaciones y enrutamiento inteligente para acelerar la atención sin perder el toque humano.',
                            'tag' => 'IA + N8N',
                        ],
                        [
                            'title' => 'Automatización de procesos internos',
                            'detail' => 'Procesos basados en normas de Organización y Métodos, aplicados con IA y flujos de trabajo en N8N: menos fricción manual, más cumplimiento y velocidad.',
                            'tag' => 'O&M + IA',
                        ],
                    ],
                    'promise' => 'INTEGRACORP deja de ser solo operación: se convierte en la plataforma donde la empresa comunica, colabora y se automatiza.',
                ],
            ],
            [
                'id' => 'cierre',
                'type' => 'closing',
                'title' => 'De una necesidad, a una plataforma',
                'subtitle' => 'INTEGRACORP nació para resolver lo urgente. Hoy se convierte en la plataforma tecnológica y de desarrollo de tuDrGroup.',
                'module' => 'Cierre',
                'icon' => '◆',
                'color' => '#007AFF',
                'speaker_note' => null,
                'highlights' => [
                    'Equipo de desarrollo listo para enfrentar cualquier desafío',
                    'Ecosistema multi-servidor, multi-producto, una sola visión',
                    'La confianza se construye entregando — sprint tras sprint',
                ],
                'tags' => ['Visión', 'Fe', 'Futuro'],
                'data' => [
                    'quote' => 'Todo en la vida comienza con un voto de FE, la confianza viene después',
                    'attribution' => 'Departamento de Tecnología · tuDrGroup',
                    'tagline' => 'INTEGRACORP: de necesidad operativa a plataforma de desarrollo.',
                ],
            ],
        ];
    }
}
