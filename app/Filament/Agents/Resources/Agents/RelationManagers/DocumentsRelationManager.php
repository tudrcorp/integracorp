<?php

namespace App\Filament\Agents\Resources\Agents\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\AgentDocument;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\Layout\Stack;
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
                Section::make('CARGA DE DOCUMENTOS')
                    ->collapsed()
                    ->description('El tamaño máximo de los documentos es de 2MB. Acepta .jpg, .jpeg, .pdf, .txt, .xls, .xlsx')
                    ->icon('heroicon-m-folder-plus')
                    ->schema([
                        FileUpload::make('fir_dig_agent')
                            ->label('Firma Digitalizada del Agente')
                            ->uploadingMessage('Cargando firma...')
                            ->hidden(fn() => AgentDocument::where('agent_id', $this->getOwnerRecord()->id)->where('title', 'FIRMA DIGITAL AGENTE')->where('document', '!=', null)->exists()),
                        FileUpload::make('fir_dig_agency')
                            ->label('Firma Digitalizada Agencia Master')
                            ->uploadingMessage('Cargando firma...')
                            ->hidden(fn() => AgentDocument::where('agent_id', $this->getOwnerRecord()->id)->where('title', 'FIRMA DIGITAL AGENCIA')->where('document', '!=', null)->exists()),
                        FileUpload::make('file_ci_rif')
                            ->label('CI/RIF')
                            ->uploadingMessage('Cargando documento...')
                            ->hidden(fn() => AgentDocument::where('agent_id', $this->getOwnerRecord()->id)->where('title', 'DOCUMENTO DE IDENTIDAD CI/RIF')->where('document', '!=', null)->exists()),
                        FileUpload::make('file_w8_w9')
                            ->label('W8/W9')
                            ->uploadingMessage('Cargando documento...')
                            ->hidden(fn() => AgentDocument::where('agent_id', $this->getOwnerRecord()->id)->where('title', 'W8/W9')->where('document', '!=', null)->exists()),
                        FileUpload::make('file_account_usd')
                            ->label('Cta. US$')
                            ->uploadingMessage('Cargando documento...')
                            ->hidden(fn() => AgentDocument::where('agent_id', $this->getOwnerRecord()->id)->where('title', 'CUENTA USD')->where('document', '!=', null)->exists()),
                        FileUpload::make('file_account_bsd')
                            ->label('Cta.VES(Bs.) ')
                            ->uploadingMessage('Cargando documento...')
                            ->hidden(fn() => AgentDocument::where('agent_id', $this->getOwnerRecord()->id)->where('title', 'CUENTA VES')->where('document', '!=', null)->exists()),
                        FileUpload::make('file_account_zelle')
                            ->label('Cta. Zelle')
                            ->uploadingMessage('Cargando documento...')
                            ->hidden(fn() => AgentDocument::where('agent_id', $this->getOwnerRecord()->id)->where('title', 'CUENTA ZELLE')->where('document', '!=', null)->exists()),
                    ])->columnSpanFull()->columns(2),
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

                            'fir_dig_agent'         => 'FIRMA DIGITAL AGENTE',
                            'fir_dig_agency'        => 'FIRMA DIGITAL AGENCIA',
                            'file_ci_rif'           => 'DOCUMENTO DE IDENTIDAD CI/RIF',
                            'file_w8_w9'            => 'W8/W9',
                            'file_account_usd'      => 'CUENTA USD',
                            'file_account_bsd'      => 'CUENTA VES',
                            'file_account_zelle'    => 'CUENTA ZELLE',
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
                        return asset('storage/' . $record->document);
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