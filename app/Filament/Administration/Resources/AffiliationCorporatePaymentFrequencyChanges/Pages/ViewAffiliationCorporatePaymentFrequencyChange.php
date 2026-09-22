<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\Pages;

use App\Exceptions\CorporatePaymentFrequencyChangeBlockedException;
use App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\AffiliationCorporatePaymentFrequencyChangeResource;
use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Models\AffiliationCorporatePaymentFrequencyChange as FrequencyChange;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChangeReverser as Reverser;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ViewAffiliationCorporatePaymentFrequencyChange extends ViewRecord
{
    protected static string $resource = AffiliationCorporatePaymentFrequencyChangeResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var FrequencyChange $record */
        $record = $this->getRecord();

        return 'Cambio de frecuencia · '.($record->affiliation_code ?: 'Afiliación #'.$record->affiliation_corporate_id);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->validateAction(),
            $this->reverseAction(),
            Action::make('viewAffiliationCorporate')
                ->label('Ver afiliación')
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('gray')
                ->url(fn (FrequencyChange $record): string => AffiliationCorporateResource::getUrl('view', ['record' => $record->affiliation_corporate_id]))
                ->visible(fn (FrequencyChange $record): bool => $record->affiliation_corporate_id > 0),
            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(AffiliationCorporatePaymentFrequencyChangeResource::getUrl()),
        ];
    }

    private function validateAction(): Action
    {
        return Action::make('validateChange')
            ->label('Marcar como validado')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Validar el cambio de frecuencia')
            ->modalDescription('Confirma que Administración revisó el cambio y los avisos de cobro, y está de acuerdo. Queda registrado quién validó y cuándo. Si luego se detecta una falla, aún podrá revertirse.')
            ->modalSubmitActionLabel('Validar')
            ->action(function (FrequencyChange $record): void {
                try {
                    Reverser::validate($record);
                } catch (CorporatePaymentFrequencyChangeBlockedException $exception) {
                    Notification::make()->warning()->title('No se pudo validar')->body($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Cambio validado')->send();
                $this->refreshFormData(['validated_at', 'validated_by_name']);
            })
            ->visible(fn (FrequencyChange $record): bool => ! $record->isReversed() && ! $record->isValidated());
    }

    private function reverseAction(): Action
    {
        return Action::make('reverseChange')
            ->label('Revertir cambio')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalHeading('Revertir el cambio de frecuencia de pago')
            ->modalDescription(fn (FrequencyChange $record): string => 'La afiliación vuelve a '.CorporatePaymentFrequency::label($record->previous_frequency)
                .' con su monto por período anterior. Los '.count($record->created_collections ?? []).' avisos creados por el cambio pasan a CANCELADO '
                .'y los '.count($record->cancelled_collections ?? []).' avisos originales vuelven a POR PAGAR. Nada se borra. '
                .'Se avisa a Administración y al analista que hizo el cambio. Esta acción no se puede deshacer.')
            ->modalSubmitActionLabel('Revertir definitivamente')
            ->form(fn (FrequencyChange $record): array => [
                Textarea::make('reason')
                    ->label('Motivo del reverso')
                    ->placeholder('Explique qué falló o por qué Administración no está de acuerdo con el cambio.')
                    ->required()
                    ->minLength(Reverser::MIN_REASON_LENGTH)
                    ->maxLength(Reverser::MAX_REASON_LENGTH)
                    ->rows(4)
                    ->validationMessages([
                        'required' => 'El motivo es obligatorio.',
                        'min' => 'Explique el motivo con al menos '.Reverser::MIN_REASON_LENGTH.' caracteres.',
                    ]),
                TextInput::make('confirmation')
                    ->label('Escriba '.Reverser::expectedConfirmation($record).' para confirmar')
                    ->required()
                    ->autocomplete(false)
                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                        if (! Reverser::confirmationMatches($record, is_string($value) ? $value : null)) {
                            $fail('El código no coincide con el de la afiliación.');
                        }
                    })
                    ->validationMessages([
                        'required' => 'Escriba el código de la afiliación para confirmar.',
                    ]),
            ])
            ->action(function (FrequencyChange $record, array $data): void {
                try {
                    $result = Reverser::reverse($record, (string) ($data['reason'] ?? ''), (string) ($data['confirmation'] ?? ''));
                } catch (CorporatePaymentFrequencyChangeBlockedException|InvalidArgumentException $exception) {
                    Notification::make()->warning()->title('No se revirtió el cambio')->body($exception->getMessage())->persistent()->send();

                    return;
                } catch (Throwable $throwable) {
                    Log::error('ADMINISTRACION-CAMBIO-FRECUENCIA: Error al revertir.', [
                        'change_id' => $record->getKey(),
                        'error' => $throwable->getMessage(),
                    ]);
                    Notification::make()->danger()->title('No se pudo revertir')
                        ->body('No se aplicó ningún cambio. Intente de nuevo o contacte a soporte.')->persistent()->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Cambio revertido')
                    ->body($result['restored'].' avisos restaurados a POR PAGAR y '.$result['cancelled'].' avisos anulados. Se avisó a Administración y al analista.')
                    ->persistent()
                    ->send();

                $this->redirect(AffiliationCorporatePaymentFrequencyChangeResource::getUrl('view', ['record' => $record]), navigate: true);
            })
            ->disabled(fn (FrequencyChange $record): bool => Reverser::blockReason($record) !== null)
            ->tooltip(fn (FrequencyChange $record): ?string => Reverser::blockReason($record))
            ->visible(fn (FrequencyChange $record): bool => ! $record->isReversed() && BusinessFilamentActionAccess::userCan(
                BusinessFilamentActionPermissionRegistry::REVERSE_CORPORATE_PAYMENT_FREQUENCY_CHANGE,
            ));
    }
}
