<?php

namespace App\Filament\Operations\Resources\Helpdesks\Schemas;

use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Support\HelpdeskTaskStatusOptions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class HelpdeskForm
{
    /**
     * Contenedor de sección: tarjeta tipo iOS (vidrio, sombra suave). Estilos en theme.css (.fi-helpdesk-ios-section).
     */
    private const IOS_SECTION_CLASS = 'fi-helpdesk-ios-section';

    /**
     * Agrupa campos con fondo inset estilo lista iOS.
     */
    private const IOS_INSET_CLASS = 'fi-helpdesk-ios-inset';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Describe tu caso')
                    ->description('Cuanto más contexto des (pasos, módulo, mensaje de error), más rápido podremos ayudarte.')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->iconColor('info')
                    ->schema([
                        Textarea::make('description')
                            ->label('¿Qué necesitas?')
                            ->placeholder('Ej.: En Negocios → Afiliaciones, al exportar el PDF el archivo sale vacío. Usuario: … Navegador: …')
                            ->rows(5)
                            ->autosize()
                            ->minLength(10)
                            ->maxLength(65000)
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Mínimo 10 caracteres. Incluye capturas en la siguiente sección si aplica.'),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ]),

                Section::make('Prioridad, asignación y evidencias')
                    ->description('Define la urgencia, delega en un compañero si quieres y adjunta capturas o documentos.')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->iconColor('warning')
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 2])
                            ->schema([
                                Select::make('priority')
                                    ->label('Prioridad')
                                    ->prefixIcon('heroicon-m-bolt')
                                    ->options([
                                        'BAJA' => 'Baja — puede esperar',
                                        'MEDIA' => 'Media — flujo normal',
                                        'ALTA' => 'Alta — bloquea trabajo',
                                    ])
                                    ->default('MEDIA')
                                    ->required()
                                    ->native(false)
                                    ->helperText('Alta: solo si afecta operación o plazos críticos.'),
                                Select::make('rrhh_colaborador_id')
                                    ->label('Asignar a')
                                    ->prefixIcon('heroicon-m-user-group')
                                    ->relationship(
                                        name: 'rrhhColaborador',
                                        titleAttribute: 'fullName',
                                        modifyQueryUsing: function ($query) {
                                            $myId = RrhhColaborador::query()
                                                ->where('user_id', Auth::id())
                                                ->value('id');
                                            if ($myId !== null) {
                                                $query->where('id', '!=', $myId);
                                            }

                                            return $query;
                                        },
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->hiddenOn('edit')
                                    ->helperText('Opcional. No puedes asignarte el ticket a ti mismo.'),
                            ])
                            ->extraAttributes([
                                'class' => self::IOS_INSET_CLASS,
                            ])
                            ->columnSpanFull(),

                        FileUpload::make('image')
                            ->label('Adjunto (imagen o PDF)')
                            ->directory('helpdesks-documents')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'])
                            ->imagePreviewHeight('160')
                            ->panelLayout('grid')
                            ->downloadable()
                            ->openable()
                            ->helperText('Hasta 2 MB. Captura de pantalla, PDF o foto del error.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ]),

                Section::make('Gestión del ticket')
                    ->description('Estado interno y notas de seguimiento (solo al editar).')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->iconColor('success')
                    ->visibleOn('edit')
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 2])
                            ->schema([
                                Select::make('status')
                                    ->label('Estado')
                                    ->prefixIcon('heroicon-m-flag')
                                    ->options(function (?HelpDesk $record): array {
                                        return HelpdeskTaskStatusOptions::forSelect($record, Auth::user()?->name);
                                    })
                                    ->default('PENDIENTE POR INICIAR')
                                    ->required()
                                    ->native(true)
                                    ->extraInputAttributes([
                                        'class' => 'helpdesk-status-native-select w-full max-w-full min-h-11 text-base sm:text-sm',
                                    ]),
                                Textarea::make('observation')
                                    ->label('Observación interna')
                                    ->placeholder('Ej.: Se contactó al usuario por Teams; pendiente validar en producción.')
                                    ->rows(4)
                                    ->autosize()
                                    ->required()
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                            ])
                            ->extraAttributes([
                                'class' => self::IOS_INSET_CLASS,
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ]),

                Hidden::make('created_by')
                    ->default(fn (): ?string => Auth::user()?->name),
            ]);
    }
}
