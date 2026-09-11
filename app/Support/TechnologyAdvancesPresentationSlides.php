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
                        ['label' => 'Hub', 'hint' => 'Panel de Sistemas'],
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
                'id' => 'panel-sistemas',
                'type' => 'devices',
                'title' => 'Panel de Sistemas',
                'subtitle' => 'El hub interno de presentaciones y manuales. El colaborador entra con cédula o teléfono; el equipo ya no pregunta “¿cuál es el link?”.',
                'module' => 'Sistemas',
                'icon' => '◉',
                'color' => '#FCA311',
                'speaker_note' => 'Ruta /dpto-tecnologia-sistemas. En el iPhone se ve el acceso por identidad; en el monitor, el catálogo con la imagen del departamento. Sesión de 10 minutos.',
                'highlights' => [
                    'Acceso solo para colaboradores registrados (cédula o teléfono)',
                    'Presentaciones técnicas y manuales en un mismo catálogo',
                    'URL propia: integracorp.tudrgroup.com/dpto-tecnologia-sistemas',
                    'Cierre automático a los 10 minutos de inactividad',
                ],
                'tags' => ['Hub', 'Acceso', 'Capacitación'],
                'data' => [
                    'device_set' => 'sistemas',
                    'phone_caption' => 'iPhone · Identidad',
                    'monitor_caption' => 'PC · Panel',
                    'hero_image' => 'image/presentaciones-sistemas-bg.png',
                    'mark_image' => 'image/imagotipo.png',
                    'url' => 'https://integracorp.tudrgroup.com/dpto-tecnologia-sistemas',
                    'steps' => [
                        ['title' => 'Identidad'],
                        ['title' => 'Presentaciones'],
                        ['title' => 'Manuales'],
                        ['title' => '10 min idle'],
                    ],
                ],
            ],
            [
                'id' => 'pwa',
                'type' => 'devices',
                'title' => 'PWA comercial · planes en el bolsillo',
                'subtitle' => 'Una app instalable en iPhone y Android para conocer planes, cotizar y avanzar la afiliación como si fuera un producto, no un formulario interno.',
                'module' => 'PWA',
                'icon' => '◇',
                'color' => '#00C7BE',
                'speaker_note' => 'Ruta /app. A la izquierda, la bienvenida. A la derecha, el catálogo de planes. El cliente cotiza; el agente entra con su cuenta.',
                'highlights' => [
                    'Instalable en iOS y Android, con comportamiento de app nativa',
                    'Planes Inicial, Ideal y Especial como fichas de producto',
                    'Cotización página a página: personas, datos, confirmar, propuesta',
                    'Consulta de cotizaciones, métodos de pago y PDF',
                ],
                'tags' => ['PWA', 'Mobile', 'Cotización'],
                'data' => [
                    'device_set' => 'pwa',
                    'kicker' => 'Así se ve en el iPhone: bienvenida y catálogo de planes',
                    'phone_caption' => 'iPhone · Bienvenida',
                    'plans_caption' => 'iPhone · Planes',
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
                    'plans' => [
                        [
                            'title' => 'Plan Inicial',
                            'promise' => 'Orientación médica y beneficios esenciales para empezar a cuidarte.',
                            'cover' => 'image/storefront/plan-inicial.jpg',
                            'cover_webp' => 'image/storefront/plan-inicial.webp',
                            'price' => 'Ver tarifas',
                        ],
                        [
                            'title' => 'Plan Ideal',
                            'promise' => 'Más protección, más red y más tranquilidad en el día a día.',
                            'cover' => 'image/storefront/plan-ideal.jpg',
                            'cover_webp' => 'image/storefront/plan-ideal.webp',
                            'price' => 'Ver tarifas',
                        ],
                        [
                            'title' => 'Plan Especial',
                            'promise' => 'La cobertura más amplia de la línea básica para tu familia.',
                            'cover' => 'image/storefront/plan-especial.jpg',
                            'cover_webp' => 'image/storefront/plan-especial.webp',
                            'price' => 'Ver tarifas',
                        ],
                    ],
                    'promise' => 'La PWA acerca el producto al cliente y libera a Negocios de cotizaciones que el usuario ya puede armar solo.',
                ],
            ],
            [
                'id' => 'portal-paciente',
                'type' => 'devices',
                'title' => 'Portal del Paciente',
                'subtitle' => 'La cara digital del servicio. El afiliado entra con cédula y clave, en el teléfono o en el computador, sin saturar el núcleo operativo.',
                'module' => 'Portal Paciente',
                'icon' => '◌',
                'color' => '#FF2D55',
                'speaker_note' => 'A la izquierda el login en iPhone; a la derecha, el mismo ingreso en monitor. Cada interacción digitalizada libera tiempo analítico y reduce tickets repetitivos.',
                'highlights' => [
                    'Experiencia self-service para el afiliado',
                    'Canal directo de atención que descongestiona canales internos',
                    'Datos estructurados que alimentan el análisis operativo',
                    'Carga segregada: SRV-PROD-PORTALPACIENTE',
                ],
                'tags' => ['Portal', 'CX', 'Operaciones'],
                'data' => [
                    'device_set' => 'portal',
                    'phone_caption' => 'iPhone · Login',
                    'monitor_caption' => 'PC · Login',
                    'login_image' => 'image/storefront/portal-paciente-login.jpg',
                    'login_logo' => 'image/logoNewTDG.png',
                    'steps' => [
                        ['title' => 'Self-service'],
                        ['title' => 'Menos tickets'],
                        ['title' => 'Servidor propio'],
                    ],
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
                'type' => 'devices',
                'title' => 'Sistema de Marketing',
                'subtitle' => 'Landing pública en el computador; el analista entra al panel desde el iPhone o el iPad. Campañas, cumpleaños y despacho masivo sobre un sistema propio.',
                'module' => 'Marketing',
                'icon' => '◈',
                'color' => '#FF9500',
                'speaker_note' => 'A la derecha, la landing de TDG Marketing. A la izquierda, el login del panel en iPhone e iPad. No es un Excel con WhatsApp: hay carpetas, progreso de despacho y servidor dedicado.',
                'highlights' => [
                    'Notificaciones masivas por email, WhatsApp y video, con carpetas y progreso',
                    'Cumpleaños, eventos corporativos y CAPEMIAC',
                    'Visión de afiliados, agencias, agentes y proveedores',
                    'Calidad automatizada: Pest, health API y heatmap del panel',
                ],
                'tags' => ['Campañas', 'WhatsApp', 'Calidad'],
                'data' => [
                    'device_set' => 'marketing',
                    'phone_caption' => 'iPhone · Acceso',
                    'tablet_caption' => 'iPad · Acceso',
                    'monitor_caption' => 'PC · Landing',
                    'login_logo' => 'image/logoNewTDG.png',
                    'casa_image' => 'image/storefront/tdg-casa-bg.jpg',
                    'viajes_image' => 'image/storefront/tdg-viajes-bg.jpg',
                    'steps' => [
                        ['title' => 'Landing'],
                        ['title' => 'Acceso al panel'],
                        ['title' => 'Campañas'],
                        ['title' => 'Servidor propio'],
                    ],
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
                'speaker_note' => 'No es un panel Filament: es el índice de portales. A la izquierda producción, a la derecha desarrollo. DNS, HTTPS y una página viva en SRV-DES-INTEGRACORP.',
                'highlights' => [
                    'URL propia de tuDrGroup, con HTTPS',
                    'Acceso unificado a ambientes, bases y herramientas',
                    'Menos “¿cuál es el link?” entre el equipo',
                    'Publicado en SRV-DES-INTEGRACORP',
                ],
                'tags' => ['Hub', 'Desarrollo', 'HTTPS'],
                'data' => [
                    'url' => 'https://intra.tudrgroup.com',
                    'status' => 'En línea',
                    'host' => 'SRV-DES-INTEGRACORP',
                    'monitor_caption' => 'PC · Índice de portales',
                    'login_logo' => 'image/logoNewTDG.png',
                    'device_set' => 'intra',
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
                    'quote' => 'Lo más difícil de ver es lo bueno.... En tu Doctor Group, lo bueno pesa muchísimo más...',
                    'attribution' => 'Departamento de Tecnología · tuDrGroup',
                    'tagline' => 'INTEGRACORP: de necesidad operativa a plataforma de desarrollo.',
                ],
            ],
        ];
    }
}
