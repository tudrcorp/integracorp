<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\DoctorNurse;
use App\Models\Supplier;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

final class OperationServiceOrderProviderContacts
{
    /**
     * @return array{email: ?string, phone: ?string, address: ?string, name: ?string, missing: list<string>}
     */
    public static function empty(): array
    {
        return [
            'email' => null,
            'phone' => null,
            'address' => null,
            'name' => null,
            'missing' => ['correo', 'teléfono', 'dirección'],
        ];
    }

    /**
     * @return array{email: ?string, phone: ?string, address: ?string, name: ?string, missing: list<string>}
     */
    public static function fromModels(?DoctorNurse $doctorNurse, ?Supplier $supplier): array
    {
        if ($supplier instanceof Supplier) {
            return self::fromSupplier($supplier);
        }

        if ($doctorNurse instanceof DoctorNurse) {
            return self::fromDoctorNurse($doctorNurse);
        }

        return self::empty();
    }

    /**
     * @return array{email: ?string, phone: ?string, address: ?string, name: ?string, missing: list<string>}
     */
    public static function fromCatalogIds(?int $doctorNurseId, ?int $supplierId): array
    {
        if ($supplierId !== null && $supplierId > 0) {
            $supplier = Supplier::query()
                ->with(['state', 'city'])
                ->find($supplierId);

            return $supplier instanceof Supplier
                ? self::fromSupplier($supplier)
                : self::empty();
        }

        if ($doctorNurseId !== null && $doctorNurseId > 0) {
            $doctorNurse = DoctorNurse::query()->find($doctorNurseId);

            return $doctorNurse instanceof DoctorNurse
                ? self::fromDoctorNurse($doctorNurse)
                : self::empty();
        }

        return self::empty();
    }

    /**
     * @return array{email: ?string, phone: ?string, address: ?string, name: ?string, missing: list<string>}
     */
    public static function fromSupplier(Supplier $supplier): array
    {
        $contacts = MedicalAppointmentManager::resolveSupplierNotifyContacts($supplier);

        return self::pack(
            $contacts['email'],
            $contacts['phone'],
            OperationServiceOrderProviderSummary::addressFromSupplier($supplier),
            $supplier->name,
        );
    }

    /**
     * @return array{email: ?string, phone: ?string, address: ?string, name: ?string, missing: list<string>}
     */
    public static function fromDoctorNurse(DoctorNurse $doctorNurse): array
    {
        $email = filled($doctorNurse->correo_principal)
            ? trim((string) $doctorNurse->correo_principal)
            : null;
        $phone = filled($doctorNurse->personal_phone)
            ? trim((string) $doctorNurse->personal_phone)
            : (filled($doctorNurse->local_phone) ? trim((string) $doctorNurse->local_phone) : null);

        return self::pack(
            $email !== '' ? $email : null,
            $phone !== '' ? $phone : null,
            OperationServiceOrderProviderSummary::addressFromDoctorNurse($doctorNurse),
            $doctorNurse->name,
        );
    }

    public static function hasCatalogSelection(Get $get): bool
    {
        return ! (bool) $get('register_unregistered_provider')
            && (filled($get('doctor_nurse_id')) || filled($get('supplier_id')));
    }

    public static function applyFromDoctorNurseId(int $doctorNurseId, Set $set): void
    {
        self::apply(self::fromCatalogIds($doctorNurseId, null), $set);
    }

    public static function applyFromSupplierId(int $supplierId, Set $set): void
    {
        self::apply(self::fromCatalogIds(null, $supplierId), $set);
    }

    /**
     * @param  array{email: ?string, phone: ?string, address: ?string, name: ?string, missing: list<string>}  $contacts
     */
    public static function apply(array $contacts, Set $set, bool $notifyIfIncomplete = true): void
    {
        $set('supplier_notify_email', $contacts['email']);
        $set('supplier_notify_phone', $contacts['phone']);
        $set('supplier_notify_address', $contacts['address']);

        if ($notifyIfIncomplete) {
            self::notifyIfIncomplete($contacts);
        }
    }

    public static function clearForm(Set $set): void
    {
        $set('supplier_notify_email', null);
        $set('supplier_notify_phone', null);
        $set('supplier_notify_address', null);
    }

    /**
     * @param  array{email: ?string, phone: ?string, address: ?string, name: ?string, missing: list<string>}  $contacts
     */
    public static function notifyIfIncomplete(array $contacts): void
    {
        if ($contacts['missing'] === []) {
            return;
        }

        $name = filled($contacts['name'])
            ? trim((string) $contacts['name'])
            : 'el proveedor seleccionado';
        $missing = self::spanishList($contacts['missing']);

        Notification::make()
            ->title('Ficha del proveedor incompleta')
            ->body(
                'La ficha de '.$name.' no tiene '.$missing.'. '
                .'Comuníquese con el equipo de Proveedores para completar esos datos. '
                .'No los invente en esta orden.'
            )
            ->warning()
            ->persistent()
            ->send();
    }

    /**
     * @return array{email: ?string, phone: ?string, address: ?string, name: ?string, missing: list<string>}
     */
    private static function pack(?string $email, ?string $phone, ?string $address, mixed $name): array
    {
        $resolvedName = filled($name) ? trim((string) $name) : null;
        $missing = [];

        if ($email === null || $email === '') {
            $missing[] = 'correo';
            $email = null;
        }

        if ($phone === null || $phone === '') {
            $missing[] = 'teléfono';
            $phone = null;
        }

        if ($address === null || $address === '') {
            $missing[] = 'dirección';
            $address = null;
        }

        return [
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'name' => $resolvedName !== '' ? $resolvedName : null,
            'missing' => $missing,
        ];
    }

    /**
     * @param  list<string>  $items
     */
    public static function spanishList(array $items): string
    {
        $items = array_values(array_filter($items, static fn (string $item): bool => $item !== ''));

        if ($items === []) {
            return '';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' y '.$last;
    }
}
