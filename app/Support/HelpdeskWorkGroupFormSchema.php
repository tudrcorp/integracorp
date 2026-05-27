<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Alignment;

final class HelpdeskWorkGroupFormSchema
{
    private const IOS_SECTION_CLASS = 'fi-helpdesk-ios-section';

    private const IOS_INNER_CLASS = 'fi-helpdesk-ios-inset';

    /**
     * @return array<int, mixed>
     */
    public static function components(): array
    {
        return [
            Section::make('Opciones')
                ->description('Active el formulario cuando desee registrar un grupo nuevo.')
                ->icon('heroicon-o-adjustments-horizontal')
                ->iconColor('gray')
                ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
                ->schema([
                    Checkbox::make('show_create_form')
                        ->label('Mostrar formulario para crear un grupo')
                        ->live()
                        ->default(false)
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Nuevo grupo de trabajo')
                ->description('Identifique el equipo, defina la cuota y seleccione integrantes.')
                ->icon('heroicon-o-user-group')
                ->iconColor('primary')
                ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
                ->visible(fn (Get $get): bool => (bool) $get('show_create_form'))
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2])
                        ->extraAttributes(['class' => self::IOS_INNER_CLASS])
                        ->schema([
                            TextInput::make('name')
                                ->label('Nombre del grupo')
                                ->placeholder('Ej.: Mesa de ayuda TI')
                                ->required()
                                ->maxLength(255)
                                ->prefixIcon('heroicon-m-user-group')
                                ->columnSpanFull(),

                            Select::make('status')
                                ->label('Estado')
                                ->required()
                                ->native(false)
                                ->options([
                                    'ACTIVO' => 'Activo',
                                    'INACTIVO' => 'Inactivo',
                                ])
                                ->default('ACTIVO')
                                ->prefixIcon('heroicon-m-signal'),

                            TextInput::make('total_tickets_assigned')
                                ->label('Cuota de tickets')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->prefixIcon('heroicon-m-ticket')
                                ->suffix('tickets')
                                ->helperText('Máximo simultáneo para el grupo.'),

                            Select::make('team_colaborador_ids')
                                ->label('Integrantes del grupo')
                                ->multiple()
                                ->required()
                                ->rules(['array', 'min:2'])
                                ->validationMessages([
                                    'min' => 'Seleccione al menos dos colaboradores para el grupo.',
                                ])
                                ->options(HelpdeskFormSchema::rrhhColaboradorOptionsForHelpdeskWorkGroups())
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->prefixIcon('heroicon-m-users')
                                ->helperText('Directorio RRHH completo, excepto Cayetano Batres. Mínimo 2 personas.')
                                ->columnSpanFull(),
                        ]),

                    Actions::make([
                        Action::make('submitCreateGroup')
                            ->label('Crear grupo')
                            ->submit('callMountedAction')
                            ->extraAttributes([
                                'class' => HelpdeskWorkGroupHeaderAction::SUBMIT_BUTTON_CLASS,
                            ]),
                    ])
                        ->alignment(Alignment::End)
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function shouldCreateGroup(array $data): bool
    {
        return (bool) ($data['show_create_form'] ?? false);
    }
}
