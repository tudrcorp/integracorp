<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Tables;

use App\Models\OperationServiceOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use ZipArchive;

class OperationServiceOrdersTable
{
    private const IOS_SECTION_CLASS = 'fi-helpdesk-ios-section';

    private const IOS_SUCCESS_BTN = 'aviso-btn-ios-success shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_GRAY_BTN = 'ticket-btn-ios-gray shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /** @return array<string, string> */
    private static function paymentMethodOptions(): array
    {
        return [
            'ZELLE' => 'ZELLE',
            'TRANSFERENCIA US$' => 'TRANSFERENCIA(US$)',
            'EFECTIVO US$' => 'EFECTIVO US$',
            'MULTIPLE' => 'MULTIPLE',
            'PAGO MOVIL VES' => 'PAGO MOVIL(VES)',
            'TRANSFERENCIA VES' => 'TRANSFERENCIA(VES)',
        ];
    }

    /**
     * Misma lógica que exige el modal al guardar: método de pago, tasa BCV > 0 y al menos un monto.
     */
    private static function hasRegisteredPaymentData(OperationServiceOrder $record): bool
    {
        if (! filled($record->payment_method)) {
            return false;
        }

        $tasa = (float) ($record->tasa_bcv ?? 0);
        if ($tasa <= 0) {
            return false;
        }

        $usd = $record->total_amount_usd;
        $ves = $record->total_amount_ves;
        $hasUsd = $usd !== null && $usd !== '' && is_numeric($usd);
        $hasVes = $ves !== null && $ves !== '' && is_numeric($ves);

        return $hasUsd || $hasVes;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Órdenes de servicio')
            ->description('Listado de órdenes generadas por coordinación; el color de cada fila indica la prioridad asignada. Flujo: primero «Datos de pago»; al guardarlos podrás usar «Cargar soportes».')
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
                TextColumn::make('tasa_bcv')
                    ->label('Tasa BCV')
                    ->sortable()
                    ->toggleable()
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== '' ? (string) $state : '—'),
                TextColumn::make('total_amount_usd')
                    ->label('Total US$')
                    ->sortable()
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== ''
                        ? 'US$ '.number_format((float) $state, 2, ',', '.')
                        : '—'),
                TextColumn::make('total_amount_ves')
                    ->label('Total Bs.')
                    ->sortable()
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== ''
                        ? 'Bs. '.number_format((float) $state, 2, ',', '.')
                        : '—'),
                TextColumn::make('payment_method')
                    ->label('Método de pago')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable()
                    ->formatStateUsing(fn (?string $state): string => $state ? (self::paymentMethodOptions()[$state] ?? $state) : '—'),
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
                Action::make('registerPayment')
                    ->label('Datos de pago')
                    ->icon('heroicon-m-banknotes')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->modalIcon('heroicon-m-banknotes')
                    ->modalHeading('Registrar datos de pago')
                    ->modalDescription('Completa la tasa BCV, los montos y el método de pago para actualizar la orden. Usa el botón «Guardar» al finalizar: los totales en dólares y bolívares se sincronizan según la tasa (si indicas ambos montos, prevalece el total en US$).')
                    ->modalSubmitActionLabel('Guardar datos de pago')
                    ->modalSubmitAction(
                        fn (Action $action): Action => $action
                            ->extraAttributes([
                                'class' => self::IOS_SUCCESS_BTN,
                            ])
                    )
                    ->modalCancelAction(
                        fn (Action $action): Action => $action
                            ->label('Cancelar')
                            ->extraAttributes([
                                'class' => self::IOS_GRAY_BTN,
                            ])
                    )
                    ->fillForm(fn (OperationServiceOrder $record): array => [
                        'tasa_bcv' => $record->tasa_bcv,
                        'total_amount_usd' => $record->total_amount_usd,
                        'total_amount_ves' => $record->total_amount_ves,
                        'payment_method' => $record->payment_method,
                    ])
                    ->form([
                        Section::make('Información de pago')
                            ->description('Indica la tasa del día y al menos un monto (US$ o Bs.); el otro se calcula al guardar. El método de pago es obligatorio.')
                            ->icon('heroicon-m-currency-dollar')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 2])
                                    ->schema([
                                        TextInput::make('tasa_bcv')
                                            ->label('Tasa BCV')
                                            ->prefix('VES')
                                            ->placeholder('Ej. 36,50')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0.000001)
                                            ->helperText('Tipo de cambio oficial o acordado para esta orden.'),
                                        Select::make('payment_method')
                                            ->label('Método de pago')
                                            ->prefixIcon('heroicon-m-credit-card')
                                            ->options(self::paymentMethodOptions())
                                            ->required()
                                            ->native(false)
                                            ->searchable(),
                                        TextInput::make('total_amount_usd')
                                            ->label('Total en US$')
                                            ->prefix('US$')
                                            ->placeholder('0,00')
                                            ->numeric()
                                            ->helperText('Opcional si ya ingresaste el total en bolívares.'),
                                        TextInput::make('total_amount_ves')
                                            ->label('Total en bolívares')
                                            ->prefix('Bs.')
                                            ->placeholder('0,00')
                                            ->numeric()
                                            ->helperText('Opcional si ya ingresaste el total en US$.'),
                                    ]),
                            ])
                            ->columns(1)
                            ->columnSpanFull()
                            ->extraAttributes([
                                'class' => self::IOS_SECTION_CLASS,
                            ]),
                    ])
                    ->successNotification(null)
                    ->action(function (OperationServiceOrder $record, array $data): void {
                        $tasa = (float) ($data['tasa_bcv'] ?? 0);
                        if ($tasa <= 0) {
                            Notification::make()
                                ->title('Tasa inválida')
                                ->body('La tasa BCV debe ser mayor que cero.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $usdRaw = $data['total_amount_usd'] ?? null;
                        $vesRaw = $data['total_amount_ves'] ?? null;
                        $usd = ($usdRaw !== null && $usdRaw !== '') ? (float) $usdRaw : null;
                        $ves = ($vesRaw !== null && $vesRaw !== '') ? (float) $vesRaw : null;

                        if ($usd === null && $ves === null) {
                            Notification::make()
                                ->title('Montos requeridos')
                                ->body('Indica al menos el total en US$ o el total en bolívares.')
                                ->warning()
                                ->send();

                            return;
                        }

                        if ($usd !== null && $ves !== null) {
                            $ves = $usd * $tasa;
                        } elseif ($usd !== null) {
                            $ves = $usd * $tasa;
                        } else {
                            $usd = $ves / $tasa;
                        }

                        $record->update([
                            'tasa_bcv' => $tasa,
                            'total_amount_usd' => round($usd, 4),
                            'total_amount_ves' => round($ves, 4),
                            'payment_method' => (string) $data['payment_method'],
                            'updated_by' => Auth::user()?->name ?? 'sistema',
                        ]);

                        Notification::make()
                            ->title('Datos de pago guardados')
                            ->body('La orden #'.($record->order_number ?: $record->getKey()).' se actualizó correctamente.')
                            ->success()
                            ->send();
                    })
                    ->hidden(fn (OperationServiceOrder $record): bool => self::hasRegisteredPaymentData($record)),
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
                    ->hidden(fn (OperationServiceOrder $record): bool => $record->status === 'FINALIZADO'
                        || ! self::hasRegisteredPaymentData($record)),
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
