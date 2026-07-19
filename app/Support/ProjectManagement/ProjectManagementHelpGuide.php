<?php

declare(strict_types=1);

namespace App\Support\ProjectManagement;

final class ProjectManagementHelpGuide
{
    /**
     * @return list<array{
     *     id: string,
     *     title: string,
     *     eyebrow: string,
     *     summary: string,
     *     blocks: list<array{type: string, title?: string, body?: string, items?: list<string>, steps?: list<array{title: string, body: string}>, cards?: list<array{title: string, body: string, meta?: string}>}>
     * }>
     */
    public static function sections(): array
    {
        return [
            [
                'id' => 'inicio',
                'title' => 'Qué es este módulo',
                'eyebrow' => 'Empezar aquí',
                'summary' => 'Una sola herramienta para planear trabajo, repartirlo al equipo y ver el avance día a día — con o sin Scrum.',
                'blocks' => [
                    [
                        'type' => 'paragraph',
                        'body' => 'El panel de Proyectos sirve para organizar el trabajo interno: desde una mejora de marketing hasta un sprint técnico de Sistemas. No necesitas saber Scrum para empezar; el módulo te guía con pantallas claras.',
                    ],
                    [
                        'type' => 'cards',
                        'cards' => [
                            [
                                'title' => 'Planear',
                                'body' => 'Defines qué hay que hacer (backlog) y en qué orden importa.',
                                'meta' => 'Backlog · Épicas · Sprints',
                            ],
                            [
                                'title' => 'Ejecutar',
                                'body' => 'Mueves el trabajo en el Kanban mientras el equipo avanza.',
                                'meta' => 'Kanban · Actividades',
                            ],
                            [
                                'title' => 'Medir',
                                'body' => 'Ves puntos comprometidos, hechos y el burndown del sprint.',
                                'meta' => 'Sprint · Velocity',
                            ],
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'title' => 'Regla de oro',
                        'body' => 'Una pantalla = un trabajo. Backlog prioriza. Kanban ejecuta. Sprint planifica y mide. No mezcles todo en la misma vista.',
                    ],
                ],
            ],
            [
                'id' => 'menu',
                'title' => 'Mapa del menú',
                'eyebrow' => 'Navegación',
                'summary' => 'Qué hace cada ítem del menú y cuándo usarlo.',
                'blocks' => [
                    [
                        'type' => 'list',
                        'items' => [
                            'Proyectos — el “contenedor” grande (ej. Portal afiliaciones, Campaña fuerza de venta).',
                            'Épicas — grupos de historias relacionadas dentro de un proyecto.',
                            'Subproyectos — fases o bloques opcionales (no son épicas; sirven para estructura).',
                            'Sprints — ventanas de tiempo (ej. 4 días o 1 semana) con un objetivo claro.',
                            'Backlog — lista priorizada de historias aún sin sprint (o fuera del sprint).',
                            'Actividades — todas las historias/tareas con detalle completo.',
                            'Kanban — tablero visual Por hacer → En progreso → En revisión → Finalizada.',
                            'Departamentos / Equipos — quién puede ejecutar el trabajo.',
                            'Ayuda — esta guía.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'conceptos',
                'title' => 'Conceptos en lenguaje simple',
                'eyebrow' => 'Diccionario',
                'summary' => 'Cada pieza del módulo explicada con un “para qué sirve”.',
                'blocks' => [
                    [
                        'type' => 'cards',
                        'cards' => [
                            [
                                'title' => 'Proyecto',
                                'body' => 'El esfuerzo completo. Ejemplo: “Mejorar plantillas de marketing para fuerza de venta”.',
                            ],
                            [
                                'title' => 'Épica',
                                'body' => 'Un paquete grande de valor. Ejemplo: “Plantillas WhatsApp” o “Ticket #31 correos testigos”.',
                            ],
                            [
                                'title' => 'Historia / Actividad',
                                'body' => 'Una unidad de trabajo entregable. Ejemplo: “Actualizar plantilla de cotización PDF”.',
                            ],
                            [
                                'title' => 'Sprint',
                                'body' => 'Un plazo corto con meta. Ejemplo: “En 4 días, correos de testigos llegan bien”.',
                            ],
                            [
                                'title' => 'Story points',
                                'body' => 'Tamaño relativo del esfuerzo (1 chico, 3 medio, 8 grande). No son horas exactas.',
                            ],
                            [
                                'title' => 'Product Owner / Scrum Master',
                                'body' => 'PO decide prioridad de negocio. SM facilita el ritmo del equipo. Se eligen en el Proyecto (pestaña Scrum).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'paso-a-paso',
                'title' => 'Flujo paso a paso (cualquier proyecto)',
                'eyebrow' => 'Operación diaria',
                'summary' => 'Sigue este orden la primera vez. Después se vuelve automático.',
                'blocks' => [
                    [
                        'type' => 'steps',
                        'steps' => [
                            [
                                'title' => '1. Crea el Proyecto',
                                'body' => 'Ve a Proyectos → Nuevo. Pon nombre, estatus Activo, fechas si las tienes y descripción breve. En la pestaña Scrum asigna Product Owner y Scrum Master.',
                            ],
                            [
                                'title' => '2. (Opcional) Crea Épicas',
                                'body' => 'Si el trabajo es grande, parte en épicas. Ejemplo Marketing: “Plantillas correo”, “Plantillas WhatsApp”. Ejemplo Sistemas: “Ticket #31”.',
                            ],
                            [
                                'title' => '3. Llena el Backlog',
                                'body' => 'En Backlog → Nueva historia. Escribe título claro, puntos, prioridad y criterios de aceptación (“qué debe cumplirse para decir listo”). Ordénalas con Subir/Bajar.',
                            ],
                            [
                                'title' => '4. Crea el Sprint',
                                'body' => 'En Sprints → Nuevo. Nombre, objetivo (Sprint Goal), fechas (ej. 4 días) y estatus Planificado.',
                            ],
                            [
                                'title' => '5. Planning: mete historias al sprint',
                                'body' => 'Edita cada historia y asígnale el Sprint (o créalas ya con ese sprint). Suma story points razonables. Agenda la ceremonia Planning dentro del sprint.',
                            ],
                            [
                                'title' => '6. Activa el Sprint',
                                'body' => 'En la ficha del sprint pulsa Activar. Solo puede haber un sprint activo por proyecto.',
                            ],
                            [
                                'title' => '7. Trabaja en el Kanban',
                                'body' => 'Filtro Proyecto + Sprint activo. Arrastra tarjetas entre columnas. Asigna responsables editando la actividad.',
                            ],
                            [
                                'title' => '8. Daily / Review / Retro',
                                'body' => 'Registra ceremonias en el sprint. Al terminar, Completar sprint: lo no finalizado vuelve solo al Backlog.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'ejemplo-marketing',
                'title' => 'Ejemplo sencillo: Marketing',
                'eyebrow' => 'Proyecto de departamento',
                'summary' => 'Mejorar plantillas de marketing para la fuerza de venta — sin necesidad de un sprint largo.',
                'blocks' => [
                    [
                        'type' => 'paragraph',
                        'body' => 'Ideal cuando el trabajo es claro, corto y de un solo equipo (Marketing + Comercial). Puedes usar backlog + Kanban; el sprint es opcional pero ayuda a no dejarlo abierto por semanas.',
                    ],
                    [
                        'type' => 'callout',
                        'title' => 'Proyecto',
                        'body' => 'Nombre: “Plantillas marketing para fuerza de venta”. Objetivo de negocio: que los agentes envíen materiales actualizados y consistentes.',
                    ],
                    [
                        'type' => 'list',
                        'title' => 'Épicas sugeridas',
                        'items' => [
                            'Épica A — Plantillas de correo comercial',
                            'Épica B — Piezas WhatsApp / One-pagers',
                        ],
                    ],
                    [
                        'type' => 'list',
                        'title' => 'Historias ejemplo (Backlog)',
                        'items' => [
                            'Actualizar plantilla de cotización PDF (3 pts, Alta)',
                            'Crear secuencia de 3 correos de seguimiento (5 pts, Media)',
                            'Diseñar tarjeta WhatsApp de beneficios (2 pts, Media)',
                            'Publicar guía rápida para agentes (1 pt, Baja)',
                        ],
                    ],
                    [
                        'type' => 'steps',
                        'steps' => [
                            [
                                'title' => 'Qué hace el PO (Marketing)',
                                'body' => 'Ordena el backlog: primero lo que desbloquea ventas esta semana.',
                            ],
                            [
                                'title' => 'Qué hace el equipo',
                                'body' => 'Toma 2–4 historias, las mete a un sprint de 5 días y las mueve en Kanban hasta Finalizada.',
                            ],
                            [
                                'title' => 'Cómo sabes que terminó',
                                'body' => 'Criterios de aceptación cumplidos + Review corta mostrando las plantillas a un líder comercial.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'ejemplo-sistemas',
                'title' => 'Ejemplo complejo: Sistemas',
                'eyebrow' => 'Sprint técnico',
                'summary' => 'Corrección de correos de testigos en afiliaciones individuales — con sprint, ceremonias y métricas.',
                'blocks' => [
                    [
                        'type' => 'paragraph',
                        'body' => 'Ideal cuando hay dependencia técnica, varias personas y necesidad de medir avance (burndown / velocity).',
                    ],
                    [
                        'type' => 'callout',
                        'title' => 'Sprint Goal',
                        'body' => '“Los correos de testigos de afiliaciones individuales llegan a los destinatarios correctos y queda validado en QA.”',
                    ],
                    [
                        'type' => 'list',
                        'title' => 'Historias típicas dentro del sprint',
                        'items' => [
                            'Resolver destinatarios de testigos (5 pts) → Finalizada',
                            'Validar plantilla de correo (2 pts) → Finalizada',
                            'Ajustar CC/BCC por documento (3 pts) → En progreso',
                            'Log de envíos fallidos (3 pts) → En revisión',
                            'Prueba integral QA (5 pts) → Por hacer',
                            'Documentar procedimiento (1 pt) → Por hacer',
                        ],
                    ],
                    [
                        'type' => 'list',
                        'title' => 'Ceremonias (qué registrar)',
                        'items' => [
                            'Planning — qué entra al sprint y por qué (compromiso de puntos).',
                            'Daily — 10–15 min: qué hice, qué haré, bloqueos.',
                            'Review — demo del fix a PO / interesados.',
                            'Retro — qué mejorar en el siguiente ciclo.',
                        ],
                    ],
                    [
                        'type' => 'paragraph',
                        'body' => 'En Kanban filtra Proyecto + Sprint activo. El chip de puntos muestra compromiso / done / restante. En la ficha del sprint ves el burndown.',
                    ],
                ],
            ],
            [
                'id' => 'kanban',
                'title' => 'Kanban: cómo trabajar el día a día',
                'eyebrow' => 'Ejecución',
                'summary' => 'El tablero es tu mesa de trabajo. Menos filtros abiertos = más foco.',
                'blocks' => [
                    [
                        'type' => 'list',
                        'items' => [
                            'Por defecto usa Sprint activo para no mezclar trabajo viejo.',
                            'Product Backlog en el filtro muestra solo historias sin sprint.',
                            'Arrastra tarjetas entre columnas; al salir de Finalizada se limpia el archivo automático del tablero si aplica.',
                            'Notas y documentos se cargan desde la tarjeta (modales).',
                            'Asigna colaboradores, equipo o departamento editando la actividad.',
                        ],
                    ],
                    [
                        'type' => 'callout',
                        'title' => 'Si el tablero se ve vacío',
                        'body' => 'Revisa: 1) hay un sprint Activo, 2) las historias tienen ese sprint, 3) el filtro de proyecto no está en otro proyecto, 4) Visibilidad = En tablero.',
                    ],
                ],
            ],
            [
                'id' => 'metricas',
                'title' => 'Story points, burndown y velocity',
                'eyebrow' => 'Métricas',
                'summary' => 'Sirven para conversar sobre carga y avance, no para castigar.',
                'blocks' => [
                    [
                        'type' => 'cards',
                        'cards' => [
                            [
                                'title' => 'Story points',
                                'body' => 'Compara tamaño: “esto es el doble de aquello”. Escala típica 1, 2, 3, 5, 8.',
                            ],
                            [
                                'title' => 'Burndown',
                                'body' => 'Línea ideal vs puntos restantes del sprint. Se actualiza al cambiar estatus o puntos.',
                            ],
                            [
                                'title' => 'Velocity',
                                'body' => 'Promedio de puntos terminados en sprints completados. Ayuda a planear el siguiente.',
                            ],
                        ],
                    ],
                    [
                        'type' => 'paragraph',
                        'body' => 'Al Completar sprint, las historias no Finalizadas vuelven al Backlog solas. Las Finalizadas se quedan asociadas al sprint (cuentan para velocity).',
                    ],
                ],
            ],
            [
                'id' => 'equipos',
                'title' => 'Equipos, departamentos y asignación',
                'eyebrow' => 'Personas',
                'summary' => 'Quién hace el trabajo y cómo se refleja en la actividad.',
                'blocks' => [
                    [
                        'type' => 'list',
                        'items' => [
                            'Colaborador(es) — una o varias personas de RRHH.',
                            'Equipo — grupo con integrantes (collaborator_ids).',
                            'Departamento — unidad organizacional del módulo de proyectos.',
                            'Una historia puede crearse sin responsable (Backlog rápido) y asignarse después.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'checklist',
                'title' => 'Checklist express',
                'eyebrow' => 'Antes de empezar',
                'summary' => 'Imprime mentalmente esto al abrir un proyecto nuevo.',
                'blocks' => [
                    [
                        'type' => 'list',
                        'items' => [
                            '¿Existe el Proyecto con PO y SM?',
                            '¿Hay épicas si el alcance es grande?',
                            '¿El Backlog está ordenado (arriba = más importante)?',
                            '¿El Sprint tiene objetivo y fechas reales?',
                            '¿Las historias del sprint tienen puntos y criterios de aceptación?',
                            '¿El sprint está Activo (solo uno)?',
                            '¿El Kanban filtra ese sprint?',
                            '¿Quedaron Planning / Daily / Review / Retro registradas?',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'faq',
                'title' => 'Preguntas frecuentes',
                'eyebrow' => 'FAQ',
                'summary' => 'Dudas típicas al usar el módulo por primera vez.',
                'blocks' => [
                    [
                        'type' => 'steps',
                        'steps' => [
                            [
                                'title' => '¿Subproyecto o Épica?',
                                'body' => 'Subproyecto = estructura/fase del plan. Épica = agrupación de valor para Scrum/backlog. Pueden convivir.',
                            ],
                            [
                                'title' => '¿Obligatorio usar sprints?',
                                'body' => 'No. Puedes trabajar solo con Actividades + Kanban. Los sprints aportan foco y métricas.',
                            ],
                            [
                                'title' => '¿Puedo tener dos sprints activos?',
                                'body' => 'No, por proyecto. Completa el actual y luego activa el siguiente.',
                            ],
                            [
                                'title' => '¿Qué pasa al completar el sprint?',
                                'body' => 'Lo no Finalizado vuelve al Backlog. Lo Finalizado permanece en el sprint para medir velocity.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<array{id: string, title: string}>
     */
    public static function toc(): array
    {
        return collect(self::sections())
            ->map(fn (array $section): array => [
                'id' => $section['id'],
                'title' => $section['title'],
            ])
            ->values()
            ->all();
    }
}
