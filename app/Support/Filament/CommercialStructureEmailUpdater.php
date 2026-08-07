<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CommercialStructureEmailUpdater
{
    public const TEMPORARY_PASSWORD = '12345678';

    /**
     * @return array{
     *     email_from: string,
     *     email_to: string,
     *     user_updated: bool,
     *     user_id: int|null
     * }
     */
    public static function update(
        Agency|Agent $record,
        string $newEmail,
        string $reason,
        bool $alsoUpdateUser,
        string $entity,
        string $panel,
    ): array {
        $newEmail = trim($newEmail);
        $previousEmail = trim((string) $record->email);
        $reason = trim($reason);

        if ($newEmail === '') {
            throw ValidationException::withMessages([
                'email' => 'Debe indicar el nuevo correo.',
            ]);
        }

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Debe indicar el motivo del cambio de correo.',
            ]);
        }

        if (strcasecmp($newEmail, $previousEmail) === 0) {
            throw ValidationException::withMessages([
                'email' => 'El nuevo correo debe ser distinto al actual.',
            ]);
        }

        $user = null;
        $userUpdated = false;

        if ($alsoUpdateUser) {
            $user = self::findUserByEmail($previousEmail);

            if ($user === null) {
                throw new RuntimeException(
                    'No se encontró un usuario con el correo actual ('.$previousEmail.'). Desmarque la opción de actualizar usuarios o cree el usuario primero.'
                );
            }

            $emailTaken = User::query()
                ->where('email', $newEmail)
                ->whereKeyNot($user->getKey())
                ->exists();

            if ($emailTaken) {
                throw ValidationException::withMessages([
                    'email' => 'Ya existe otro usuario con ese correo.',
                ]);
            }
        }

        $record->email = $newEmail;
        $record->updated_by = Auth::user()?->name;
        $record->save();

        if ($user !== null) {
            $user->email = $newEmail;
            $user->updated_by = Auth::user()?->name;
            $user->save();
            $userUpdated = true;
        }

        $details = [
            'entity' => $entity,
            'record_id' => $record->getKey(),
            'email_from' => $previousEmail,
            'email_to' => $newEmail,
            'reason' => $reason,
            'also_update_user' => $alsoUpdateUser,
            'user_updated' => $userUpdated,
            'user_id' => $user?->id,
            'updated_by' => Auth::id(),
        ];

        if ($record instanceof Agency) {
            $details['agency_code'] = $record->code;
            $details['agency_name'] = $record->name_corporative;
        }

        if ($record instanceof Agent) {
            $details['code_agent'] = $record->code_agent;
            $details['agent_name'] = $record->name;
        }

        SecurityAudit::log(
            self::emailAuditAction($panel, $entity),
            self::emailAuditRoute($panel, $entity),
            $details,
        );

        return [
            'email_from' => $previousEmail,
            'email_to' => $newEmail,
            'user_updated' => $userUpdated,
            'user_id' => $user?->id,
        ];
    }

    public static function findUserByEmail(?string $email): ?User
    {
        $email = trim((string) $email);

        if ($email === '') {
            return null;
        }

        return User::query()
            ->where('email', $email)
            ->first();
    }

    public static function emailsMatchForPasswordReset(Agency|Agent $record): bool
    {
        return self::findUserByEmail((string) $record->email) !== null;
    }

    private static function emailAuditAction(string $panel, string $entity): string
    {
        return 'AUDIT_'.strtoupper($panel).'_'.strtoupper($entity).'_EMAIL_UPDATED';
    }

    private static function emailAuditRoute(string $panel, string $entity): string
    {
        $resource = $entity === 'agency' ? 'agencies' : 'agents';

        return $panel.'.'.$resource.'.email.update';
    }
}
