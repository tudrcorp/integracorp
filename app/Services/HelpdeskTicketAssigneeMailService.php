<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HelpdeskTicketMailException;
use App\Mail\SendEmailCreateTicketAndAssigned;
use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class HelpdeskTicketAssigneeMailService
{
    private const STATIC_CC_SOLRODRIGUEZ = 'solrodriguez@tudrencasa.com';

    /**
     * CC fijo + correos corporativos de colaboradores en «CC (Opcional)» del ticket.
     * No duplica el destinatario principal (To) en la lista de CC.
     *
     * @return list<string>
     */
    public static function buildCcEmailListForAssigneeMessage(HelpDesk $ticket, string $primaryToEmail): array
    {
        $primary = strtolower(trim($primaryToEmail));

        $cc = [self::STATIC_CC_SOLRODRIGUEZ];

        $ids = $ticket->getAttribute('cc_colaboradores');
        if (is_array($ids) && $ids !== []) {
            $extra = RrhhColaborador::query()
                ->whereIn('id', $ids)
                ->pluck('emailCorporativo')
                ->filter(fn (?string $e): bool => filled($e))
                ->map(fn (string $e): string => strtolower(trim($e)))
                ->reject(fn (string $e): bool => $e === $primary)
                ->unique()
                ->values()
                ->all();
            $cc = [...$cc, ...$extra];
        }

        $cc = array_values(array_unique(array_filter($cc, fn (string $e): bool => $e !== '' && $e !== $primary)));

        if ($cc === []) {
            return [self::STATIC_CC_SOLRODRIGUEZ];
        }

        return $cc;
    }

    /**
     * Vuelve a leer el ticket con asignados desde la BD. Tras crear en Filament, el modelo en memoria
     * puede tener `rrhhColaboradores` vacío u obsoleto; `loadMissing` no recarga si la relación ya está cargada.
     */
    public static function loadTicketWithAssigneesForNotifications(HelpDesk $record): HelpDesk
    {
        $fresh = HelpDesk::query()
            ->with('rrhhColaboradores')
            ->find($record->getKey());

        return $fresh ?? $record;
    }

    /**
     * Envía un correo a cada colaborador asignado al ticket (cada uno en To: con saludo propio).
     * Un fallo en un destinatario no impide el envío al resto.
     *
     * @return int Número de envíos despachados (encolados si el mailer usa cola).
     */
    public static function sendToEachAssignee(HelpDesk $ticket): int
    {
        $ticket = self::loadTicketWithAssigneesForNotifications($ticket);

        if ($ticket->rrhhColaboradores->isEmpty()) {
            return 0;
        }

        $sentCount = 0;

        foreach ($ticket->rrhhColaboradores as $colaborador) {
            $emailCorporativo = $colaborador->emailCorporativo;

            if (blank($emailCorporativo)) {
                Log::warning('Helpdesk: colaborador asignado sin correo corporativo; no se envía notificación.', [
                    'help_desk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                ]);

                continue;
            }

            try {
                $ccList = self::buildCcEmailListForAssigneeMessage($ticket, $emailCorporativo);

                Mail::to($emailCorporativo)
                    ->cc($ccList)
                    ->send(SendEmailCreateTicketAndAssigned::fromTicket($ticket, $colaborador));
                $sentCount++;

            } catch (HelpdeskTicketMailException $e) {
                Log::error('Helpdesk: no se pudo preparar o enviar correo a un asignado.', array_merge(
                    $e->context,
                    [
                        'message' => $e->getMessage(),
                        'help_desk_id' => $ticket->getKey(),
                        'rrhh_colaborador_id' => $colaborador->getKey(),
                    ],
                ));
            } catch (Throwable $e) {
                Log::error('Helpdesk: error al enviar correo a un asignado.', [
                    'message' => $e->getMessage(),
                    'help_desk_id' => $ticket->getKey(),
                    'rrhh_colaborador_id' => $colaborador->getKey(),
                    'exception' => $e::class,
                ]);
            }
        }

        return $sentCount;
    }
}
