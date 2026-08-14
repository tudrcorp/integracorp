<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\WhiteCompanies\RelationManagers;

use App\Models\WhiteCompany;
use App\Models\WhiteCompanyFee;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\FilamentIosButton;
use App\Support\WhiteCompanies\WhiteCompanyCatalogFeeOptions;
use App\Support\WhiteCompanies\WhiteCompanyNegotiatedFeesBulkCreator;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

class NegotiatedFeesRelationManager extends RelationManager
{
    protected static string $relationship = 'negotiatedFees';

    protected static ?string $title = 'Matriz de negociación';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! parent::canViewForRecord($ownerRecord, $pageClass)) {
            return false;
        }

        return BusinessFilamentActionAccess::userCan(
            BusinessFilamentActionPermissionRegistry::MANAGE_WHITE_COMPANY_NEGOTIATED_FEES,
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->catalogFeeSelect(),
                TextInput::make('sale_price')
                    ->label('Precio de venta US$')
                    ->helperText('Monto anual que la empresa aliada vende al cliente.')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->prefix('$'),
                TextInput::make('neta')
                    ->label('Neta US$')
                    ->helperText('Monto anual que Integra reconoce como venta. Debe ser menor o igual al precio de venta.')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->prefix('$')
                    ->lte('sale_price')
                    ->validationMessages([
                        'lte' => 'La neta no puede ser mayor que el precio de venta.',
                    ]),
                Hidden::make('status')->default('ACTIVO'),
                Hidden::make('created_by')->default(fn (): ?string => Auth::user()?->name),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('MATRIZ DE NEGOCIACIÓN')
            ->description('Precio de venta y neta por plan, cobertura y rango de edad. El margen (venta − neta) se asigna solo a la agencia master.')
            ->emptyStateHeading('Sin tarifas pactadas')
            ->emptyStateDescription('Cargue la neta y el precio de venta para cada tarifa del catálogo que venda esta empresa aliada.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'fee.ageRange.plan',
                'fee.coverageRecord',
            ]))
            ->striped()
            ->columns([
                TextColumn::make('fee.ageRange.plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color('success')
                    ->placeholder('—')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('fee.coverage')
                    ->label('Cobertura')
                    ->placeholder('Sin cobertura')
                    ->formatStateUsing(fn (mixed $state): string => filled($state)
                        ? number_format((float) $state, 0, ',', '.').' UD$'
                        : 'Sin cobertura')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('fee.ageRange.range')
                    ->label('Rango de edad')
                    ->placeholder('—')
                    ->suffix(' años')
                    ->badge(),
                TextColumn::make('sale_price')
                    ->label('Precio de venta')
                    ->money('USD')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('neta')
                    ->label('Neta')
                    ->money('USD')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (?string $state): string => mb_strtoupper((string) $state) === 'ACTIVO' ? 'success' : 'danger'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar netas')
                    ->modalHeading('Cargar matriz de negociación')
                    ->modalDescription('Agregue una o varias tarifas del catálogo con precio de venta y neta. Puede cargar todo el plan de una sola vez.')
                    ->modalSubmitActionLabel('Guardar tarifas')
                    ->modalWidth(Width::SevenExtraLarge)
                    ->createAnother(false)
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('success')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                    ])
                    ->form([
                        Repeater::make('items')
                            ->label('Tarifas a pactar')
                            ->helperText('Una fila por plan, cobertura y rango de edad. Use “Añadir tarifa” o duplique una fila para cargar más.')
                            ->addActionLabel('Añadir tarifa')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->cloneable()
                            ->reorderable(false)
                            ->columnSpanFull()
                            ->compact()
                            ->table([
                                TableColumn::make('Tarifa del catálogo'),
                                TableColumn::make('Precio de venta US$'),
                                TableColumn::make('Neta US$'),
                            ])
                            ->schema([
                                $this->catalogFeeSelect(forRepeater: true),
                                TextInput::make('sale_price')
                                    ->label('Precio de venta US$')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->prefix('$')
                                    ->validationMessages([
                                        'required' => 'Debe indicar el precio de venta.',
                                    ]),
                                TextInput::make('neta')
                                    ->label('Neta US$')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->prefix('$')
                                    ->lte('sale_price')
                                    ->validationMessages([
                                        'required' => 'Debe indicar la neta.',
                                        'lte' => 'La neta no puede ser mayor que el precio de venta.',
                                    ]),
                            ]),
                    ])
                    ->using(function (array $data): WhiteCompanyFee {
                        $created = WhiteCompanyNegotiatedFeesBulkCreator::createForCompany(
                            $this->ownerCompany(),
                            is_array($data['items'] ?? null) ? $data['items'] : [],
                            Auth::user()?->name,
                        );

                        $first = $created[0] ?? null;

                        if (! $first instanceof WhiteCompanyFee) {
                            throw ValidationException::withMessages([
                                'items' => 'Debe agregar al menos una tarifa.',
                            ]);
                        }

                        return $first;
                    })
                    ->successNotification(function (): Notification {
                        return Notification::make()
                            ->success()
                            ->title('Tarifas pactadas')
                            ->body('Las tarifas se guardaron en la matriz de negociación.');
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->modalHeading('Editar neta pactada'),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    private function catalogFeeSelect(bool $forRepeater = false): Select
    {
        $select = Select::make('fee_id')
            ->label('Tarifa del catálogo')
            ->options(fn (): array => WhiteCompanyCatalogFeeOptions::forCompany(
                $this->ownerCompany(),
                $this->mountedNegotiatedFeeId(),
            ))
            ->getSearchResultsUsing(function (string $search): array {
                $fees = WhiteCompanyCatalogFeeOptions::catalogFees();
                $options = WhiteCompanyCatalogFeeOptions::forCompany(
                    $this->ownerCompany(),
                    $this->mountedNegotiatedFeeId(),
                );

                return WhiteCompanyCatalogFeeOptions::matching($options, $search, $fees);
            })
            ->getOptionLabelUsing(function (mixed $value): ?string {
                if (blank($value)) {
                    return null;
                }

                $options = WhiteCompanyCatalogFeeOptions::forCompany($this->ownerCompany(), (int) $value);

                return $options[(int) $value] ?? null;
            })
            ->searchable()
            ->preload()
            ->required()
            ->unique(
                table: 'white_company_fees',
                column: 'fee_id',
                ignoreRecord: true,
                modifyRuleUsing: fn (Unique $rule): Unique => $rule
                    ->where('white_company_id', $this->getOwnerRecord()->getKey()),
            )
            ->validationMessages([
                'required' => 'Debe seleccionar la tarifa del catálogo.',
                'unique' => 'Esta tarifa ya tiene neta pactada para la empresa aliada.',
                'distinct' => 'No puede repetir la misma tarifa del catálogo en esta carga.',
            ]);

        if (! $forRepeater) {
            $select->helperText('Plan, cobertura (si aplica) y rango de edad sobre los que se pacta venta y neta.');
        }

        if ($forRepeater) {
            $select
                ->distinct()
                ->disableOptionsWhenSelectedInSiblingRepeaterItems();
        }

        return $select;
    }

    private function mountedNegotiatedFeeId(): ?int
    {
        $record = $this->getMountedTableActionRecord();

        if (! $record instanceof WhiteCompanyFee) {
            return null;
        }

        return (int) $record->fee_id;
    }

    private function ownerCompany(): WhiteCompany
    {
        $owner = $this->getOwnerRecord();

        if (! $owner instanceof WhiteCompany) {
            throw ValidationException::withMessages([
                'items' => 'No se pudo identificar la empresa aliada.',
            ]);
        }

        return $owner;
    }
}
