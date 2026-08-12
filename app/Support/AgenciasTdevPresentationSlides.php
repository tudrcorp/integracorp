<?php

declare(strict_types=1);

namespace App\Support;

final class AgenciasTdevPresentationSlides
{
    /**
     * Presentación detallada del desarrollo de AGENCIAS TDEV.
     *
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
                'title' => 'Desarrollo de Agencias TDEV',
                'subtitle' => 'Cómo Tecnología construyó la red comercial digital de seguros de viaje: estructura, landings, registros públicos y operación en INTEGRACORP.',
                'module' => 'Portada',
                'icon' => '◆',
                'color' => '#FCA311',
                'speaker_note' => null,
                'highlights' => [
                    'Red comercial digital con niveles 2 y 3',
                    'Landings y formularios públicos con token',
                    'Operación centralizada en INTEGRACORP',
                ],
                'tags' => ['TDEV', 'Agencias', 'INTEGRACORP', '2026'],
                'data' => [
                    'eyebrow' => 'INTEGRACORP · tuDrGroup · Departamento de Sistemas',
                    'badge' => 'Capacitación técnica · Negocios',
                ],
            ],
            [
                'id' => 'proposito',
                'type' => 'pillars',
                'title' => '¿Qué problema resuelve TDEV?',
                'subtitle' => 'Antes: expansión comercial dispersa. Ahora: cada agencia tiene identidad digital, canales de registro y trazabilidad hacia el equipo de Negocios.',
                'module' => 'Contexto',
                'icon' => '◎',
                'color' => '#007AFF',
                'speaker_note' => 'TDEV no es solo un CRUD: es el puente entre la red comercial externa y la operación interna de INTEGRACORP.',
                'highlights' => [
                    'Escalar la red sin perder control de quién invita a quién',
                    'Dar a cada agencia principal su landing y formularios propios',
                    'Notificar a analistas cuando llega un registro nuevo',
                    'Mantener jerarquía clara: principal → asociadas → agentes',
                ],
                'tags' => ['Negocios', 'Escalabilidad', 'Trazabilidad'],
                'data' => [
                    'pillars' => [
                        [
                            'title' => 'Crecimiento ordenado',
                            'detail' => 'La red puede crecer por invitaciones controladas, sin depender de Excel ni formularios sueltos.',
                        ],
                        [
                            'title' => 'Identidad por agencia',
                            'detail' => 'Logo, slogans y URLs propias: cada agencia nivel 2 se presenta como marca comercial digital.',
                        ],
                        [
                            'title' => 'Captura sin fricción',
                            'detail' => 'Agencias y agentes se registran desde el celular o el navegador, con validación y datos completos.',
                        ],
                        [
                            'title' => 'Visibilidad interna',
                            'detail' => 'Negocios ve altas nuevas, relaciones padre/hija y agentes asociados desde el panel AGENCIAS TDEV.',
                        ],
                    ],
                    'robustness' => 'La robustez está en tokens únicos, jerarquía de niveles y notificaciones auditables al equipo.',
                ],
            ],
            [
                'id' => 'mapa',
                'type' => 'hierarchy',
                'title' => 'Mapa de la red TDEV',
                'subtitle' => 'Toca cada nodo para leer su rol. La jerarquía es la base de URLs, permisos y reportes.',
                'module' => 'Arquitectura',
                'icon' => '▣',
                'color' => '#5856D6',
                'speaker_note' => 'Nivel 2 es la agencia principal. Nivel 3 cuelga de una nivel 2. Los agentes pueden ser freelance de nivel 2 o pertenecer a una nivel 3.',
                'highlights' => [
                    'Nivel 2 = agencia principal (landing + tokens)',
                    'Nivel 3 = agencia asociada a una principal',
                    'Agente freelance o agente de agencia nivel 3',
                ],
                'tags' => ['Nivel 2', 'Nivel 3', 'Agentes'],
                'data' => [
                    'nodes' => [
                        [
                            'id' => 'n2',
                            'label' => 'Agencia nivel 2',
                            'role' => 'Principal',
                            'detail' => 'Tiene landing pública, token de agentes y token para registrar agencias nivel 3. Es el ancla de la red.',
                        ],
                        [
                            'id' => 'n3',
                            'label' => 'Agencia nivel 3',
                            'role' => 'Asociada',
                            'detail' => 'Se registra bajo una nivel 2. Hereda la relación comercial con la principal y opera como nodo hijo.',
                        ],
                        [
                            'id' => 'af',
                            'label' => 'Agente freelance',
                            'role' => 'Nivel 2',
                            'detail' => 'Se vincula directamente a la agencia principal mediante el token de registro de agentes.',
                        ],
                        [
                            'id' => 'a3',
                            'label' => 'Agente de agencia',
                            'role' => 'Nivel 3',
                            'detail' => 'Pertenece a una agencia asociada (nivel 3) y queda trazado bajo esa rama comercial.',
                        ],
                    ],
                    'reading' => 'Lea de arriba hacia abajo: principal → asociadas → agentes. Cada flecha es una relación persistida en base de datos.',
                ],
            ],
            [
                'id' => 'nivel-2',
                'type' => 'feature',
                'title' => 'Agencia nivel 2 (principal)',
                'subtitle' => 'Es el producto digital de la red: datos comerciales, branding, landing y dos puertas de registro.',
                'module' => 'Nivel 2',
                'icon' => '◈',
                'color' => '#FCA311',
                'speaker_note' => 'Al crear una nivel 2 en INTEGRACORP se generan tokens UUID y slogans por defecto de seguros de viaje.',
                'highlights' => [
                    'Datos de agencia, representante, ubicación y contacto',
                    'Logo y slogans de landing personalizables',
                    'Token de agentes y token de agencias nivel 3',
                    'URL pública de landing con marca propia',
                ],
                'tags' => ['Principal', 'Landing', 'Tokens'],
                'data' => [
                    'upgrades' => [
                        [
                            'title' => 'Alta en panel Negocios',
                            'detail' => 'Se crea desde Estructura comercial → AGENCIAS TDEV con validación de identificación, contacto y ubicación.',
                        ],
                        [
                            'title' => 'Tokens automáticos',
                            'detail' => 'registration_token (agentes) y agency_registration_token (nivel 3) se generan al crear el registro.',
                        ],
                        [
                            'title' => 'Branding listo',
                            'detail' => 'Slogans por defecto: “Seguros de viaje con respaldo y confianza” y “Protección integral para tu red comercial”.',
                        ],
                        [
                            'title' => 'Landing pública',
                            'detail' => 'Ruta /tdev/web/{token}: vitrina de la agencia con logo, mensajes y accesos a registro.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'nivel-3',
                'type' => 'feature',
                'title' => 'Agencia nivel 3 (asociada)',
                'subtitle' => 'Extiende la red bajo una principal. El registro público captura la ficha completa y notifica a Negocios.',
                'module' => 'Nivel 3',
                'icon' => '◇',
                'color' => '#34C759',
                'speaker_note' => 'La URL /tdev/agencia/{token} usa el agency_registration_token de la nivel 2.',
                'highlights' => [
                    'Siempre tiene parent_id hacia la nivel 2',
                    'Formulario público con datos fiscales y de representante',
                    'Queda visible en la ficha de la principal',
                    'Dispara notificación de “Agencia nivel 3”',
                ],
                'tags' => ['Asociada', 'Registro', 'Notificación'],
                'data' => [
                    'upgrades' => [
                        [
                            'title' => 'Relación padre/hija',
                            'detail' => 'Sin principal no hay nivel 3. Toda asociada queda anclada a una red concreta.',
                        ],
                        [
                            'title' => 'Formulario guiado',
                            'detail' => 'Nombre, identificación, email, teléfonos, ubicación y representante: datos listos para operación.',
                        ],
                        [
                            'title' => 'Operación interna',
                            'detail' => 'Negocios revisa, edita y da seguimiento desde el resource AGENCIAS TDEV en Filament.',
                        ],
                        [
                            'title' => 'Alertas al equipo',
                            'detail' => 'WhatsApp/email a analistas con resumen del alta y enlace al panel.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'agentes',
                'type' => 'value',
                'title' => 'Agentes TDEV',
                'subtitle' => 'Dos caminos claros: freelance de una principal, o agente de una agencia asociada.',
                'module' => 'Agentes',
                'icon' => '◉',
                'color' => '#AF52DE',
                'speaker_note' => 'La ruta /tdev/{token} usa el registration_token. El contexto del token determina si el agente queda freelance o bajo nivel 3.',
                'highlights' => [
                    'Registro público responsive',
                    'Vinculación a agencia según token',
                    'Notificación de nuevo agente al equipo',
                ],
                'tags' => ['Freelance', 'Agente', 'Token'],
                'data' => [
                    'for_company' => [
                        'Capta fuerza comercial sin intermediarios manuales',
                        'Cada agente queda asociado a una rama concreta de la red',
                        'Reduce errores de “quién lo trajo”',
                        'Habilita seguimiento comercial desde el día uno',
                    ],
                    'for_analysts' => [
                        'Ver agentes por agencia en el panel',
                        'Distinguir freelance vs agente de nivel 3',
                        'Recibir alerta inmediata del registro',
                        'Completar o corregir datos si hace falta',
                    ],
                    'robustness' => 'La robustez está en el token + modelo TdevAgent + notificación tipificada.',
                ],
            ],
            [
                'id' => 'urls',
                'type' => 'urls',
                'title' => 'URLs públicas (memorízalas)',
                'subtitle' => 'Tres puertas. Todas protegidas por token UUID. Toca cada tarjeta para ver el uso exacto.',
                'module' => 'Canales',
                'icon' => '↗',
                'color' => '#007AFF',
                'speaker_note' => 'Nunca compartas tokens por canales no confiables. Si se compromete un token, regenerarlo desde el panel.',
                'highlights' => [
                    '/tdev/web/{token} → landing nivel 2',
                    '/tdev/agencia/{token} → registro nivel 3',
                    '/tdev/{token} → registro de agente',
                ],
                'tags' => ['Rutas', 'Público', 'Seguridad'],
                'data' => [
                    'routes' => [
                        [
                            'path' => '/tdev/web/{token}',
                            'name' => 'Landing de agencia',
                            'who' => 'Prospectos y red de la nivel 2',
                            'detail' => 'Muestra marca, slogans y accesos. Es la vitrina comercial digital de la principal.',
                        ],
                        [
                            'path' => '/tdev/agencia/{token}',
                            'name' => 'Registro agencia nivel 3',
                            'who' => 'Nuevas agencias asociadas',
                            'detail' => 'Formulario Livewire de alta. Usa agency_registration_token de la nivel 2.',
                        ],
                        [
                            'path' => '/tdev/{token}',
                            'name' => 'Registro de agente',
                            'who' => 'Agentes freelance o de agencia',
                            'detail' => 'Formulario de agente. Usa registration_token según el contexto de invitación.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'flujo',
                'type' => 'lifecycle',
                'title' => 'Flujo de un alta real',
                'subtitle' => 'Sigue los pasos en orden. Cada etapa deja rastro en sistema y en el equipo de Negocios.',
                'module' => 'Proceso',
                'icon' => '⟳',
                'color' => '#FF9500',
                'speaker_note' => 'Puedes dramatizar el flujo: crear nivel 2 → compartir link → llega nivel 3 → llega agente → analista revisa.',
                'highlights' => [
                    'Creación interna de la principal',
                    'Compartir links de landing/registro',
                    'Captura pública + notificación',
                    'Revisión en panel INTEGRACORP',
                ],
                'tags' => ['Flujo', 'Operación', 'E2E'],
                'data' => [
                    'steps' => [
                        [
                            'title' => '1. Alta nivel 2',
                            'detail' => 'Negocios crea la agencia principal en AGENCIAS TDEV. El sistema genera tokens y slogans.',
                        ],
                        [
                            'title' => '2. Publicación',
                            'detail' => 'Se comparte la landing /tdev/web/{token} y, según necesidad, los links de registro.',
                        ],
                        [
                            'title' => '3. Autoregistro',
                            'detail' => 'Una agencia nivel 3 o un agente completa el formulario público con sus datos.',
                        ],
                        [
                            'title' => '4. Alerta y revisión',
                            'detail' => 'El equipo recibe notificación y valida/ajusta el registro en INTEGRACORP.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'panel',
                'type' => 'pillars',
                'title' => 'Panel INTEGRACORP: AGENCIAS TDEV',
                'subtitle' => 'En Negocios → Estructura comercial vive el centro de mando: crear, ver, editar y medir la red.',
                'module' => 'Panel',
                'icon' => '▦',
                'color' => '#14213D',
                'speaker_note' => 'El resource Filament incluye listado, ficha, edición, estadísticas y branding visual TDEV.',
                'highlights' => [
                    'Navegación: AGENCIAS TDEV',
                    'CRUD completo de agencias',
                    'Relaciones padres, hijas y agentes',
                    'Widgets de estadísticas',
                ],
                'tags' => ['Filament', 'Negocios', 'CRUD'],
                'data' => [
                    'pillars' => [
                        [
                            'title' => 'Listado operable',
                            'detail' => 'Buscar, filtrar y abrir fichas de nivel 2 y 3 sin salir del panel de Negocios.',
                        ],
                        [
                            'title' => 'Ficha completa',
                            'detail' => 'Infolist con datos comerciales, ubicación, branding y vínculos de la red.',
                        ],
                        [
                            'title' => 'Edición controlada',
                            'detail' => 'Actualizar logo, slogans, contactos y datos del representante con auditoría de usuario.',
                        ],
                        [
                            'title' => 'Métricas de red',
                            'detail' => 'Widgets de overview para leer el tamaño y la dinámica de la estructura comercial.',
                        ],
                    ],
                    'company_help' => [
                        'Un solo lugar para operar la red TDEV',
                        'Menos dependencia de hojas de cálculo',
                        'Visibilidad inmediata de altas públicas',
                        'Base lista para reportes y seguimiento comercial',
                    ],
                    'robustness' => 'La robustez del panel está en el modelo Eloquent + resource Filament + notificaciones tipificadas.',
                ],
            ],
            [
                'id' => 'notificaciones',
                'type' => 'feature',
                'title' => 'Notificaciones al equipo',
                'subtitle' => 'Cada registro relevante avisa a analistas con contexto suficiente para actuar sin adivinar.',
                'module' => 'Alertas',
                'icon' => '🔔',
                'color' => '#FF3B30',
                'speaker_note' => 'Los mensajes distinguen: agencia nivel 3, agente de nivel 3 y agente freelance de nivel 2.',
                'highlights' => [
                    'Tipos de mensaje claros',
                    'WhatsApp y email con payload estructurado',
                    'Referencia a la agencia principal',
                    'Indicación de dónde revisar en INTEGRACORP',
                ],
                'tags' => ['WhatsApp', 'Email', 'Analistas'],
                'data' => [
                    'upgrades' => [
                        [
                            'title' => 'Agencia nivel 3',
                            'detail' => 'Alerta con nombre, identificación y agencia principal que originó la invitación.',
                        ],
                        [
                            'title' => 'Agente freelance',
                            'detail' => 'Indica que el agente se vinculó directamente a una nivel 2.',
                        ],
                        [
                            'title' => 'Agente de nivel 3',
                            'detail' => 'Marca la rama asociada: principal → nivel 3 → agente.',
                        ],
                        [
                            'title' => 'Acción sugerida',
                            'detail' => 'El mensaje orienta a revisar en Estructura comercial → AGENCIAS TDEV.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'lectura',
                'type' => 'readable',
                'title' => 'Guía rápida de lectura (100% clara)',
                'subtitle' => 'Si solo recuerdas una diapositiva, que sea esta. Sirve para onboarding de cualquier colaborador.',
                'module' => 'Resumen',
                'icon' => '✓',
                'color' => '#34C759',
                'speaker_note' => 'Úsala como handout verbal: quién es quién, qué URL usar y dónde se opera.',
                'highlights' => [
                    'Nivel 2 = dueña de la red',
                    'Nivel 3 = asociada bajo una 2',
                    'Tres URLs públicas con token',
                    'Todo se opera en AGENCIAS TDEV',
                ],
                'tags' => ['Onboarding', 'Checklist'],
                'data' => [
                    'cards' => [
                        [
                            'title' => 'Para Negocios',
                            'points' => [
                                'Crea y mantiene agencias nivel 2',
                                'Comparte solo los links correctos',
                                'Revisa altas notificadas el mismo día',
                            ],
                        ],
                        [
                            'title' => 'Para Sistemas',
                            'points' => [
                                'Tokens UUID = acceso a formularios',
                                'Modelos: TdevAgency + TdevAgent',
                                'Rutas Livewire públicas bajo /tdev/*',
                            ],
                        ],
                        [
                            'title' => 'Para la red comercial',
                            'points' => [
                                'Landing = cara digital de la principal',
                                'Registro nivel 3 = crecer la red',
                                'Registro agente = sumar fuerza de venta',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'beneficios',
                'type' => 'benefits',
                'title' => 'Beneficios del desarrollo',
                'subtitle' => 'Toca cada beneficio. Es el “por qué” que conecta Tecnología con el resultado de negocio.',
                'module' => 'Valor',
                'icon' => '★',
                'color' => '#FCA311',
                'speaker_note' => 'Cierra el arco: de problema de expansión → a plataforma operable.',
                'highlights' => [
                    'Escalamiento controlado',
                    'Menos trabajo manual',
                    'Marca comercial por agencia',
                    'Trazabilidad punta a punta',
                ],
                'tags' => ['ROI', 'Operación', 'Marca'],
                'data' => [
                    'benefits' => [
                        [
                            'title' => 'Escalamiento con control',
                            'detail' => 'La red crece por invitaciones tokenizadas, no por listas opacas.',
                        ],
                        [
                            'title' => 'Menos carga operativa',
                            'detail' => 'El autoregistro reduce captura manual y errores de digitación.',
                        ],
                        [
                            'title' => 'Marca por agencia',
                            'detail' => 'Landing y branding fortalecen la presencia comercial de cada principal.',
                        ],
                        [
                            'title' => 'Trazabilidad real',
                            'detail' => 'Sabemos de qué red viene cada agencia y cada agente.',
                        ],
                        [
                            'title' => 'Equipo informado',
                            'detail' => 'Las alertas acortan el tiempo entre el alta y la gestión comercial.',
                        ],
                        [
                            'title' => 'Base para el futuro',
                            'detail' => 'Con la estructura lista, se pueden sumar reportes, comisiones y campañas.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'cierre',
                'type' => 'closing',
                'title' => 'Agencias TDEV ya es plataforma',
                'subtitle' => 'No es un módulo aislado: es la columna digital de la red comercial de seguros de viaje.',
                'module' => 'Cierre',
                'icon' => '◆',
                'color' => '#007AFF',
                'speaker_note' => null,
                'highlights' => [
                    'Estructura nivel 2 / nivel 3 / agentes',
                    'Canales públicos con token',
                    'Operación y alertas en INTEGRACORP',
                ],
                'tags' => ['Cierre', 'TDEV'],
                'data' => [
                    'quote' => 'Cuando la red comercial tiene identidad digital, tokens claros y un panel operable, Tecnología deja de ser soporte y pasa a ser ventaja competitiva.',
                    'attribution' => 'Departamento de Sistemas · INTEGRACORP × tuDrGroup',
                    'tagline' => 'Agencias TDEV — de la invitación al seguimiento, en un solo flujo.',
                ],
            ],
            [
                'id' => 'preguntas',
                'type' => 'qa',
                'title' => 'Preguntas',
                'subtitle' => 'Abrimos espacio para dudas de Negocios, Sistemas y operación comercial.',
                'module' => 'Q&A',
                'icon' => '?',
                'color' => '#FCA311',
                'speaker_note' => null,
                'highlights' => [
                    'Dudas de proceso',
                    'Dudas técnicas de tokens/URLs',
                    'Próximas mejoras deseadas',
                ],
                'tags' => ['Q&A'],
                'data' => [
                    'contact' => [
                        'name' => 'Departamento de Sistemas',
                        'email' => '',
                        'linkedin' => '',
                        'org' => 'INTEGRACORP · tuDrGroup',
                    ],
                ],
            ],
        ];
    }
}
