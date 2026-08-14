<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\WhiteCompanies\RelationManagers;

use App\Models\Fee;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;

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
                Select::make('fee_id')
                    ->label('Tarifa del catálogo')
                    ->helperText('Plan, cobertura (si aplica) y rango de edad sobre los que se pacta venta y neta.')
                    ->options(fn (): array => self::feeOptions())
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
                    ]),
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
                    ->label('Agregar neta')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('success')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar'),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function feeOptions(): array
    {
        return Fee::query()
            ->with(['ageRange.plan', 'coverageRecord'])
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (Fee $fee): array {
                $plan = $fee->ageRange?->plan?->description ?: 'Plan';
                $range = filled($fee->ageRange?->range) ? $fee->ageRange->range.' años' : 'sin rango';
                $coverage = filled($fee->coverage)
                    ? number_format((float) $fee->coverage, 0, ',', '.').' UD$'
                    : 'sin cobertura';

                return [
                    $fee->id => $plan.' · '.$coverage.' · '.$range,
                ];
            })
            ->all();
    }
}
