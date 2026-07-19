<?php

declare(strict_types=1);

namespace App\Support;

final class ScrumPresentationSlides
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
                'title' => 'Desarrollo de Aplicaciones con Scrum',
                'subtitle' => 'Construyendo software de forma ágil, rápida y adaptable.',
                'module' => 'Portada',
                'icon' => '⚙️',
                'color' => '#FCA311',
                'speaker_note' => null,
                'highlights' => [
                    'Marco ágil para apps iOS y Android',
                    'Entregas cortas, valor constante',
                    'Adaptación ante cambios de mercado',
                ],
                'tags' => ['Scrum', 'Apps', 'Agilidad'],
                'data' => [],
            ],
            [
                'id' => 'problema',
                'type' => 'problem',
                'title' => '¿Por qué la metodología tradicional ya no funciona para las Apps?',
                'subtitle' => 'El enfoque en cascada planifica todo durante meses… y llega tarde al mercado.',
                'module' => 'El problema',
                'icon' => '⚠️',
                'color' => '#FCA311',
                'speaker_note' => 'En el mundo de las apps, si tardas un año en lanzar, naces muerto. Necesitamos adaptabilidad.',
                'highlights' => [
                    'Planificar todo durante meses → Diseñar → Programar → Lanzar',
                    'Cuando la app sale, los requisitos, la tecnología o las políticas de Apple/Google ya cambiaron',
                    'El proyecto fracasa por llegar tarde al mercado',
                ],
                'tags' => ['Cascada', 'Riesgo'],
                'data' => [
                    'steps' => [
                        ['label' => 'Planificar', 'detail' => 'Meses de análisis y documentos'],
                        ['label' => 'Diseñar', 'detail' => 'UI/UX congelada al inicio'],
                        ['label' => 'Programar', 'detail' => 'Desarrollo monolítico'],
                        ['label' => 'Lanzar', 'detail' => 'Todo o nada al final'],
                    ],
                ],
            ],
            [
                'id' => 'que-es-scrum',
                'type' => 'cycle',
                'title' => 'Scrum en una Sola Frase',
                'subtitle' => 'Marco ágil para entregar valor en periodos cortos llamados Sprints (2 a 4 semanas).',
                'module' => 'Qué es Scrum',
                'icon' => '🔄',
                'color' => '#FCA311',
                'speaker_note' => null,
                'highlights' => [
                    'No se hace toda la app de golpe: se construye módulo por módulo',
                    'Sprint 1: Login y Registro',
                    'Sprint 2: Perfil de usuario',
                    'Sprint 3: Pasarela de pagos',
                ],
                'tags' => ['Sprints', 'Valor'],
                'data' => [
                    'modules' => [
                        ['sprint' => 'Sprint 1', 'feature' => 'Login y Registro'],
                        ['sprint' => 'Sprint 2', 'feature' => 'Perfil de usuario'],
                        ['sprint' => 'Sprint 3', 'feature' => 'Pasarela de pagos'],
                    ],
                ],
            ],
            [
                'id' => 'roles',
                'type' => 'roles',
                'title' => '¿Quién es quién en el desarrollo de la App?',
                'subtitle' => 'Tres roles claros. Toca cada tarjeta para ver su responsabilidad.',
                'module' => 'El equipo',
                'icon' => '👥',
                'color' => '#FCA311',
                'speaker_note' => null,
                'highlights' => [],
                'tags' => ['Roles', 'Equipo'],
                'data' => [
                    'roles' => [
                        [
                            'title' => 'Product Owner (PO)',
                            'short' => 'Dueño del producto',
                            'responsibility' => 'Representa al cliente. Decide qué funciones prioriza la app (ej. ¿va primero el chat o las notificaciones?).',
                            'icon' => '🎯',
                        ],
                        [
                            'title' => 'Scrum Master',
                            'short' => 'Facilitador',
                            'responsibility' => 'Elimina obstáculos del equipo técnico (ej. si falta una API de un proveedor o licencias de desarrollo).',
                            'icon' => '🛡️',
                        ],
                        [
                            'title' => 'Developers / UX Team',
                            'short' => 'Equipo técnico',
                            'responsibility' => 'Programadores iOS/Android, diseñadores UI y QAs. Estiman y transforman las ideas en código real.',
                            'icon' => '💻',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'artefactos',
                'type' => 'artifacts',
                'title' => '¿Cómo organizamos el trabajo?',
                'subtitle' => 'Los ladrillos de construcción: Backlog, Sprint Backlog e Incremento.',
                'module' => 'Artefactos',
                'icon' => '🧱',
                'color' => '#FCA311',
                'speaker_note' => null,
                'highlights' => [],
                'tags' => ['Backlog', 'Incremento'],
                'data' => [
                    'artifacts' => [
                        [
                            'title' => 'Product Backlog',
                            'aka' => 'Lista de deseos',
                            'description' => 'Lista maestra de todas las funciones que tendrá la app (Historias de Usuario).',
                            'example' => 'Como usuario, quiero recuperar mi contraseña por SMS.',
                        ],
                        [
                            'title' => 'Sprint Backlog',
                            'aka' => 'Ciclo actual',
                            'description' => 'Las tareas específicas elegidas para el ciclo actual de 2 semanas.',
                            'example' => 'Historias comprometidas + tareas técnicas del Sprint.',
                        ],
                        [
                            'title' => 'Incremento',
                            'aka' => 'Entregable real',
                            'description' => 'Resultado del Sprint: una versión de la app que funciona y el cliente puede probar en su teléfono.',
                            'example' => 'Build instalable en dispositivo de prueba.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'ciclo-sprint',
                'type' => 'lifecycle',
                'title' => 'La Rutina de la Agilidad',
                'subtitle' => 'El ciclo de vida de un Sprint en 4 pasos. Explora cada ceremonia.',
                'module' => 'Ciclo de vida',
                'icon' => '📅',
                'color' => '#FCA311',
                'speaker_note' => null,
                'highlights' => [],
                'tags' => ['Ceremonias', 'Sprint'],
                'data' => [
                    'steps' => [
                        [
                            'title' => 'Sprint Planning',
                            'detail' => 'Reunión al inicio del ciclo. El equipo decide qué Historias de Usuario del Backlog se pueden programar y probar en las próximas 2 semanas.',
                        ],
                        [
                            'title' => 'Daily Scrum (15 min)',
                            'detail' => 'Reunión diaria corta. Cada desarrollador responde: ¿Qué hice ayer? ¿Qué haré hoy? ¿Tengo algún problema técnico?',
                        ],
                        [
                            'title' => 'Sprint Review',
                            'detail' => 'Al final de las 2 semanas, el equipo demuestra la app funcionando al Product Owner y stakeholders.',
                        ],
                        [
                            'title' => 'Sprint Retrospective',
                            'detail' => 'Reunión interna para analizar qué funcionó, qué falló en el código o comunicación, y cómo mejorar en el siguiente Sprint.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'caso-delivery',
                'type' => 'timeline',
                'title' => 'Scrum en Acción: Caso Real',
                'subtitle' => 'Creando una app tipo UberEats. Avanza los sprints para ver el MVP nacer.',
                'module' => 'Caso práctico',
                'icon' => '🛵',
                'color' => '#FCA311',
                'speaker_note' => null,
                'highlights' => [
                    'En la semana 6 ya tenemos un Producto Mínimo Viable (MVP) listo para probar en el mercado.',
                ],
                'tags' => ['MVP', 'Delivery'],
                'data' => [
                    'goal' => 'Una app tipo UberEats',
                    'sprints' => [
                        [
                            'label' => 'Sprint 1',
                            'weeks' => 'Semanas 1–2',
                            'items' => ['Pantalla de registro', 'Mapa interactivo', 'Geolocalización'],
                            'result' => 'Incremento instalable en teléfono de prueba',
                        ],
                        [
                            'label' => 'Sprint 2',
                            'weeks' => 'Semanas 3–4',
                            'items' => ['Catálogo de restaurantes', 'Carrito de compras'],
                            'result' => 'Flujo de pedido parcial',
                        ],
                        [
                            'label' => 'Sprint 3',
                            'weeks' => 'Semanas 5–6',
                            'items' => ['Pasarela de pagos', 'Notificaciones Push'],
                            'result' => 'MVP listo para el mercado',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'beneficios',
                'type' => 'benefits',
                'title' => '¿Por qué elegir esta metodología?',
                'subtitle' => 'Tres beneficios concretos al aplicar Scrum en tu app.',
                'module' => 'Beneficios',
                'icon' => '✨',
                'color' => '#FCA311',
                'speaker_note' => null,
                'highlights' => [],
                'tags' => ['ROI', 'Riesgo'],
                'data' => [
                    'benefits' => [
                        [
                            'emoji' => '🚀',
                            'title' => 'Menor tiempo de comercialización',
                            'aka' => 'Time-to-Market',
                            'description' => 'Puedes lanzar una versión básica de la app rápido y añadirle funciones después.',
                        ],
                        [
                            'emoji' => '🎯',
                            'title' => 'Mitigación del riesgo',
                            'aka' => 'Validación temprana',
                            'description' => 'Si una función no le gusta a los usuarios, te das cuenta en 2 semanas, no en 1 año. Ahorras miles de dólares.',
                        ],
                        [
                            'emoji' => '📊',
                            'title' => 'Transparencia absoluta',
                            'aka' => 'Demos en vivo',
                            'description' => 'El cliente ve el progreso real de la app de forma quincenal mediante demos en vivo.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'conclusion',
                'type' => 'conclusion',
                'title' => 'Scrum no es una receta, es una cultura',
                'subtitle' => 'Flexibilidad ante usuarios que cambian de opinión y plataformas que se actualizan.',
                'module' => 'Conclusión',
                'icon' => '🧭',
                'color' => '#FCA311',
                'speaker_note' => null,
                'highlights' => [
                    'Desarrollar aplicaciones requiere flexibilidad porque los usuarios cambian de opinión y las plataformas (iOS/Android) se actualizan.',
                    'Scrum permite fallar rápido, corregir rápido y entregar software de alta calidad de forma constante.',
                ],
                'tags' => ['Cultura', 'Calidad'],
                'data' => [
                    'pillars' => ['Inspección', 'Adaptación', 'Transparencia'],
                    'closing' => 'Inspección, Adaptación y Transparencia',
                ],
            ],
            [
                'id' => 'preguntas',
                'type' => 'qa',
                'title' => '¡Gracias por su atención!',
                'subtitle' => '¿Tienen alguna duda sobre cómo implementar Scrum en su próximo proyecto?',
                'module' => 'Preguntas',
                'icon' => '💬',
                'color' => '#FCA311',
                'speaker_note' => null,
                'highlights' => [],
                'tags' => ['Q&A', 'Contacto'],
                'data' => [
                    'contact' => [
                        'name' => 'Dpto. De Tecnología y Sistemas',
                        'email' => 'gcamacho@tudrencasa.com',
                        'linkedin' => 'linkedin.com/in/gustavocamacho',
                        'org' => 'INTEGRACORP · TUDRGROUP',
                    ],
                ],
            ],
        ];
    }
}
