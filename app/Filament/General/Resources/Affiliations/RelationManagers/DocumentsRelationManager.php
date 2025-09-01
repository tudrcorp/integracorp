<?php

namespace App\Filament\General\Resources\Affiliations\RelationManagers;

use App\Models\Affiliate;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\Layout\Stack;
use App\Models\AffiliationIndividualDocument;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\General\Resources\Affiliations\AffiliationResource;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documentos';

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
                            ->hidden(fn() => AffiliationIndividualDocument::where('affiliation_id', $this->getOwnerRecord()->id)->where('title', 'FIRMA DIGITAL AGENTE')->where('documents', '!=', null)->exists()),
                        FileUpload::make('fir_dig_ti')
                            ->label('Firma Digitalizada Agencia Master')
                            ->uploadingMessage('Cargando firma...')
                            ->hidden(fn() => AffiliationIndividualDocument::where('affiliation_id', $this->getOwnerRecord()->id)->where('title', 'FIRMA DIGITAL TITULAR')->where('documents', '!=', null)->exists()),
                        FileUpload::make('documents')
                            ->label('Documentos')
                            ->helperText('Carga multiple de documentos. La cantidad de documentos debe ser igual al número de afiliados.')
                            ->uploadingMessage('Cargando documentos...')
                            ->multiple()
                            ->maxFiles(function () {
                                return Affiliate::where('affiliation_id', $this->getOwnerRecord()->id)->count();
                            }),
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
            ->recordActions([
                // ...
                Action::make('download')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('verde')
                    ->url(function ($record) {
                        return asset('storage/' . $record->documents);
                    })
                    ->button()
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar Documento')
                    ->icon('heroicon-o-plus')
                    ->color('verde')
                    ->modalHeading('Agregar Documento')
                    ->modalButton('Agregar')
                    ->action(function (array $data) {
                        try {
                            // dd($data);
                            $array_title = [

                                'fir_dig_agent'         => 'FIRMA DIGITAL AGENTE',
                                'fir_dig_ti'            => 'FIRMA DIGITAL TITULAR',
                                'documents'             => 'DOCUMENTO AFILIADO',
                            ];

                            foreach ($data as $key => $value) {
                                if ($value && $key != 'documents') {
                                    AffiliationIndividualDocument::create([
                                        'affiliation_id' => $this->getOwnerRecord()->id,
                                        'title' => $array_title[$key],
                                        'documents' => $value,
                                        'image' => 'folder2.png',
                                    ]);
                                }
                            }

                            if ($data['documents']) {
                                for ($i = 0; $i < count($data['documents']); $i++) {
                                    // dd($data['documents'][$i]);
                                    AffiliationIndividualDocument::create([
                                        'affiliation_id' => $this->getOwnerRecord()->id,
                                        'title' => 'DOCUMENTO AFILIADO',
                                        'documents' => $data['documents'][$i],
                                        'image' => 'folder2.png',
                                    ]);
                                }
                            }
                        } catch (\Throwable $th) {
                            dd($th);
                        }
                    })
            ])
            ->striped()
            ->defaultSort('created_at', 'desc');
    }
}