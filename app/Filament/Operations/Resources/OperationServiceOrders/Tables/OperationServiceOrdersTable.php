<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use ZipArchive;

class OperationServiceOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Órdenes de servicio')
            ->description('Listado de órdenes generadas por coordinación; el color de cada fila indica la prioridad asignada.')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['telemedicinePriority', 'supplier']))
            ->columns([
                TextColumn::make('order_number')
                    ->label('Nº orden')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->icon('heroicon-m-hashtag')
                    ->copyable()
                    ->copyMessage('Número copiado'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->color(fn (?string $state): string => match ($state) {
                        'EN GESTION', 'EN GESTIÓN' => 'primary',
                        'FINALIZADO' => 'success',
                        'PENDIENTE' => 'warning',
                        'CANCELADO' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('telemedicinePriority.name')
                    ->label('Prioridad')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->color(fn (?string $state): string => match ($state) {
                        'NO URGENTE' => 'no-urgente',
                        'ESTANDAR' => 'estandar',
                        'URGENCIA' => 'urgencia',
                        'EMERGENCIA' => 'emergencia',
                        'CRITICO' => 'critico',
                        default => 'gray',
                    }),
                TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->limit(28)
                    ->tooltip(fn ($record) => $record->supplier?->name),
                TextColumn::make('supplier_external')
                    ->label('Proveedor externo')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->description),
                TextColumn::make('service_type')
                    ->label('Tipo de servicio')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('currency')
                    ->label('Moneda')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextInputColumn::make('tasa_bcv')
                    ->label('Tasa BCV')
                    ->placeholder('0.00')
                    ->prefix('VES')
                    ->inputMode('decimal')
                    ->sortable(),
                TextInputColumn::make('total_amount_usd')
                    ->label('Total US$')
                    ->placeholder('0.00')
                    ->prefix('US$')
                    ->afterStateUpdated(function ($record, $state) {
                        $record->total_amount_ves = $state * $record->tasa_bcv;
                        $record->save();
                    })
                    ->sortable()
                    ->alignEnd(),
                TextInputColumn::make('total_amount_ves')
                    ->label('Total Bs.')
                    ->placeholder('0.00')
                    ->prefix('Bs. ')
                    ->inputMode('decimal')
                    ->afterStateUpdated(function ($record, $state) {
                        $record->total_amount_usd = $state / $record->tasa_bcv;
                        $record->save();
                    })
                    ->sortable()
                    ->alignEnd(),
                SelectColumn::make('payment_method')
                    ->label('Método de pago')
                    ->options([
                        'ZELLE' => 'ZELLE',
                        'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
                        'EFECTIVO US$' => 'EFECTIVO US$',
                        'MULTIPLE' => 'MULTIPLE',
                        'PAGO MOVIL VES' => 'PAGO MOVIL(VES)',
                        'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',
                    ])
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable(),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->description(fn ($record) => $record->created_at->diffForHumans())
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days'),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordClasses(function ($record): array {
                $name = $record->telemedicinePriority?->name;
                $classes = match ($name) {
                    'NO URGENTE' => 'bg-[#005ca9]/10 dark:bg-[#005ca9]/25 border-l-4 border-[#005ca9]',
                    'ESTANDAR' => 'bg-[#02976d]/10 dark:bg-[#02976d]/25 border-l-4 border-[#02976d]',
                    'URGENCIA' => 'bg-[#eab527]/10 dark:bg-[#eab527]/25 border-l-4 border-[#eab527]',
                    'EMERGENCIA' => 'bg-[#f17f29]/10 dark:bg-[#f17f29]/25 border-l-4 border-[#f17f29]',
                    'CRITICO' => 'bg-[#e4003b]/10 dark:bg-[#e4003b]/25 border-l-4 border-[#e4003b]',
                    default => 'border-l-4 border-gray-200 bg-gray-50/50 dark:border-gray-600 dark:bg-gray-950/20',
                };

                return [$classes];
            })
            ->filters([
                //
            ])
            ->recordActions([
                // ViewAction::make(),
                // EditAction::make(),
                Action::make('upload_files')
                    ->label('Cargar Soportes')
                    ->icon('heroicon-m-cloud-arrow-up')
                    ->color('warning')
                    ->button()
                    ->extraAttributes([
                        'x-on:click.stop' => '',
                        'class' => 'rounded-full border-b-2 border-warning-600 dark:border-warning-500 bg-warning-500/15 dark:bg-warning-500/25 text-warning-700 dark:text-warning-300 font-semibold shadow-sm hover:bg-warning-500/25 dark:hover:bg-warning-500/35',
                    ])
                    ->modalHeading('Cargar Soportes')
                    ->modalDescription('Cargue los soportes de la orden de servicio')
                    ->modalSubmitActionLabel('Cargar')
                    ->modalCancelActionLabel('Cancelar')
                    ->modalIcon('heroicon-m-cloud-arrow-up')
                    ->form([
                        FileUpload::make('files')
                            ->label('Soportes')
                            ->disk('public')
                            ->directory('operation-service-orders-files')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->maxSize(2048)
                            ->helperText('Formatos: JPG, PNG, WebP o PDF. Máximo 2 MB.')
                            ->multiple()
                            ->required()
                            ->validationMessages([
                                'required' => 'El campo es requerido',
                            ]),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'files' => $data['files'],
                            'updated_by' => Auth::user()->name,
                            'status' => 'FINALIZADO',
                        ]);
                        Notification::make()
                            ->title('¡TAREA COMPLETADA!')
                            ->body('Los soportes han sido cargados correctamente.')
                            ->success()
                            ->send();
                    })
                    ->hidden(fn ($record) => $record->status === 'FINALIZADO'),
                Action::make('preview_files')
                    ->label('Vista previa')
                    ->icon('heroicon-m-eye')
                    ->color('success')
                    ->button()
                    ->extraAttributes([
                        'x-on:click.stop' => '',
                        'class' => 'rounded-full border-b-2 border-success-600 dark:border-success-500 bg-success-500/15 dark:bg-success-500/25 text-success-700 dark:text-success-300 font-semibold shadow-sm hover:bg-success-500/25 dark:hover:bg-success-500/35',
                    ])
                    ->modalHeading('Vista previa de soportes')
                    ->modalDescription('Previsualiza los archivos cargados y descárgalos individualmente.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalIcon('heroicon-m-eye')
                    ->form(fn ($record): array => [
                        Section::make('Soportes cargados')
                            ->schema([
                                Placeholder::make('files_preview')
                                    ->label('')
                                    ->content(fn () => self::renderFilesPreview($record, self::buildDownloadAllUrl($record))),
                            ]),
                    ])
                    ->hidden(fn ($record) => empty($record->files)),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function buildDownloadAllUrl($record): ?string
    {
        $files = is_array($record->files) ? $record->files : [];

        if ($files === []) {
            return null;
        }

        $disk = Storage::disk('public');
        $zipFileName = 'os-'.($record->order_number ?: $record->id).'-soportes-'.now()->format('YmdHis').'.zip';
        $zipRelativePath = 'operation-service-orders-files/zips/'.$zipFileName;
        $zipAbsolutePath = $disk->path($zipRelativePath);
        $zipDirectory = dirname($zipAbsolutePath);

        if (! is_dir($zipDirectory)) {
            mkdir($zipDirectory, 0755, true);
        }

        if (file_exists($zipAbsolutePath)) {
            @unlink($zipAbsolutePath);
        }

        $zip = new ZipArchive;

        if ($zip->open($zipAbsolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        foreach ($files as $file) {
            if (! is_string($file) || $file === '' || ! $disk->exists($file)) {
                continue;
            }

            $zip->addFile($disk->path($file), basename($file));
        }

        $zip->close();

        return URL::to(Storage::url($zipRelativePath));
    }

    private static function renderFilesPreview($record, ?string $downloadAllUrl = null): HtmlString
    {
        $files = is_array($record->files) ? $record->files : [];

        if ($files === []) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">No hay soportes cargados.</p>');
        }

        $cards = array_map(static function (string $file): string {
            $url = URL::to(Storage::url($file));
            $name = basename($file);
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            $preview = '<div class="rounded-2xl border border-gray-200/80 dark:border-gray-700 bg-white/70 dark:bg-gray-900/50 p-3 text-sm text-gray-500 dark:text-gray-400">Sin previsualización disponible</div>';

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $preview = '<img src="'.e($url).'" alt="'.e($name).'" class="w-full rounded-2xl border border-gray-200/80 dark:border-gray-700 object-cover max-h-72" loading="lazy">';
            } elseif ($extension === 'pdf') {
                $preview = '<iframe src="'.e($url).'#toolbar=0&navpanes=0" class="w-full h-72 rounded-2xl border border-gray-200/80 dark:border-gray-700 bg-white" title="'.e($name).'"></iframe>';
            }

            return '<div class="rounded-3xl border border-gray-200/70 dark:border-gray-700/70 bg-white/80 dark:bg-gray-900/60 p-4 shadow-sm">'.
                '<div class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200 truncate">'.e($name).'</div>'.
                '<div class="mb-3">'.$preview.'</div>'.
                '<div class="flex justify-end">'.
                '<a href="'.e($url).'" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-full border-b-2 border-primary-600 dark:border-primary-500 bg-primary-500/15 dark:bg-primary-500/25 text-primary-700 dark:text-primary-300 no-underline">'.
                '<svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>'.
                'Descargar</a>'.
                '</div>'.
                '</div>';
        }, $files);

        $downloadAllButton = filled($downloadAllUrl)
            ? '<div class="mb-4 flex justify-end">'.
                '<a href="'.e($downloadAllUrl).'" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-full border-b-2 border-info-600 dark:border-info-500 bg-info-500/15 dark:bg-info-500/25 text-info-700 dark:text-info-300 no-underline">'.
                '<svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5v9A2.25 2.25 0 0 1 18.75 18.75H5.25A2.25 2.25 0 0 1 3 16.5v-9m18 0-2.25-2.25M21 7.5l-2.25 2.25M3 7.5 5.25 5.25M3 7.5l2.25 2.25M9 11.25h6m-6 3h6" /></svg>'.
                'Descargar todos</a>'.
                '</div>'
            : '';

        return new HtmlString($downloadAllButton.'<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">'.implode('', $cards).'</div>');
    }
}
