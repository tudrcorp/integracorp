<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Validation\Rules\Unique;

final class TelemedicineClinicalCatalogForm
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'CUBIERTO' => 'CUBIERTO',
            'NO CUBIERTO' => 'NO CUBIERTO',
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    public static function configure(
        Schema $schema,
        string $model,
        string $nameLabel,
        string $placeholder,
        string $sectionTitle,
        string $sectionDescription,
        string $nameIcon = 'heroicon-m-beaker',
    ): Schema {
        return $schema
            ->components([
                Section::make($sectionTitle)
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->description($sectionDescription)
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label($nameLabel)
                                    ->prefixIcon($nameIcon)
                                    ->placeholder($placeholder)
                                    ->required()
                                    ->maxLength(255)
                                    ->autofocus()
                                    ->unique(
                                        table: (new $model)->getTable(),
                                        column: 'name',
                                        ignoreRecord: true,
                                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('type', $get('type')),
                                    )
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('name', $state.toUpperCase());
                                    JS)
                                    ->helperText('Se guarda en mayúsculas. Es el nombre que verá el médico en la consulta.')
                                    ->validationMessages([
                                        'required' => 'Escriba el nombre.',
                                        'unique' => 'Ya existe un registro con este nombre y tipo de cobertura.',
                                    ]),
                                ToggleButtons::make('type')
                                    ->label('Tipo de cobertura')
                                    ->options(self::typeOptions())
                                    ->colors([
                                        'CUBIERTO' => 'success',
                                        'NO CUBIERTO' => 'warning',
                                    ])
                                    ->icons([
                                        'CUBIERTO' => Heroicon::OutlinedCheckCircle,
                                        'NO CUBIERTO' => Heroicon::OutlinedExclamationTriangle,
                                    ])
                                    ->inline()
                                    ->grouped()
                                    ->required()
                                    ->default('CUBIERTO')
                                    ->helperText('CUBIERTO se ofrece como incluido. NO CUBIERTO se puede indicar, pero no está cubierto por el plan.')
                                    ->validationMessages([
                                        'required' => 'Elija si es cubierto o no cubierto.',
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $data['name'] = mb_strtoupper(trim((string) ($data['name'] ?? '')));
        $data['type'] = mb_strtoupper(trim((string) ($data['type'] ?? '')));

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeSpecialist(array $data): array
    {
        $data = self::normalize($data);

        if (DatabaseSchema::hasColumn('telemedicine_list_specialists', 'type_two')) {
            $data['type_two'] = $data['type'] === 'NO CUBIERTO' ? 'NO CUBIERTO' : null;
        }

        return $data;
    }
}
