<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliations\Concerns;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCertificateDelivery;
use App\Models\AffiliationCertificateIssue;
use App\Models\User;
use App\Services\AffiliationCertificateDeliveryService;
use App\Support\Affiliations\Certificates\CertificateDeliveryRecipients;
use App\Support\Affiliations\Certificates\CertificateFormat;
use App\Support\Affiliations\Certificates\CorporateCertificateDocument;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * «Enviar por correo» del Generador de Certificado (individual y corporativo):
 * el documento completo o solo los carnets elegidos, con los correos del dueño
 * de la afiliación y de la propia afiliación ya cargados.
 *
 * Requiere que la página tenga `currentIssue()`.
 */
trait SendsAffiliationCertificateByEmail
{
    /** Hasta este número de carnets la lista se carga completa; por encima se busca. */
    private const CARNET_OPTIONS_PRELOAD = 100;

    public function sendByEmailAction(): Action
    {
        return Action::make('sendByEmail')
            ->label('Enviar por correo')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('primary')
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
            ])
            ->visible(fn (): bool => $this->canSendCurrentIssue())
            ->modalHeading('Enviar certificado por correo')
            ->modalDescription(fn (): string => $this->sendByEmailDescription())
            ->modalIcon(Heroicon::OutlinedEnvelope)
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Enviar')
            ->modalCancelActionLabel('Cancelar')
            ->fillForm(fn (): array => [
                'content' => AffiliationCertificateDelivery::CONTENT_FULL,
                'carnets' => [],
                'recipients' => ($issue = $this->currentIssue()) !== null ? CertificateDeliveryRecipients::defaultsFor($issue) : [],
                'message' => null,
            ])
            ->schema([
                Radio::make('content')
                    ->label('¿Qué desea enviar?')
                    ->options(fn (): array => $this->sendByEmailContentOptions())
                    ->required()
                    ->live()
                    ->validationMessages(['required' => 'Elija qué desea enviar.']),
                Select::make('carnets')
                    ->label('Carnets a enviar')
                    ->helperText('Hasta '.AffiliationCertificateDeliveryService::MAX_CARNETS_PER_DELIVERY.' carnets por correo. Se envían en un PDF aparte, sin el certificado.')
                    ->placeholder('Busque por nombre o cédula')
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => $this->carnetCount() <= self::CARNET_OPTIONS_PRELOAD ? $this->carnetLabels() : [])
                    ->getSearchResultsUsing(fn (string $search): array => $this->carnetLabels($search))
                    ->getOptionLabelsUsing(fn (array $values): array => $this->carnetLabels(null, $values))
                    ->maxItems(AffiliationCertificateDeliveryService::MAX_CARNETS_PER_DELIVERY)
                    ->visible(fn (Get $get): bool => $get('content') === AffiliationCertificateDelivery::CONTENT_CARNETS)
                    ->required(fn (Get $get): bool => $get('content') === AffiliationCertificateDelivery::CONTENT_CARNETS)
                    ->validationMessages([
                        'required' => 'Elija al menos un carnet o cambie a «Documento completo».',
                        'max' => 'Puede enviar hasta '.AffiliationCertificateDeliveryService::MAX_CARNETS_PER_DELIVERY.' carnets por correo.',
                    ]),
                TagsInput::make('recipients')
                    ->label('Destinatarios')
                    ->placeholder('Escriba un correo y pulse Enter')
                    ->helperText(fn (): string => $this->recipientsHelperText())
                    ->splitKeys(['Enter', 'Tab', ',', ' '])
                    ->trim()
                    ->required()
                    ->nestedRecursiveRules(['email:filter', 'max:150'])
                    ->rules(['array', 'max:'.CertificateDeliveryRecipients::MAX_RECIPIENTS])
                    ->validationMessages([
                        'required' => 'Indique al menos un correo.',
                        'max' => 'Puede enviar a un máximo de '.CertificateDeliveryRecipients::MAX_RECIPIENTS.' correos por envío.',
                        'email' => 'Uno de los correos no es válido.',
                    ]),
                Textarea::make('message')
                    ->label('Mensaje (opcional)')
                    ->placeholder('Se agrega al cuerpo del correo.')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (array $data, AffiliationCertificateDeliveryService $service): void {
                $issue = $this->currentIssue();
                $user = Auth::user();

                if ($issue === null || ! $user instanceof User || ! $this->canSendCurrentIssue()) {
                    Notification::make()->title('No se puede enviar este certificado')->body('Vuelva a generarlo e intente de nuevo.')->danger()->send();

                    return;
                }

                try {
                    $delivery = $service->request(
                        $issue,
                        (string) ($data['content'] ?? ''),
                        array_values((array) ($data['carnets'] ?? [])),
                        (array) ($data['recipients'] ?? []),
                        $data['message'] ?? null,
                        $user,
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('No se pudo enviar')->body($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('Envío en curso')
                    ->body('Se enviará a '.implode(', ', $delivery->recipients).'. Le avisaremos cuando salga.')
                    ->success()
                    ->send();
            });
    }

    protected function canSendCurrentIssue(): bool
    {
        $issue = $this->currentIssue();

        if ($issue === null || $issue->pdf_status === AffiliationCertificateIssue::PDF_FAILED) {
            return false;
        }

        return ! $issue->isQueued() || $issue->pdf_status === AffiliationCertificateIssue::PDF_READY;
    }

    /**
     * @return array<string, string>
     */
    protected function sendByEmailContentOptions(): array
    {
        $carnets = $this->carnetCount();
        $options = [
            AffiliationCertificateDelivery::CONTENT_FULL => $carnets > 0
                ? 'Documento completo (certificado y '.($carnets === 1 ? '1 carnet' : number_format($carnets, 0, ',', '.').' carnets').')'
                : 'Documento completo (certificado)',
        ];

        if ($carnets > 0) {
            $options[AffiliationCertificateDelivery::CONTENT_CERTIFICATE] = 'Solo el certificado (sin carnets)';
            $options[AffiliationCertificateDelivery::CONTENT_CARNETS] = 'Solo algunos carnets';
        }

        return $options;
    }

    protected function sendByEmailDescription(): string
    {
        $issue = $this->currentIssue();

        if ($issue === null) {
            return '';
        }

        return $issue->affiliation_code.' · clave '.$issue->verification_key.'. Si el PDF pesa más de 8 MB se envía un enlace de descarga válido por '.AffiliationCertificateDeliveryService::LINK_DAYS.' días en lugar del adjunto.';
    }

    protected function recipientsHelperText(): string
    {
        $issue = $this->currentIssue();
        $suggestions = $issue !== null ? CertificateDeliveryRecipients::suggestionsFor($issue) : [];

        if ($suggestions === []) {
            return 'La afiliación no tiene correos registrados: escriba al menos uno.';
        }

        return 'Precargados: '.implode(' · ', array_map(
            static fn (array $suggestion): string => $suggestion['label'].' ('.$suggestion['email'].')',
            $suggestions,
        )).'. Puede quitar o agregar correos (máximo '.CertificateDeliveryRecipients::MAX_RECIPIENTS.').';
    }

    protected function carnetCount(): int
    {
        return count($this->currentIssue()?->carnet_affiliate_ids ?? []);
    }

    /**
     * Etiquetas «Nombre · Cédula» de los afiliados con carnet en la emisión actual.
     *
     * @param  array<int, mixed>|null  $only
     * @return array<int, string>
     */
    protected function carnetLabels(?string $search = null, ?array $only = null): array
    {
        $issue = $this->currentIssue();
        $ids = array_map('intval', $issue?->carnet_affiliate_ids ?? []);

        if ($only !== null) {
            $ids = array_values(array_intersect($ids, array_map('intval', $only)));
        }

        if ($issue === null || $ids === []) {
            return [];
        }

        $term = trim((string) $search);

        if ($issue->isIndividual()) {
            return Affiliate::query()
                ->whereKey($ids)
                ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('full_name', 'like', '%'.$term.'%')
                    ->orWhere('nro_identificacion', 'like', '%'.$term.'%')))
                ->orderBy('full_name')
                ->limit(self::CARNET_OPTIONS_PRELOAD)
                ->get(['id', 'full_name', 'nro_identificacion'])
                ->mapWithKeys(fn (Affiliate $affiliate): array => [
                    (int) $affiliate->id => mb_strtoupper(trim((string) $affiliate->full_name)).' · '.CertificateFormat::document($affiliate->nro_identificacion),
                ])
                ->all();
        }

        return AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $issue->affiliation_id)
            ->whereKey($ids)
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('first_name', 'like', '%'.$term.'%')
                ->orWhere('last_name', 'like', '%'.$term.'%')
                ->orWhere('nro_identificacion', 'like', '%'.$term.'%')))
            ->orderBy('first_name')
            ->limit(self::CARNET_OPTIONS_PRELOAD)
            ->get(['id', 'first_name', 'last_name', 'nro_identificacion'])
            ->mapWithKeys(fn (AffiliateCorporate $affiliate): array => [
                (int) $affiliate->id => CorporateCertificateDocument::fullName($affiliate).' · '.CertificateFormat::document($affiliate->nro_identificacion),
            ])
            ->all();
    }
}
