<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MassNotificationDeliveryStatus;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\BirthdayNotification;
use App\Models\BirthdayNotificationDelivery;
use App\Models\Capemiac;
use App\Models\RrhhColaborador;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class BirthdayNotificationRecipientCatalog
{
    /**
     * @return array{
     *     model: class-string<Model>,
     *     name: string,
     *     email: string|null,
     *     phone: string|null,
     *     birth_date: string|null
     * }
     */
    public static function configFor(string $dataType): array
    {
        return match ($dataType) {
            'agents' => [
                'model' => Agent::class,
                'name' => 'name',
                'email' => 'email',
                'phone' => 'phone',
                'birth_date' => 'birth_date',
            ],
            'agencies' => [
                'model' => Agency::class,
                'name' => 'name_corporative',
                'email' => 'email',
                'phone' => 'phone',
                'birth_date' => 'brithday_date',
            ],
            'affiliates' => [
                'model' => Affiliate::class,
                'name' => 'full_name',
                'email' => 'email',
                'phone' => 'phone',
                'birth_date' => 'birth_date',
            ],
            'affiliate_corporates' => [
                'model' => AffiliateCorporate::class,
                'name' => 'first_name',
                'email' => 'email',
                'phone' => 'phone',
                'birth_date' => 'birth_date',
            ],
            'affiliations' => [
                'model' => Affiliation::class,
                'name' => 'full_name_ti',
                'email' => 'email_ti',
                'phone' => 'phone_ti',
                'birth_date' => 'birth_date_ti',
            ],
            'rrhh_colaboradors' => [
                'model' => RrhhColaborador::class,
                'name' => 'fullName',
                'email' => 'emailCorporativo',
                'phone' => 'telefono',
                'birth_date' => 'fechaNacimiento',
            ],
            'suppliers' => [
                'model' => Supplier::class,
                'name' => 'name',
                'email' => 'correo_principal',
                'phone' => 'personal_phone',
                'birth_date' => 'afiliacion_proveedor',
            ],
            'users' => [
                'model' => User::class,
                'name' => 'name',
                'email' => 'email',
                'phone' => 'phone',
                'birth_date' => 'birthday_date',
            ],
            'capemiacs' => [
                'model' => Capemiac::class,
                'name' => 'cliente',
                'email' => 'email',
                'phone' => 'telefonoUno',
                'birth_date' => null,
            ],
            default => throw new InvalidArgumentException("Tipo de destinatario no soportado: {$dataType}"),
        };
    }

    public static function queryFor(BirthdayNotification $notification): Builder
    {
        $dataType = (string) $notification->data_type;
        $config = self::configFor($dataType);

        return $config['model']::query()->orderBy($config['name']);
    }

    /**
     * @return array{
     *     model: class-string<Model>,
     *     name: string,
     *     email: string|null,
     *     phone: string|null,
     *     birth_date: string|null
     * }
     */
    public static function configForNotification(BirthdayNotification $notification): array
    {
        return self::configFor((string) $notification->data_type);
    }

    public static function applyBirthdayDateFilter(Builder $query, string $birthDateColumn, CarbonInterface $date): Builder
    {
        $dayMonth = $date->format('d/m');

        return $query->where($birthDateColumn, 'like', $dayMonth.'/%');
    }

    public static function recipientName(Model $record, array $config): string
    {
        return trim((string) data_get($record, $config['name'], ''));
    }

    public static function recipientEmail(Model $record, array $config): ?string
    {
        if ($config['email'] === null) {
            return null;
        }

        $email = data_get($record, $config['email']);

        return filled($email) ? (string) $email : null;
    }

    public static function recipientPhone(Model $record, array $config): ?string
    {
        if ($config['phone'] === null) {
            return null;
        }

        $phone = data_get($record, $config['phone']);

        return filled($phone) ? (string) $phone : null;
    }

    public static function recipientBirthDate(Model $record, array $config): ?string
    {
        if ($config['birth_date'] === null) {
            return null;
        }

        $birthDate = data_get($record, $config['birth_date']);

        return filled($birthDate) ? (string) $birthDate : null;
    }

    /**
     * @return Collection<int, BirthdayNotificationDelivery>
     */
    public static function deliveriesForNotification(BirthdayNotification $notification, ?CarbonInterface $date = null): Collection
    {
        $query = BirthdayNotificationDelivery::query()
            ->where('birthday_notification_id', $notification->id);

        if ($date !== null) {
            $query->whereDate('delivery_date', $date->toDateString());
        }

        return $query->get();
    }

    public static function matchDelivery(
        Collection $deliveries,
        string $fullName,
        ?string $email,
        ?string $phone,
    ): ?BirthdayNotificationDelivery {
        $normalizedName = mb_strtolower(trim($fullName));
        $normalizedEmail = filled($email) ? mb_strtolower(trim($email)) : null;
        $normalizedPhone = filled($phone) ? (preg_replace('/\D+/', '', $phone) ?? '') : null;

        return $deliveries->first(function (BirthdayNotificationDelivery $delivery) use ($normalizedName, $normalizedEmail, $normalizedPhone): bool {
            if (mb_strtolower(trim((string) $delivery->full_name)) === $normalizedName) {
                return true;
            }

            if ($normalizedEmail !== null && filled($delivery->email) && mb_strtolower(trim((string) $delivery->email)) === $normalizedEmail) {
                return true;
            }

            if ($normalizedPhone !== null && $normalizedPhone !== '' && filled($delivery->phone)) {
                return (preg_replace('/\D+/', '', (string) $delivery->phone) ?? '') === $normalizedPhone;
            }

            return false;
        });
    }

    public static function statusLabel(?MassNotificationDeliveryStatus $status): string
    {
        return $status?->label() ?? '—';
    }

    public static function statusColor(?MassNotificationDeliveryStatus $status): string
    {
        return match ($status) {
            MassNotificationDeliveryStatus::Sent => 'success',
            MassNotificationDeliveryStatus::Failed => 'danger',
            MassNotificationDeliveryStatus::Pending => 'warning',
            MassNotificationDeliveryStatus::Skipped => 'gray',
            default => 'gray',
        };
    }
}
