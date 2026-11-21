<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\FarmadocOrders\Actions;

use App\Filament\Operations\Resources\FarmadocOrders\FarmadocOrderResource;
use App\Filament\Operations\Resources\OperationInventories\OperationInventoryResource;
use App\Models\FarmadocOrder;
use App\Models\User;
use App\Support\Farmadoc\FarmadocInventoryReceipt;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * «Dar entrada a inventario»: una pantalla con una fila por medicamento. Las coincidencias
 * exactas ya vienen resueltas; en el resto el analista elige el producto de su inventario
 * (sugeridos primero) o crea uno nuevo, y ajusta la cantidad si llegó menos.
 */
final class ReceiveFarmadocOrderAction
{
    /** @var array<int, list<array<string, mixed>>> plan por pedido, calculado una vez por petición */
    private static array $plans = [];

    public static function make(): Action
    {
        return Action::make('receiveFarmadocOrderIntoInventory')
            ->label('Dar entrada a inventario')
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->color('success')
            ->visible(fn (FarmadocOrder $record): bool => $record->canBeReceived() && self::userCanReceive())
            ->authorize(fn (): bool => self::userCanReceive())
            ->modalHeading(fn (FarmadocOrder $record): string => 'Dar entrada al pedido '.$record->order_number)
            ->modalDescription(fn (FarmadocOrder $record): Htmlable => self::summary($record))
            ->modalIcon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->modalWidth(Width::SevenExtraLarge)
            ->closeModalByClickingAway(false)
            ->fillForm(fn (FarmadocOrder $record): array => [
                'ubication_id' => self::defaultUbication($record),
                'lines' => array_map(fn (array $line): array => [
                    'key' => $line['key'],
                    'target' => null,
                    'quantity' => $line['quantity'],
                ], self::plan($record)),
                'notes' => null,
            ])
            ->schema(fn (FarmadocOrder $record): array => [
                ToggleButtons::make('ubication_id')
                    ->label('¿A qué ubicación entra el pedido?')
                    ->options(FarmadocInventoryReceipt::ubicationOptions())
                    ->helperText(fn (): ?string => $record->operation_inventory_ubication_id !== null
                        ? 'Viene marcada la ubicación que hizo el pedido.'
                        : null)
                    ->inline()
                    ->required()
                    ->validationMessages(['required' => 'Elija la ubicación que recibe el pedido.']),
                Repeater::make('lines')
                    ->label('Medicamentos')
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->itemLabel(fn (array $state): ?string => self::line($record, $state['key'] ?? null)['name'] ?? null)
                    ->schema([
                        Hidden::make('key'),
                        Grid::make(12)->schema([
                            Text::make(fn (Get $get): Htmlable => self::lineDetails(self::line($record, $get('key'))))
                                ->columnSpan(['default' => 12, 'lg' => 5]),
                            Text::make(fn (Get $get): Htmlable => self::exactMatch(self::line($record, $get('key'))))
                                ->visible(fn (Get $get): bool => (self::line($record, $get('key'))['exact_product_id'] ?? null) !== null)
                                ->columnSpan(['default' => 12, 'lg' => 5]),
                            Select::make('target')
                                ->label('Producto de su inventario')
                                ->placeholder('Elija el producto que corresponde')
                                ->options(fn (Get $get): array => [
                                    ...self::line($record, $get('key'))['suggestions'] ?? [],
                                    FarmadocInventoryReceipt::TARGET_NEW => FarmadocInventoryReceipt::newProductLabel(),
                                ])
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => [
                                    ...FarmadocInventoryReceipt::search($search),
                                    FarmadocInventoryReceipt::TARGET_NEW => FarmadocInventoryReceipt::newProductLabel(),
                                ])
                                ->getOptionLabelUsing(fn (?string $value): ?string => FarmadocInventoryReceipt::targetLabel($value))
                                ->searchPrompt('Escriba el nombre o el código del producto')
                                ->noSearchResultsMessage('Ningún producto coincide. Puede crear uno nuevo.')
                                ->helperText(fn (Get $get): string => self::targetHelp(self::line($record, $get('key')), $get('target')))
                                ->visible(fn (Get $get): bool => (self::line($record, $get('key'))['exact_product_id'] ?? null) === null)
                                ->required(fn (Get $get): bool => (int) $get('quantity') > 0)
                                ->validationMessages(['required' => 'Elija el producto de su inventario o cree uno nuevo.'])
                                ->live()
                                ->columnSpan(['default' => 12, 'lg' => 5]),
                            TextInput::make('quantity')
                                ->label('Recibido')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(fn (Get $get): int => (int) (self::line($record, $get('key'))['quantity'] ?? 0))
                                ->suffix(fn (Get $get): string => 'de '.(int) (self::line($record, $get('key'))['quantity'] ?? 0))
                                ->helperText('0 si no llegó.')
                                ->required()
                                ->live(onBlur: true)
                                ->columnSpan(['default' => 12, 'lg' => 2]),
                        ]),
                    ])
                    ->columnSpanFull(),
                Section::make()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Observaciones de la recepción (opcional)')
                            ->placeholder('Por ejemplo: llegó una caja dañada, faltó una unidad, lote corto…')
                            ->rows(2)
                            ->maxLength(1000),
                    ])
                    ->columnSpanFull(),
            ])
            ->modalSubmitActionLabel('Registrar en inventario')
            ->action(function (array $data, FarmadocOrder $record, Action $action): void {
                $user = Auth::user();

                if (! $user instanceof User) {
                    $action->halt();

                    return;
                }

                $choices = [];

                foreach ((array) ($data['lines'] ?? []) as $line) {
                    if (is_array($line) && is_string($line['key'] ?? null)) {
                        $choices[$line['key']] = ['target' => $line['target'] ?? null, 'quantity' => $line['quantity'] ?? null];
                    }
                }

                try {
                    $result = FarmadocInventoryReceipt::register($record, (int) ($data['ubication_id'] ?? 0), $choices, $user, $data['notes'] ?? null);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('No se registró la entrada')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    $action->halt();

                    return;
                }

                self::$plans = [];

                $notification = Notification::make()
                    ->title('Pedido '.$record->order_number.' registrado en inventario')
                    ->body(self::resultBody($result))
                    ->success()
                    ->persistent();

                if (FarmadocOrderActions::receiptNoteAvailable($record->refresh())) {
                    $notification->actions([
                        Action::make('verNotaRecibido')
                            ->label('Ver nota de recibido')
                            ->url(FarmadocOrderActions::receiptNoteUrl($record), shouldOpenInNewTab: true),
                    ]);
                }

                $notification->send();
            });
    }

    public static function userCanReceive(): bool
    {
        return FarmadocOrderResource::canAccess() && OperationInventoryResource::canAccess();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function plan(FarmadocOrder $record): array
    {
        return self::$plans[(int) $record->getKey()] ??= FarmadocInventoryReceipt::plan($record);
    }

    /**
     * @return array<string, mixed>
     */
    private static function line(FarmadocOrder $record, mixed $key): array
    {
        foreach (self::plan($record) as $line) {
            if ($line['key'] === $key) {
                return $line;
            }
        }

        return [];
    }

    private static function defaultUbication(FarmadocOrder $record): ?int
    {
        $options = FarmadocInventoryReceipt::ubicationOptions();

        if ($record->operation_inventory_ubication_id !== null && isset($options[$record->operation_inventory_ubication_id])) {
            return (int) $record->operation_inventory_ubication_id;
        }

        return count($options) === 1 ? (int) array_key_first($options) : null;
    }

    private static function summary(FarmadocOrder $record): Htmlable
    {
        $plan = self::plan($record);
        $exact = count(array_filter($plan, fn (array $line): bool => $line['exact_product_id'] !== null));
        $review = count($plan) - $exact;

        $parts = [count($plan).' '.(count($plan) === 1 ? 'medicamento' : 'medicamentos')];

        if ($exact > 0) {
            $parts[] = '<strong>'.$exact.'</strong> '.($exact === 1 ? 'coincide' : 'coinciden').' por código (solo se suma existencia)';
        }

        if ($review > 0) {
            $parts[] = '<strong>'.$review.'</strong> por asignar a un producto de su inventario';
        }

        return new HtmlString(implode(' · ', $parts).'. Los productos que elija se actualizan con los datos de Farmadoc (nombre, código de barras, principio activo, concentración y laboratorio).');
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function lineDetails(array $line): Htmlable
    {
        if ($line === []) {
            return new HtmlString('');
        }

        $details = array_filter([
            $line['barcode'] ? 'Código '.e($line['barcode']) : null,
            $line['active_ingredient'] ? 'Principio activo: '.e($line['active_ingredient']) : null,
            $line['concentration'] ? 'Concentración: '.e($line['concentration']) : null,
            $line['laboratory'] ? 'Laboratorio: '.e($line['laboratory']) : null,
            $line['sku'] ? 'SKU '.e($line['sku']) : null,
        ]);

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:2px;">'
            .'<span style="font-weight:600;">'.e($line['name']).'</span>'
            .'<span style="font-size:0.8rem;opacity:0.75;">'.implode(' · ', $details).'</span>'
            .'<span style="font-size:0.8rem;opacity:0.75;">Pedido: '.(int) $line['quantity'].'</span>'
            .'</div>'
        );
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function exactMatch(array $line): Htmlable
    {
        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:2px;padding:0.5rem 0.75rem;border-radius:0.5rem;background:rgba(34,197,94,0.12);">'
            .'<span style="font-weight:600;color:#15803d;">✓ Coincidencia exacta por código</span>'
            .'<span style="font-size:0.8rem;">'.e((string) ($line['exact_label'] ?? '')).'</span>'
            .'<span style="font-size:0.75rem;opacity:0.75;">Solo se suma la existencia; sus datos no cambian.</span>'
            .'</div>'
        );
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function targetHelp(array $line, mixed $target): string
    {
        if ($target === FarmadocInventoryReceipt::TARGET_NEW) {
            return 'Se crea en el inventario con los datos de Farmadoc.';
        }

        if (FarmadocInventoryReceipt::productIdFromTarget($target) !== null) {
            return 'Este producto se actualiza con los datos de Farmadoc y se le suma la existencia.';
        }

        $count = count($line['suggestions'] ?? []);

        return $count > 0
            ? $count.' '.($count === 1 ? 'producto parecido' : 'productos parecidos').' arriba de la lista. Si no es ninguno, búsquelo o cree uno nuevo.'
            : 'No hay productos parecidos: búsquelo por nombre o código, o cree uno nuevo.';
    }

    /**
     * @param  array{receipt_number: string, units: int, lines: int, created: int, updated: int, exact: int}  $result
     */
    private static function resultBody(array $result): string
    {
        $parts = [
            'Nota de recibido '.$result['receipt_number'],
            $result['units'].' '.($result['units'] === 1 ? 'unidad' : 'unidades').' en '.$result['lines'].' '.($result['lines'] === 1 ? 'producto' : 'productos'),
        ];

        $detail = array_filter([
            $result['exact'] > 0 ? $result['exact'].' por código' : null,
            $result['updated'] > 0 ? $result['updated'].' actualizados' : null,
            $result['created'] > 0 ? $result['created'].' nuevos' : null,
        ]);

        if ($detail !== []) {
            $parts[] = implode(', ', $detail);
        }

        return implode(' · ', $parts).'.';
    }
}
