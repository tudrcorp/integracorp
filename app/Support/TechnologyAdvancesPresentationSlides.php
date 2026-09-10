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
                    'Operaciones más robusta: cupos clínicos, coordinación e inventario',
                    'Canal comercial: PWA, portal del paciente y generador de planes',
                    'Marketing, API e intra.tudrgroup.com como ecosistema único',
                ],
                'tags' => ['Tecnología', 'INTEGRACORP', '2026'],
                'data' => [
                    'eyebrow' => 'tuDrGroup · Departamento de Tecnología',
                    'badge' => 'Sesión ejecutiva',
                    'tracks' => [
                        ['label' => 'Operaciones', 'hint' => 'Cupos, coordinación, inventario'],
                        ['label' => 'Planes', 'hint' => 'Carga desde el catálogo'],
                        ['label' => 'PWA', 'hint' => 'Cotizar y afiliar en el celular'],
                        ['label' => 'Portal', 'hint' => 'Self-service del paciente'],
                        ['label' => 'Marketing', 'hint' => 'Campañas con calidad'],
                        ['label' => 'Intra', 'hint' => 'intra.tudrgroup.com'],
                    ],
                ],
            ],
            [
                'id' => 'operaciones',
                'type' => 'pillars',
                'title' => 'Avances del Panel de Operaciones',
                'subtitle' => 'El centro de mando clínico-operativo ya no es un listado: ahora el analista ve cupos, beneficios, coordinación e inventario en el mismo flujo.',
                'module' => 'Operaciones',
                'icon' => '◎',
                'color' => '#007AFF',
                'speaker_note' => 'La robustez no está solo en pantallas: está en cupos que se consumen y se revierten, documentos, notificaciones y procesos auditables.',
                'highlights' => [
                    'Cupos clínicos visibles en la ficha, con tope antes de agotar el beneficio',
                    'Tarjeta de beneficios del plan del afiliado, sin saltar a Negocios',
                    'Coordinación de servicios, órdenes de servicio y chat de seguimiento',
                    'Inventario Diagnomóvil con alerta de stock bajo y zona de descarga',
                ],
                'tags' => ['Operaciones', 'Cupos', 'Coordinación'],
                'data' => [
                    'pillars' => [
                        [
                            'title' => 'Cupos clínicos',
                            'detail' => 'Cada beneficio tiene un conteo real (por año de afiliación, contrato o casos). El médico lo ve en la consulta; Operaciones lo lee en la ficha. Si se agota, hay OTP de autorización — no un bypass opaco.',
                        ],
                        [
                            'title' => 'Coordinación y OS',
                            'detail' => 'Órdenes de servicio, citas, documentos de clínica y chat de seguimiento en un solo panel. Las OS caducan solas a los 10 días: menos colas fantasma.',
                        ],
                        [
                            'title' => 'Inventario Diagnomóvil',
                            'detail' => 'Entradas, salidas, stock bajo y deducción automática cuando Telemedicina receta. El material deja de vivir en una hoja aparte.',
                        ],
                        [
                            'title' => 'Trazabilidad operativa',
                            'detail' => 'Afiliados individuales y corporativos, zona de descarga y auditorías documentales. El analista opera con contexto, no con conjeturas.',
                        ],
                    ],
                    'robustness' => 'La robustez radica en un panel unificado: cupo, beneficio, coordinación e inventario dejan rastro y se revierten juntos.',
                ],
            ],
            [
                'id' => 'generador-planes',
                'type' => 'lifecycle',
                'title' => 'Generador de planes · carga desde el catálogo',
                'subtitle' => 'Negocios ya no arma la matriz a mano. Elige un plan creado, selecciona coberturas y el generador vuelca columnas, beneficios, costos límite y tarifas.',
                'module' => 'Negocios',
                'icon' => '▣',
                'color' => '#34C759',
                'speaker_note' => 'La cotización guarda su propia copia: si mañana se edita el plan del catálogo, las cotizaciones ya emitidas no se descuadran.',
                'highlights' => [
                    'Importa estructura desde un plan ya creado',
                    'El analista elige qué coberturas entran a la cotización',
                    'Población e imágenes se cargan después: son datos del cliente',
                    'La cotización queda independiente del catálogo',
                ],
                'tags' => ['Generador', 'Catálogo', 'Cotización'],
                'data' => [
                    'kicker' => 'De un plan existente a una cotización lista',
                    'steps' => [
                        [
                            'title' => 'Elegir el plan',
                            'detail' => 'En el generador se selecciona un plan ya creado del catálogo. Paquete o coberturas: el sistema reconoce el modo de precio.',
                        ],
                        [
                            'title' => 'Elegir coberturas',
                            'detail' => 'Un mismo plan suele cotizarse con un subconjunto. El analista marca cuáles entran; sin selección no se importa nada.',
                        ],
                        [
                            'title' => 'Cargar estructura',
                            'detail' => 'Un clic vuelca columnas, beneficios, costos límite y tarifas. Lo que ya estaba en el plan no se vuelve a tipear.',
                        ],
                        [
                            'title' => 'Población e imágenes',
                            'detail' => 'Falta lo del cliente: cuántas personas por rango y las imágenes de la propuesta. El PDF sale con la matriz ya coherente.',
                        ],
                    ],
                    'promise' => 'Menos error humano, menos tiempo de armado y una cotización que no se rompe si el catálogo cambia después.',
                ],
            ],
            [
                'id' => 'pwa',
                'type' => 'lifecycle',
                'title' => 'PWA comercial · planes en el bolsillo',
                'subtitle' => 'Una app instalable en iPhone y Android para conocer planes, cotizar y avanzar la afiliación como si fuera un producto, no un formulario interno.',
                'module' => 'PWA',
                'icon' => '◇',
                'color' => '#00C7BE',
                'speaker_note' => 'Ruta /app. Experiencia mobile-first, menú hamburguesa, login con correo o Google. El cliente final cotiza; el agente entra con su cuenta.',
                'highlights' => [
                    'Instalable en iOS y Android, con comportamiento de app nativa',
                    'Planes Inicial, Ideal y Especial como fichas de producto',
                    'Cotización página a página: personas, datos, confirmar, propuesta',
                    'Consulta de cotizaciones, métodos de pago y PDF',
                ],
                'tags' => ['PWA', 'Mobile', 'Cotización'],
                'data' => [
                    'kicker' => 'El canal número 1 para cotizar desde el celular',
                    'steps' => [
                        [
                            'title' => 'Catálogo',
                            'detail' => 'Los planes se venden como productos: portada, promesa y precio desde. No hay jerga de panel interno.',
                        ],
                        [
                            'title' => 'Ficha',
                            'detail' => 'Beneficios, rangos de edad y tarifas en una sola pantalla. El usuario entiende qué compra antes de cotizar.',
                        ],
                        [
                            'title' => 'Cotizar',
                            'detail' => 'Flujo por páginas, con progreso visible y back nativo. Público o agente: cada uno con sus reglas.',
                        ],
                        [
                            'title' => 'Propuesta y pago',
                            'detail' => 'Resultado, propuesta, PDF, frecuencia y métodos de pago. El analista recibe aviso cuando hay una cotización nueva.',
                        ],
                    ],
                    'promise' => 'La PWA acerca el producto al cliente y libera a Negocios de cotizaciones que el usuario ya puede armar solo.',
                ],
            ],
            [
                'id' => 'portal-paciente',
                'type' => 'value',
                'title' => 'Portal del Paciente',
                'subtitle' => 'La cara digital del servicio. Self-service para el afiliado, menos fricción para Operaciones y un servidor propio para no saturar el núcleo.',
                'module' => 'Portal Paciente',
                'icon' => '◌',
                'color' => '#FF2D55',
                'speaker_note' => 'Cada interacción del paciente que se digitaliza libera tiempo analítico y reduce tickets repetitivos.',
                'highlights' => [
                    'Experiencia self-service para el afiliado',
                    'Canal directo de atención que descongestiona canales internos',
                    'Datos estructurados que alimentan el análisis operativo',
                    'Carga segregada: SRV-PROD-PORTALPACIENTE',
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
                'title' => 'Sistema de Marketing',
                'subtitle' => 'Campañas, cumpleaños, eventos y despacho masivo sobre un panel propio, con pruebas automatizadas para innovar sin romper producción.',
                'module' => 'Marketing',
                'icon' => '◈',
                'color' => '#FF9500',
                'speaker_note' => 'Marketing no es un Excel con WhatsApp: es un sistema con carpetas, progreso de despacho, API de salud y servidor dedicado.',
                'highlights' => [
                    'Notificaciones masivas por email, WhatsApp y video, con carpetas y progreso',
                    'Cumpleaños, eventos corporativos y CAPEMIAC',
                    'Visión de afiliados, agencias, agentes y proveedores',
                    'Calidad automatizada: Pest, health API y heatmap del panel',
                ],
                'tags' => ['Campañas', 'WhatsApp', 'Calidad'],
                'data' => [
                    'suites' => [
                        ['name' => 'Campañas', 'items' => ['Masivos email / WhatsApp / video', 'Carpetas de despacho', 'Progreso en vivo', 'Reintento controlado']],
                        ['name' => 'Relación', 'items' => ['Cumpleaños', 'Eventos corporativos', 'CAPEMIAC', 'Listas de contacto']],
                        ['name' => 'Red comercial', 'items' => ['Afiliados ind./corp.', 'Agencias y agentes', 'Proveedores', 'Dashboard']],
                        ['name' => 'Calidad', 'items' => ['Pest de recursos', 'Health API', 'Heatmap', 'Servidor dedicado']],
                    ],
                    'message' => 'Si Marketing crece, la plataforma acompaña: throttle, locks y pruebas evitan que una campaña tumbe el resto del ecosistema.',
                ],
            ],
            [
                'id' => 'intra',
                'type' => 'hub',
                'title' => 'intra.tudrgroup.com',
                'subtitle' => 'El hub interno de Tecnología: un solo lugar para ambientes, documentación y herramientas de desarrollo, publicado en el servidor de desarrollo.',
                'module' => 'Intra',
                'icon' => '⬡',
                'color' => '#5856D6',
                'speaker_note' => 'No es un panel Filament: es la puerta de entrada del equipo. DNS, HTTPS y una página viva en el servidor de desarrollo.',
                'highlights' => [
                    'URL propia de tuDrGroup, con HTTPS',
                    'Acceso unificado a ambientes y recursos de desarrollo',
                    'Menos “¿cuál es el link?” entre el equipo',
                    'Publicado en SRV-DES-INTEGRACORP',
                ],
                'tags' => ['Hub', 'Desarrollo', 'HTTPS'],
                'data' => [
                    'url' => 'https://intra.tudrgroup.com',
                    'status' => 'En línea',
                    'host' => 'SRV-DES-INTEGRACORP',
                    'cards' => [
                        [
                            'title' => 'Una sola puerta',
                            'detail' => 'El equipo entra por un dominio de la marca, no por IPs ni archivos sueltos en el escritorio.',
                        ],
                        [
                            'title' => 'Ambientes a la vista',
                            'detail' => 'Desarrollo concentra ambientes, bases y herramientas. Intra es el mapa para no perderse.',
                        ],
                        [
                            'title' => 'Cultura de plataforma',
                            'detail' => 'Tecnología deja de vivir en chats: hay un lugar oficial donde está lo que el equipo necesita.',
                        ],
                    ],
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
                'speaker_note' => 'Cada servicio crítico tiene su propio servidor de producción; el desarrollo concentra ambientes, bases e intra.tudrgroup.com.',
                'highlights' => [
                    'Producción: apps y API en nodos dedicados',
                    'Base de datos de producción aislada',
                    'Desarrollo: un servidor con todos los ambientes, DBs e intra.tudrgroup.com',
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
                                    'detail' => 'Paneles Filament, operaciones, negocios, proyectos, métricas, PWA y generador de planes.',
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
                            'detail' => 'Paneles Filament, operaciones, negocios, proyectos, métricas, PWA y generador de planes.',
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
                        'detail' => 'Contiene todos los ambientes, bases de datos de desarrollo e intra.tudrgroup.com.',
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
