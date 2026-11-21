<?php

declare(strict_types=1);

namespace App\Support\Farmadoc;

use App\Filament\Operations\Resources\FarmadocOrders\Actions\FarmadocOrderActions;
use App\Filament\Operations\Resources\FarmadocOrders\FarmadocOrderResource;
use App\Filament\Operations\Resources\FarmadocOrders\Pages\ViewFarmadocOrder;
use App\Models\FarmadocOrder;
use App\Models\FarmadocOrderAcceptanceDismissal;
use App\Models\User;
use App\Support\Filament\UserNavigationAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso a los analistas TDG de Operaciones y a los superadmin cuando Farmadoc acepta un pedido.
 *
 * - Nace cuando la consulta a Farmadoc trae la aceptación (fecha `accepted_at`).
 * - «Entendido» lo cierra solo para ese analista y lo deja en su campanita.
 * - Un analista que entra tarde ve las aceptaciones de las últimas 24 horas que no cerró.
 * - Solo cantidades y estado por medicamento: nunca montos.
 */
final class FarmadocAcceptanceAlerts
{
    public const WINDOW_HOURS = 24;

    public const MAX_PENDING = 20;

    public const INTERNAL_EMAIL_DOMAIN = '@tudrencasa.com';

    private static bool $tablesExist = false;

    /**
     * Analistas TDG de Operaciones (correo interno, sin proveedor, fuera de Atención Médica) o superadmin.
     */
    public static function userCanReceive(?User $user): bool
    {
        if ($user === null || $user->supplier_id !== null || $user->isProveedorAmd()) {
            return false;
        }

        if (! str_ends_with(mb_strtolower(trim((string) $user->email)), self::INTERNAL_EMAIL_DOMAIN)) {
            return false;
        }

        if (UserNavigationAccess::isSuperAdmin($user)) {
            return self::tablesExist();
        }

        if (UserNavigationAccess::userHasModule($user, 'ATENMEDI') || ! UserNavigationAccess::userHasModule($user, 'OPERACIONES')) {
            return false;
        }

        return self::tablesExist();
    }

