<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\ApiPartners\Schemas;

use App\Enums\ApiPartnerAbility;
use App\Models\ApiPartner;
use App\Support\ApiPartners\ApiPartnerSupplierOptions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class ApiPartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Aliado')
                    ->description('Quién se conecta. El token se genera desde la ficha del aliado, después de guardar.')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del aliado')
                            ->required()
                            ->maxLength(150)
                            ->placeholder('Ej.: Farmadoc')
                            ->columnSpanFull(),
                        TextInput::make('contact_name')
                            ->label('Persona de contacto')
                            ->maxLength(150),
                        TextInput::make('contact_email')
                            ->label('Correo de contacto')
                            ->email()
                            ->maxLength(150),
                        Select::make('supplier_id')
                            ->label('Proveedor jurídico vinculado')
                            ->placeholder('Busca por nombre o RIF')
                            ->helperText('Opcional. Identifica al aliado con su registro en Proveedores jurídicos; no cambia su acceso al API.')
                            ->searchable()
                            ->searchPrompt('Escribe al menos 2 letras del nombre o el RIF')
                            ->noSearchResultsMessage('Ningún proveedor coincide.')
                            ->getSearchResultsUsing(fn (string $search): array => ApiPartnerSupplierOptions::search($search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => ApiPartnerSupplierOptions::label($value))
                            ->rule(fn (): Exists => Rule::exists('suppliers', 'id')->whereNull('deleted_at'))
                            ->validationMessages(['exists' => 'El proveedor seleccionado no existe o fue eliminado.'])
                            ->visible(fn (): bool => ApiPartner::supplierColumnExists())
                            ->columnSpanFull(),
                    ]),
                Section::make('Acceso')
                    ->description('Qué puede consultar y con qué frecuencia. El acceso es solo con el token.')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('abilities')
                            ->label('Permisos')
                            ->options(ApiPartnerAbility::options())
                            ->required()
                            ->minItems(1)
                            ->validationMessages(['required' => 'Marca al menos un permiso.', 'min' => 'Marca al menos un permiso.'])
                            ->default([ApiPartnerAbility::NonCoveredMedicationsRead->value])
                            ->columnSpanFull(),
                        TextInput::make('rate_limit_per_minute')
                            ->label('Límite de peticiones por minuto')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->default(60)
                            ->minValue(ApiPartner::MIN_RATE_LIMIT)
                            ->maxValue(ApiPartner::MAX_RATE_LIMIT)
                            ->suffix('por minuto'),
                        DateTimePicker::make('expires_at')
                            ->label('Acceso válido hasta')
                            ->native(false)
                            ->seconds(false)
                            ->minDate(fn (string $operation): ?\Illuminate\Support\Carbon => $operation === 'create' ? now() : null)
                            ->helperText('Vacío = sin fecha de vencimiento.'),
                        Toggle::make('is_active')
                            ->label('Acceso activo')
                            ->helperText('Al desactivarlo, el aliado deja de poder consultar de inmediato, aunque tenga token.')
                            ->default(true)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Notas internas')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
