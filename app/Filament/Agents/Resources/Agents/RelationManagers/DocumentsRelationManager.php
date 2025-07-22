<?php

namespace App\Filament\Agents\Resources\Agents\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\AgentDocument;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Schemas\Components\Utilities\Get;
use App\Filament\Agents\Resources\Agents\AgentResource;
use Filament\Resources\RelationManagers\RelationManager;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'DOCUMENTOS ASOCIADOS';

    protected static string|BackedEnum|null $icon = 'heroicon-s-document';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fieldset::make('Documentos')
                    ->schema([
                        Grid::make()
                            ->schema([
                                FileUpload::make('doc_digital_signature')
                                    ->label('Firma Digital')
                                    ->uploadingMessage('Cargando documento, por favor espere...')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Documento Requerido',
                                    ])
                                    ->hidden(fn() => AgentDocument::where('agent_id', $this->getOwnerRecord()->id)->where('title', 'FIRMA DIGITAL')->where('document', '!=', null)->exists()),
                                FileUpload::make('doc_document_identity')
                                    ->label('Docuemnto de Identidad')
                                    ->uploadingMessage('Cargando documento, por favor espere...')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Documento Requerido',
                                    ])
                                    ->hidden(fn() => AgentDocument::where('agent_id', $this->getOwnerRecord()->id)->where('title', 'DOCUMENTO DE IDENTIDAD')->where('document', '!=', null)->exists()),
                                FileUpload::make('doc_w8_w9')
                                    ->label('W8/W9')
                                    ->uploadingMessage('Cargando documento, por favor espere...')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Docuemnto Requerido',
                                    ])
                                    ->hidden(fn() => AgentDocument::where('agent_id', $this->getOwnerRecord()->id)->where('title', 'W8/W9')->where('document', '!=', null)->exists()),
                            ])->columnSpanFull()->columns(3),
                        Grid::make()
                            ->schema([
                                FileUpload::make('doc_bank_data_ves')
                                    ->label('Soporte datos bancanarios(VES)')
                                    ->uploadingMessage('Cargando documento, por favor espere...')
                                    ->hidden(fn() => AgentDocument::where('agent_id', $this->getOwnerRecord()->id)->where('title', 'CUENTA VES')->where('document', '!=', null)->exists()),
                                FileUpload::make('doc_bank_data_usd')
                                    ->label('Soporte datos bancanarios(US$)')
                                    ->uploadingMessage('Cargando documento, por favor espere...')
                                    ->hidden(fn() => AgentDocument::where('agent_id', $this->getOwnerRecord()->id)->where('title', 'CUENTA US$')->where('document', '!=', null)->exists()),
                            ])->columnSpanFull()->columns(2),
                    ])->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Documentos consignados de la agente')
            ->columns([
                Stack::make([
                    ImageColumn::make('image')
                        ->height('auto')
                        ->width('30%'),
                    Stack::make([
                        TextColumn::make('title')
                            ->weight(FontWeight::Bold),
                    ]),
                ])->space(3),
            ])
            ->filters([
                //
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 4,
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar Documento')
                    ->icon('heroicon-o-plus')
                    ->color('verde')
                    ->modalHeading('Agregar Documento')
                    ->modalButton('Agregar')
                    ->action(function (array $data) {
                        $array_title = [
                            'doc_digital_signature' => 'FIRMA DIGITAL AGENTE',
                            'doc_document_identity' => 'DOCUMENTO DE IDENTIDAD',
                            'doc_w8_w9'             => 'W8/W9',
                            'doc_bank_data_ves'     => 'CUENTA USD',
                            'doc_bank_data_usd'     => 'CUENTA VES',
                        ];

                        foreach ($data as $key => $value) {
                            if ($value) {
                                AgentDocument::create([
                                    'agent_id' => $this->getOwnerRecord()->id,
                                    'title' => $array_title[$key],
                                    'document' => $value,
                                    'image' => 'folder2.png',
                                ]);
                            }
                        }
                    })

            ])
            ->recordActions([
                Action::make('download')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('verde')
                    ->url(function ($record) {
                        return asset('storage/agents/documents/' . $record->document);
                    })  
                    ->button()
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->delete();
                    })
            ]);
    }
}