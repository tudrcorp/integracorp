<?php

namespace App\Jobs;

use App\Http\Controllers\NotificationController;
use App\Mail\CollaboratorAnniversaryMail;
use App\Models\CollaboratorAnniversary;
use App\Models\RrhhColaborador;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCollaboratorAnniversaryNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     * Compara día y mes actual con fechaIngreso de colaboradores y envía email + WhatsApp.
     */
    public function handle(): void
    {
        $today = now();
        $collaborators = $this->getCollaboratorsWithAnniversaryToday($today);

        if ($collaborators->isEmpty()) {
            Log::info('SendCollaboratorAnniversaryNotification: No hay colaboradores con aniversario hoy.');

            return;
        }

        foreach ($collaborators as $collaborator) {
            $this->sendNotifications($collaborator, $today);
        }
    }

    /**
     * Colaboradores cuyo día y mes de fechaIngreso coinciden con hoy.
     */
    private function getCollaboratorsWithAnniversaryToday(Carbon $today): \Illuminate\Support\Collection
    {
        return RrhhColaborador::query()
            ->whereNotNull('fechaIngreso')
            ->where('fechaIngreso', '!=', '')
            ->get()
            ->filter(function (RrhhColaborador $c) use ($today) {
                $parsed = $this->parseFechaIngreso($c->fechaIngreso);
                if (! $parsed) {
                    return false;
                }

                return $parsed->month === (int) $today->month && $parsed->day === (int) $today->day;
            });
    }

    private function parseFechaIngreso(?string $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'];
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, trim($value));
                if ($date) {
                    return $date;
                }
            } catch (\Exception) {
                continue;
            }
        }
        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function sendNotifications(RrhhColaborador $collaborator, Carbon $today): void
    {
        $name = $collaborator->fullName ?? 'Colaborador';
        $years = $this->parseFechaIngreso($collaborator->fechaIngreso)?->diffInYears($today) ?? 0;

        $content = "¡Feliz aniversario! Hoy celebramos {$years} año(s) de trayectoria en la empresa. Gracias por tu dedicación y compromiso.";

        // Imagen del aniversario: comparar id del colaborador con rrhh_colaborador_id en collaborator_anniversaries
        $anniversaryRecord = CollaboratorAnniversary::where('rrhh_colaborador_id', $collaborator->id)->first();
        $anniversaryImage = $anniversaryRecord?->image;

        $email = $collaborator->emailCorporativo ?? $collaborator->emailPersonal ?? null;
        if ($email) {
            try {
                Mail::to($email)
                ->cc('solrodriguez@tudrencasa.com')
                ->send(new CollaboratorAnniversaryMail($name, $content, $anniversaryImage));
                Log::info("Email aniversario enviado a {$email} ({$name})");
            } catch (Throwable $e) {
                Log::error("Error enviando email aniversario a {$email}: {$e->getMessage()}");
            }
        }

        $phone = $this->normalizePhone($collaborator->telefonoCorporativo ?? $collaborator->telefono ?? null);
        if ($phone) {
            try {
                // Si tiene imagen en collaborator_anniversaries, enviar por WhatsApp con imagen; si no, solo texto
                if (! empty($anniversaryImage)) {
                    NotificationController::notificationBirthday($name, $phone, $content, $anniversaryImage, 'image');
                } else {
                    NotificationController::notificationBirthday($name, $phone, $content, '', 'url');
                }
                Log::info("WhatsApp aniversario enviado a {$phone} ({$name})");
            } catch (Throwable $e) {
                Log::error("Error enviando WhatsApp aniversario a {$phone}: {$e->getMessage()}");
            }
        }
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) < 10) {
            return null;
        }
        if (str_starts_with($phone, '0')) {
            $phone = '58'.substr($phone, 1);
        } elseif (! str_starts_with($phone, '58') && strlen($phone) === 11) {
            $phone = '58'.$phone;
        }

        return $phone;
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendCollaboratorAnniversaryNotification FAILED: '.$exception->getMessage());
    }
}
