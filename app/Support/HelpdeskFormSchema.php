<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RrhhColaborador;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

final class HelpdeskFormSchema
{
    private const IOS_SECTION_CLASS = 'fi-helpdesk-ios-section';

    private const IOS_INNER_CLASS = 'fi-helpdesk-ios-inset';

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    /**
     * @return array<int, string>
     */
    public static function rrhhColaboradorOptionsForHelpdeskMultiselect(): array
    {
        $query = RrhhColaborador::query()
            ->whereNotNull('user_id')
            ->orderBy('fullName');

        return $query
            ->pluck('fullName', $query->getModel()->getQualifiedKeyName())
            ->mapWithKeys(
                static fn (mixed $name, mixed $id): array => [(int) $id => (string) $name]
            )
            ->all();
    }

    /**
     * Todos los colaboradores del directorio RRHH, excepto exclusiones de grupos de trabajo.
     *
     * @return array<int, string>
     */
    public static function rrhhColaboradorOptionsForHelpdeskWorkGroups(): array
    {
        return RrhhColaborador::query()
            ->orderBy('fullName')
            ->get(['id', 'fullName'])
            ->reject(
                static fn (RrhhColaborador $colaborador): bool => self::isExcludedFromHelpdeskWorkGroups(
                    (string) $colaborador->fullName
                )
            )
            ->mapWithKeys(
                static fn (RrhhColaborador $colaborador): array => [
                    (int) $colaborador->id => (string) $colaborador->fullName,
                ]
            )
            ->all();
    }

    public static function isExcludedFromHelpdeskWorkGroups(?string $fullName): bool
    {
        $normalized = mb_strtoupper(preg_replace('/[^A-Z0-9]+/u', ' ', trim((string) $fullName)) ?? '');

        return str_contains($normalized, 'CAYETANO') && str_contains($normalized, 'BATRES');
    }

    public static function configure(Schema $schema, bool $assigneesRequired = true): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('helpdeskFormTabs')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Ticket')
                            ->icon('heroicon-o-ticket')
                            ->schema(self::ticketTabSchema($assigneesRequired)),

                        Tab::make('Tipo de ticket')
                            ->icon('heroicon-o-tag')
                            ->schema(self::ticketTypeTabSchema()),

