<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\RrhhColaboradors\RelationManagers;

use App\Models\RrhhAsignacion;
use App\Models\RrhhColaborador;
use App\Support\Filament\FilamentIosButton;
use App\Support\Rrhh\RrhhColaboradorConceptoForm;
use App\Support\Rrhh\RrhhValorCalculo;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AsignacionesRelationManager extends RelationManager
{
    protected static string $relationship = 'asignaciones';

    protected static ?string $title = 'Asignaciones';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedPlusCircle;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(RrhhColaboradorConceptoForm::components(
                'Nombre de la asignación',
                'Describe el alcance de esta asignación para el colaborador.',
            ));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading('Asignaciones del colaborador')
            ->description('Un colaborador puede tener una o más asignaciones. Agregue o elimine según corresponda. Las del departamento se gestionan en Asignaciones RRHH.')
            ->emptyStateHeading('Sin asignaciones individuales')
            ->emptyStateDescription('Este colaborador aún no tiene asignaciones propias. Use «Agregar asignación» para registrar la primera.')
            ->emptyStateIcon(Heroicon::OutlinedPlusCircle)
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('tipo_valor')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        RrhhValorCalculo::TIPO_PORCENTAJE => 'warning',
                        RrhhValorCalculo::TIPO_MONTO => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => RrhhValorCalculo::tipoLabel($state)),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->state(fn (RrhhAsignacion $record): string => $record->valorLabel())
                    ->color('success'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar asignación')
                    ->icon('heroicon-m-plus')
                    ->modalHeading('Nueva asignación del colaborador')
                    ->modalSubmitActionLabel('Guardar')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                    ])
                    ->mutateFormDataUsing(fn (array $data): array => $this->mutateConceptoFormData($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-m-pencil-square')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                    ], merge: true)
                    ->mutateFormDataUsing(fn (array $data): array => $this->mutateConceptoFormData($data)),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->icon('heroicon-m-trash')
                    ->modalHeading('Eliminar asignación')
                    ->modalDescription('Esta asignación dejará de aplicarse a este colaborador. Esta acción no se puede deshacer.')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
                    ], merge: true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas')
                        ->extraAttributes([
                            'class' => FilamentIosButton::extraClassForFilamentColor('danger'),
                        ], merge: true),
                ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mutateConceptoFormData(array $data): array
    {
        /** @var RrhhColaborador $owner */
        $owner = $this->getOwnerRecord();

        $data['aplicacion'] = 'colaborador';
        $data['colaborador_id'] = $owner->getKey();
        $data['departamento_id'] = null;
        $data['cargo_id'] = null;
        $data['created_by'] = $data['created_by'] ?? (Auth::user()?->name ?? '');
        $data['updated_by'] = Auth::user()?->name ?? '';

        return $data;
    }
}
