<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CommercialStructurePasswordResetter
{
    /**
     * @return array{user_id: int, email: string}
     */
    public static function reset(
        Agency|Agent $record,
        string $reason,
        string $entity,
        string $panel,
    ): array {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Debe indicar el motivo del reseteo de contraseña.',
            ]);
        }

        $email = trim((string) $record->email);

        if ($email === '') {
            throw new RuntimeException('El registro no tiene correo para validar contra usuarios.');
        }

        $user = CommercialStructureEmailUpdater::findUserByEmail($email);

        if ($user === null || (string) $user->email !== $email) {
            throw new RuntimeException(
                'No se puede resetear la contraseña: el correo de la '.($entity === 'agency' ? 'agencia' : 'agente')
                .' debe ser igual al correo del usuario en el sistema ('.$email.').'
            );
        }

        $user->password = Hash::make(CommercialStructureEmailUpdater::TEMPORARY_PASSWORD);
        $user->updated_by = Auth::user()?->name;
        $user->save();

        $details = [
            'entity' => $entity,
            'record_id' => $record->getKey(),
            'email' => $email,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'reason' => $reason,
            'temporary_password_set' => true,
            'reset_by' => Auth::id(),
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
            self::passwordAuditAction($panel, $entity),
            self::passwordAuditRoute($panel, $entity),
            $details,
        );

        return [
            'user_id' => (int) $user->id,
            'email' => $email,
        ];
    }

    public static function resolveMatchingUser(Agency|Agent $record): ?User
    {
        return CommercialStructureEmailUpdater::findUserByEmail((string) $record->email);
    }

    private static function passwordAuditAction(string $panel, string $entity): string
    {
        return 'AUDIT_'.strtoupper($panel).'_'.strtoupper($entity).'_USER_PASSWORD_RESET';
    }

    private static function passwordAuditRoute(string $panel, string $entity): string
    {
        $resource = $entity === 'agency' ? 'agencies' : 'agents';

        return $panel.'.'.$resource.'.user-password.reset';
    }
}
