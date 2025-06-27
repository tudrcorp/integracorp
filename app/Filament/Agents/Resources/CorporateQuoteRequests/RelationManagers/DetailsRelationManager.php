<?php

namespace App\Filament\Agents\Resources\CorporateQuoteRequests\RelationManagers;

use Filament\Forms;
use App\Models\Plan;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use App\Jobs\SendNotificacionAdministrador;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Agents\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use BackedEnum;

class DetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'details';

    protected static ?string $title = 'PLANES A COTIZAR';

    protected static string|BackedEnum|null $icon = 'heroicon-s-squares-plus';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('PLANES')
                    ->icon('heroicon-s-squares-plus')
                    ->description('Interactividad de seleccion de planes')
                    ->schema([
                        Repeater::make('details_corporate_quote_requests')
                            ->label('Planes a cotizar:')
                            // ->headers([
                            //     Header::make('Planes'),
                            //     Header::make('Cantidad de personas'),
                            // ])
                            // ->renderHeader(false)
                            // // ->showLabels()
                            // ->stackAt(MaxWidth::ExtraSmall)
                            // ->reorderable(false)
                            // // ->relationship('detailCoporateQuotes')
                            ->schema([
                                Forms\Components\Select::make('plan_id')
                                    ->options(function () {
                                        $planesConBeneficios = Plan::join('benefit_plans', 'plans.id', '=', 'benefit_plans.plan_id')
                                            ->select('plans.id as plan_id', 'plans.description as description')
                                            ->distinct() // Asegurarse de que no haya duplicados
                                            ->get()
                                            ->pluck('description', 'plan_id');

                                        return $planesConBeneficios;
                                    })
                                    ->label('Plan')
                                    ->preload()
                                    ->searchable()
                                    ->live()
                                    ->placeholder('Seleccione un plan'),
                                TextInput::make('total_persons')
                                    ->label('Nro. de personas')
                                    ->placeholder('Nro. de personas ')
                                    ->numeric(),
                            ])
                            ->columns(2)

                    ])->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
        ->heading('DETALLES DE LA SOLICITUD')
            ->description('Los planes asociados a la solicitud de cotización. Si desea agregar otro plan haz click en el botón "Agregar Plan"')
            ->recordTitleAttribute('corporate_quote_request_id')
            ->columns([
                TextColumn::make('plan.description'),
                TextColumn::make('total_persons')
                    ->label('Nro. de personas')
                    ->numeric(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color('warning'),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar Plan')
                    ->icon('heroicon-s-plus')
                    ->modalHeading(false)
                    ->modalButton('Agregar plan(es)')
                    ->createAnother(false)

                    ->action(function (array $data) {

                        $array = $data['details_corporate_quote_requests'];

                        /**Inicializamos la variable en false para determinar cuando estan enviando planes duplicados */
                        $exists = false;

                        for ($i = 0; $i < count($array); $i++) {
                            if ($this->getOwnerRecord()->details()->where('plan_id', $array[$i]['plan_id'])->exists()) {
                                Notification::make()
                                    ->title('Plan ya existente en la solicitud')
                                    ->warning()
                                    ->send();
                                return;
                            }
                            $this->getOwnerRecord()->details()->create([
                                'plan_id' => $array[$i]['plan_id'],
                                'total_persons' => $array[$i]['total_persons'],
                                'status' => 'PRE-APROBADA'
                            ]);
                        }

                        Notification::make()
                            ->title('Plan Agregado de forma exitosa')
                            ->success()
                            ->send();

                        /**Notificacion al dashboard del administrador */
                        $title = 'NOTIFICACION';
                        $body = 'La solicitud actualizada Nro.:' . $this->getOwnerRecord()->code;
                        SendNotificacionAdministrador::dispatch($title, $body);

                        /**Notificacion por whatsapp */
                    })
                ]);
    }
}