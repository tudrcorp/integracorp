<?php

namespace App\Filament\Operations\Resources\Helpdesks\Schemas;

use App\Models\RrhhColaborador;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                            ->disabledOn('edit')
                            ->helperText(fn (string $operation): string => $operation === 'edit'
                                ? 'Solo lectura. El único dato editable en esta pantalla es «Creado por» (más abajo).'
                                : 'Mínimo 10 caracteres. Incluye capturas en la siguiente sección si aplica.'),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ]),

                Section::make('Prioridad, asignación y evidencias')
                    ->description('Define la urgencia, asigna a uno o varios compañeros si quieres y adjunta capturas o documentos.')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->iconColor('warning')
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 3])
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
                                    ->disabledOn('edit')
                                    ->helperText('Alta: solo si afecta operación o plazos críticos.'),
                                Select::make('rrhhColaboradores')
                                    ->label('Asignar a')
                                    ->prefixIcon('heroicon-m-user-group')
                                    ->multiple()
                                    ->relationship(
                                        name: 'rrhhColaboradores',
                                        titleAttribute: 'fullName',
                                        modifyQueryUsing: function ($query) {
                                            $myId = RrhhColaborador::query()
                                                ->where('user_id', Auth::id())
                                                ->value('id');
                                            if ($myId !== null) {
                                                $query->where(
                                                    $query->getModel()->getQualifiedKeyName(),
                                                    '!=',
                                                    $myId,
                                                );
                                            }

                                            return $query;
                                        },
                                    )
                                    ->searchable()
                                    ->required()
                                    ->preload()
                                    ->hiddenOn('edit')
                                    ->helperText('Opcional. Puedes elegir varios colaboradores. No puedes asignarte el ticket a ti mismo.'),
                                Select::make('cc_colaboradores')
                                    ->label('CC (Opcional)')
                                    ->prefixIcon('heroicon-m-user-group')
                                    ->multiple()
                                    ->options(fn (): array => self::rrhhColaboradorOptionsForHelpdeskMultiselect())
                                    ->searchable()
                                    ->preload()
                                    ->hiddenOn('edit')
                                    ->helperText('Reciben el mismo correo que el asignado, en copia junto a solrodriguez@tudrencasa.com. Deben tener correo corporativo.'),
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
                            ->disabledOn('edit')
                            ->helperText(fn (string $operation): string => $operation === 'edit'
                                ? 'Solo lectura. Los adjuntos no se pueden cambiar desde la edición.'
                                : 'Hasta 2 MB. Captura de pantalla, PDF o foto del error.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ]),

                Section::make('Creador del ticket')
                    ->description('Único campo editable al revisar un ticket existente.')
                    ->icon('heroicon-o-user')
                    ->iconColor('success')
                    ->visibleOn('edit')
                    ->schema([
                        TextInput::make('created_by')
                            ->label('Creado por')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('Nombre o identificador registrado como autor del ticket.'),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ]),

                Hidden::make('created_by')
                    ->default(fn (): ?string => Auth::user()?->name)
                    ->hiddenOn('edit'),
            ]);
    }

    /**
     * Opciones para multiselect (asignación / CC) sin enlazar al pivot de asignados.
     *
     * @return array<int, string>
     */
    public static function rrhhColaboradorOptionsForHelpdeskMultiselect(): array
    {
        $query = RrhhColaborador::query()->orderBy('fullName');
        $myId = RrhhColaborador::query()->where('user_id', Auth::id())->value('id');
        if ($myId !== null) {
            $query->where('id', '!=', $myId);
        }

        return $query->pluck('fullName', 'id')->all();
    }
}