                        Tab::make('Compromiso de atención')
                            ->icon('heroicon-o-shield-check')
                            ->hiddenOn('edit')
                            ->schema(self::technologyTermsTabSchema()),
                    ]),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function ticketTabSchema(bool $assigneesRequired): array
    {
        return [
            Placeholder::make('ticket_form_intro')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">'
                    .'<span class="font-semibold text-gray-900 dark:text-white">Paso 1 — Detalle del ticket.</span> '
                    .'Describe el problema con claridad, define prioridad y asigna responsables. '
                    .'Los campos marcados con <span class="text-danger-600 dark:text-danger-400">*</span> son obligatorios.'
                    .'</p>'
                ))
                ->columnSpanFull(),

            Section::make('Descripción del caso')
                ->description('Cuanto más contexto incluyas, más rápida será la resolución.')
                ->icon('heroicon-o-document-text')
                ->iconColor('info')
                ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
                ->schema([
                    Grid::make(1)
                        ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                        ->schema([
                            Textarea::make('description')
                                ->label('¿Qué necesitas resolver?')
                                ->placeholder('Ej.: error al cargar reporte, acceso a módulo, incidencia en póliza…')
                                ->required()
                                ->autosize()
                                ->extraInputAttributes([
                                    'class' => 'min-h-[10rem]',
                                ])
                                ->helperText('Incluye pasos para reproducir, capturas en adjuntos y plazos si aplica.')
                                ->columnSpanFull()
                                ->disabledOn('edit'),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make('Clasificación')
                ->description('Define la prioridad con la que se atenderá el caso.')
                ->icon('heroicon-o-adjustments-horizontal')
                ->iconColor('warning')
                ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])
                        ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                        ->schema([
                            Select::make('priority')
                                ->label('Prioridad')
                                ->required()
                                ->native(false)
                                ->options([
                                    'BAJA' => 'Baja — puede esperar',
                                    'MEDIA' => 'Media — atención estándar',
                                    'ALTA' => 'Alta — requiere pronta atención',
                                ])
                                ->default('MEDIA')
                                ->prefixIcon('heroicon-m-bolt')
                                ->helperText('Alta: impacto operativo o cliente. Media: flujo normal. Baja: mejora o consulta.')
                                ->disabledOn('edit'),

                            Select::make('status')
                                ->label('Estado inicial')
                                ->required()
                                ->native(false)
                                ->options(HelpdeskTaskStatusOptions::all())
                                ->default(HelpdeskTaskStatusOptions::STATUS_PENDING)
                                ->prefixIcon('heroicon-m-flag')
                                ->helperText('Tras crear el ticket, el estado se actualiza desde la tabla o la vista del caso.')
                                ->hiddenOn('create')
                                ->disabledOn('edit'),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make('Personas involucradas')
                ->description('Quién ejecuta el ticket y quién solo recibe avisos.')
                ->icon('heroicon-o-users')
                ->iconColor('primary')
                ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
                ->schema([
                    Grid::make(3)
                        ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                        ->schema([
                            Select::make('rrhhColaboradores')
                                ->label('Asignados (ejecutan el ticket)')
                                ->relationship(
                                    name: 'rrhhColaboradores',
                                    titleAttribute: 'fullName',
                                    modifyQueryUsing: fn ($query) => $query->orderBy('fullName')
                                )
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->native(false)
                                ->prefixIcon('heroicon-m-user-plus')
                                ->required($assigneesRequired)
                                ->helperText($assigneesRequired
                                    ? 'Seleccione uno o más colaboradores responsables de resolver el caso.'
                                    : 'Opcional en este panel: puede dejar el ticket sin asignar y definirlo después.')
                                ->disabledOn('edit'),

                            Select::make('cc_colaboradores')
                                ->label('CC (solo notificación)')
                                ->helperText('Reciben aviso por correo o canal interno; no quedan como ejecutores del ticket.')
                                ->multiple()
                                ->options(self::rrhhColaboradorOptionsForHelpdeskMultiselect())
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->prefixIcon('heroicon-m-bell-alert')
                                ->disabledOn('edit'),

                            TextInput::make('created_by')
                                ->label('Creador del ticket')
                                ->default(fn (): ?string => Auth::user()?->name)
                                ->disabled()
                                ->dehydrated()
                                ->dehydratedWhenHidden()
                                ->prefixIcon('heroicon-m-identification')
                                ->helperText('Se registra automáticamente con su usuario de sesión.')
                                ->hiddenOn('create'),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make('Adjuntos')
                ->description('PDF, presentaciones e imágenes de soporte (máx. 10 MB).')
                ->icon('heroicon-o-paper-clip')
                ->iconColor('gray')
                ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
                ->schema([
                    Grid::make(1)
                        ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                        ->schema([
                            FileUpload::make('image')
                                ->label('Archivos adjuntos')
                                ->disk('public')
                                ->directory('helpdesks')
                                ->acceptedFileTypes([
                                    'application/pdf',
                                    'application/vnd.ms-powerpoint',
                                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                    'image/jpeg',
                                    'image/png',
                                    'image/webp',
                                    'image/gif',
                                ])
                                ->maxSize(10240)
                                ->openable()
                                ->downloadable()
                                ->previewable()
                                ->imagePreviewHeight('120')
                                ->panelLayout('compact')
                                ->helperText('Formatos: PDF, PPT/PPTX, JPG, PNG, WebP o GIF. Un archivo por ticket.')
                                ->columnSpanFull()
                                ->disabledOn('edit'),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function ticketTypeTabSchema(): array
    {
        return [
            Placeholder::make('ticket_type_intro')
                ->hiddenLabel()
                ->content(HelpdeskTicketType::tabIntro())
                ->columnSpanFull(),

            Section::make('Su selección')
                ->description('Marque la opción que mejor describa su solicitud.')
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->extraAttributes([
                    'class' => self::IOS_SECTION_CLASS.' fi-helpdesk-ticket-type-panel',
                ])
                ->schema([
                    Grid::make(1)
                        ->extraAttributes(['class' => self::IOS_INNER_CLASS.' fi-helpdesk-ticket-type-fields'])
                        ->schema([
                            Radio::make('ticket_type')
                                ->label('Tipo de ticket')
                                ->required()
                                ->live()
                                ->options(HelpdeskTicketType::options())
                                ->descriptions(HelpdeskTicketType::radioDescriptions())
                                ->columns(1)
                                ->validationMessages([
                                    'required' => 'Seleccione el tipo de ticket antes de continuar.',
                                ])
                                ->columnSpanFull()
                                ->disabledOn('edit'),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function technologyTermsTabSchema(): array
    {
        return [
            Placeholder::make('technology_terms_intro')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">'
                    .'<span class="font-semibold text-gray-900 dark:text-white">Paso 3 — Compromiso de atención.</span> '
                    .'Lea el siguiente aviso y confirme su aceptación para poder enviar el ticket.'
                    .'</p>'
                ))
                ->columnSpanFull(),

            Section::make('Departamento de Tecnología y Sistemas')
                ->description('Proceso de evaluación y plazos de respuesta.')
                ->icon('heroicon-o-computer-desktop')
                ->iconColor('info')
                ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
                ->schema([
                    Grid::make(1)
                        ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                        ->schema([
                            Placeholder::make('technology_terms_notice')
                                ->hiddenLabel()
                                ->content(HelpdeskTechnologyTermsNotice::bodyHtml())
                                ->columnSpanFull(),

                            Checkbox::make(HelpdeskTechnologyTermsNotice::ACCEPTANCE_FIELD)
                                ->label(HelpdeskTechnologyTermsNotice::acceptanceLabel())
                                ->accepted()
                                ->required()
                                ->dehydrated(false)
                                ->validationMessages([
                                    'accepted' => 'Debe aceptar el aviso para crear el ticket.',
                                    'required' => 'Debe aceptar el aviso para crear el ticket.',
                                ])
                                ->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function executionTeamTabSchema(): array
    {
        return [
            Placeholder::make('team_form_intro')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">'
                    .'<span class="font-semibold text-gray-900 dark:text-white">Paso 3 — Equipo (opcional).</span> '
                    .'Agrupe colaboradores bajo un nombre de equipo cuando varias personas ejecuten la misma tarea en paralelo.'
                    .'</p>'
                ))
                ->columnSpanFull(),

            Section::make('Equipo de ejecución')
                ->description('Opcional. Si define equipo, indique al menos dos integrantes.')
                ->icon('heroicon-o-user-group')
                ->iconColor('primary')
                ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])
                        ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                        ->schema([
                            TextInput::make('team')
                                ->label('Nombre del equipo')
                                ->placeholder('Ej.: Mesa de ayuda TI, Coordinación médica…')
                                ->maxLength(255)
                                ->prefixIcon('heroicon-m-user-group')
                                ->helperText('Nombre visible en el ticket y en notificaciones al equipo.')
                                ->columnSpanFull()
                                ->disabledOn('edit'),

                            Select::make('team_colaborador_ids')
                                ->label('Integrantes del equipo')
                                ->multiple()
                                ->options(self::rrhhColaboradorOptionsForHelpdeskMultiselect())
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->prefixIcon('heroicon-m-users')
                                ->rules(['min:2'])
                                ->validationMessages([
                                    'min' => 'Seleccione al menos dos colaboradores cuando defina un equipo.',
                                ])
                                ->helperText('Mínimo 2 personas. Deben tener usuario vinculado en RRHH.')
                                ->disabledOn('edit')
                                ->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }
}
