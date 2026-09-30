<?php

declare(strict_types=1);

namespace App\Filament\Operations\Concerns;

use App\Filament\Operations\Support\AffiliatePersonalDataFields;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\User;
use App\Support\Operations\AffiliatePersonalDataUpdater;
use App\Support\Operations\AffiliatePersonalDataUpdateResult;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Comportamiento común de las páginas de edición de datos personales de
 * afiliados (individual y corporativo) en Operaciones.
 *
 * El formulario genérico que Filament generó para estos recursos dejaba
 * cambiar plan, tarifa y montos, y traía un botón Eliminar. Estas páginas solo
 * muestran datos personales y guardan con {@see AffiliatePersonalDataUpdater},
 * que ignora cualquier otro campo, sincroniza telemedicina y avisa a Afiliaciones.
 */
trait EditsAffiliatePersonalData
{
    private ?AffiliatePersonalDataUpdateResult $updateResult = null;

    public function getTitle(): string|Htmlable
    {
        return 'Editar datos del afiliado';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Ver detalles')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return AffiliatePersonalDataFields::fillState($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Affiliate|AffiliateCorporate $record */
        $user = Auth::user();

        try {
            $this->updateResult = AffiliatePersonalDataUpdater::update(
                $record,
                $data,
                $user instanceof User ? $user : null,
                AffiliatePersonalDataUpdater::SOURCE_EDIT_FORM,
            );
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => ['data.'.$field => $messages])
                ->all());
        }

        return $this->updateResult->affiliate;
    }

    protected function getSavedNotification(): ?Notification
    {
        $result = $this->updateResult;

        if ($result === null || ! $result->hasChanges()) {
            return Notification::make()
                ->info()
                ->title('No hubo cambios')
                ->body('Los datos ya estaban así. No se envió ningún aviso a Afiliaciones.');
        }

        $count = count($result->changes);
        $body = ($count === 1 ? 'Se guardó 1 cambio' : 'Se guardaron '.$count.' cambios')
            .(AffiliatePersonalDataUpdater::affiliationsTeamWillBeNotified()
                ? ' y se avisó al equipo de Afiliaciones por correo y WhatsApp.'
                : '. El aviso a Afiliaciones está pausado o sin destinatarios en el Centro de notificaciones.');

        if ($result->telemedicineSynced) {
            $body .= ' La ficha del paciente de telemedicina también se actualizó.';
        }

        if ($result->ageRangeWarning !== null) {
            return Notification::make()
                ->warning()
                ->title('Datos actualizados: revise la tarifa')
                ->body($body.' '.$result->ageRangeWarning)
                ->persistent();
        }

        return Notification::make()
            ->success()
            ->title('Datos del afiliado actualizados')
            ->body($body);
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