    /**
     * Aceptaciones que el analista debe ver ahora, de la más antigua a la más reciente. Solo lee.
     *
     * @return list<array{
     *     id: int,
     *     order_number: string,
     *     service_order_number: string|null,
     *     coordination_reference: string|null,
     *     patient_name: string|null,
     *     delivery_note_number: string|null,
     *     accepted_by: string|null,
     *     accepted_at: string,
     *     accepted_count: int,
     *     rejected_count: int,
     *     items: list<array{name: string, barcode: string|null, quantity: int, accepted: bool, rejection_note: string|null}>,
     *     order_url: string|null,
     *     delivery_note_url: string|null
     * }>
     */
    public static function pendingFor(User $user): array
    {
        if (! self::userCanReceive($user)) {
            return [];
        }

        $orders = FarmadocOrder::query()
            ->whereNotNull('accepted_at')
            ->where('accepted_at', '>=', now()->subHours(self::WINDOW_HOURS))
            ->whereDoesntHave('acceptanceDismissals', fn ($dismissals) => $dismissals->where('user_id', $user->getKey()))
            ->orderBy('accepted_at')
            ->orderBy('id')
            ->limit(self::MAX_PENDING)
            ->get();

        if ($orders->isEmpty()) {
            return [];
        }

        $canOpenOrders = self::userCanOpenOrders($user);

        return $orders
            ->map(fn (FarmadocOrder $order): array => self::present($order, $canOpenOrders))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(FarmadocOrder $order, bool $canOpenOrders): array
    {
        $items = self::items($order);
        $accepted = count(array_filter($items, fn (array $item): bool => $item['accepted']));

        return [
            'id' => (int) $order->getKey(),
            'order_number' => (string) $order->order_number,
            'service_order_number' => self::text($order->service_order_number),
            'coordination_reference' => self::text($order->coordination_reference) ?? FarmadocOrder::DEFAULT_ORIGIN_LABEL,
            'patient_name' => self::text($order->patient_name),
            'delivery_note_number' => self::text($order->delivery_note_number),
            'accepted_by' => self::text($order->accepted_by),
            'accepted_at' => $order->accepted_at?->format('d/m/Y h:i a') ?? '',
            'accepted_count' => $accepted,
            'rejected_count' => count($items) - $accepted,
            'items' => $items,
            'order_url' => $canOpenOrders ? ViewFarmadocOrder::getUrl(['record' => $order], panel: 'operations') : null,
            'delivery_note_url' => $canOpenOrders && FarmadocOrderActions::deliveryNoteAvailable($order)
                ? FarmadocOrderActions::deliveryNoteUrl($order)
                : null,
        ];
    }

    /**
     * «Entendido»: cierra el aviso solo para este analista y lo deja en su campanita.
     */
    public static function dismiss(int $orderId, User $user): void
    {
        if (! self::tablesExist()) {
            return;
        }

        $order = FarmadocOrder::query()->find($orderId);

        if ($order === null) {
            return;
        }

        $created = FarmadocOrderAcceptanceDismissal::query()->insertOrIgnore([
            'farmadoc_order_id' => $order->getKey(),
            'user_id' => $user->getKey(),
            'dismissed_at' => now(),
        ]);

        if ($created < 1) {
            return;
        }

        $notification = Notification::make()
            ->title('Farmadoc aceptó el pedido '.$order->order_number)
            ->body(self::notificationBody($order))
            ->success()
            ->icon('heroicon-o-check-badge');

        if (self::userCanOpenOrders($user)) {
            $notification->actions([
                Action::make('verPedido')
                    ->label('Ver pedido')
                    ->url(ViewFarmadocOrder::getUrl(['record' => $order], panel: 'operations'))
                    ->markAsRead(),
            ]);
        }

        $notification->sendToDatabase($user);
    }

    /**
     * Detalle guardado de la aceptación; si Farmadoc no lo mandó, las líneas del pedido como aceptadas.
     *
     * @return list<array{name: string, barcode: string|null, quantity: int, accepted: bool, rejection_note: string|null}>
     */
    public static function items(FarmadocOrder $order): array
    {
        $source = is_array($order->acceptance_items) && $order->acceptance_items !== []
            ? $order->acceptance_items
            : array_map(fn (array $line): array => [
                'name' => $line['name'],
                'barcode' => $line['barcode'],
                'quantity' => $line['quantity'],
                'accepted' => true,
                'rejection_note' => null,
            ], FarmadocOrderItems::forDisplay($order));

        $items = [];

        foreach ($source as $item) {
            if (! is_array($item)) {
                continue;
            }

            $items[] = [
                'name' => self::text($item['name'] ?? null) ?? 'Medicamento sin nombre',
                'barcode' => self::text($item['barcode'] ?? null),
                'quantity' => is_numeric($item['quantity'] ?? null) ? (int) $item['quantity'] : 0,
                'accepted' => (bool) ($item['accepted'] ?? false),
                'rejection_note' => self::text($item['rejection_note'] ?? null),
            ];
        }

        return $items;
    }

    public static function flushTableCache(): void
    {
        self::$tablesExist = false;
    }

    private static function userCanOpenOrders(User $user): bool
    {
        $isAuthenticatedUser = Auth::id() !== null && (int) Auth::id() === (int) $user->getKey();

        return $isAuthenticatedUser && FarmadocOrderResource::canAccess();
    }

    private static function notificationBody(FarmadocOrder $order): string
    {
        $items = self::items($order);
        $accepted = count(array_filter($items, fn (array $item): bool => $item['accepted']));
        $rejected = count($items) - $accepted;

        $parts = [];

        if ($order->delivery_note_number) {
            $parts[] = 'Nota de entrega '.$order->delivery_note_number;
        }

        $parts[] = $accepted.' '.($accepted === 1 ? 'medicamento aceptado' : 'medicamentos aceptados')
            .($rejected > 0 ? ', '.$rejected.' '.($rejected === 1 ? 'rechazado' : 'rechazados') : '');

        if ($order->patient_name) {
            $parts[] = 'Paciente '.$order->patient_name;
        }

        return implode(' · ', $parts).'.';
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Las columnas de la aceptación y la tabla de cierres llegan con migraciones; hasta
     * entonces el aviso no existe (y no rompe el panel).
     */
    private static function tablesExist(): bool
    {
        if (self::$tablesExist) {
            return true;
        }

        return self::$tablesExist = Schema::hasTable('farmadoc_order_acceptance_dismissals')
            && FarmadocOrder::acceptanceColumnsExist()
            && FarmadocOrder::acceptanceDetailColumnsExist();
    }
}
