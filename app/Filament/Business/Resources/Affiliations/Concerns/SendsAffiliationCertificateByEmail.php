<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliations\Concerns;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCertificateDelivery;
use App\Models\AffiliationCertificateIssue;
use App\Models\User;
use App\Services\AffiliationCertificateDeliveryService;
use App\Support\Affiliations\Certificates\CertificateDeliveryParts;
use App\Support\Affiliations\Certificates\CertificateDeliveryRecipients;
use App\Support\Affiliations\Certificates\CertificateDeliveryRememberedCopies;
use App\Support\Affiliations\Certificates\CertificateFormat;
use App\Support\Affiliations\Certificates\CorporateCertificateDocument;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * «Enviar por correo» del Generador de Certificado (individual y corporativo):
 * el documento completo o solo los carnets elegidos, con los correos del dueño
 * de la afiliación y de la propia afiliación ya cargados, más correos testigos
 * (CC) y copias ocultas (CCO). La copia oculta fija no se puede quitar.
 *
 * Requiere que la página tenga `currentIssue()`.
 */
trait SendsAffiliationCertificateByEmail
{
    /** Hasta este número de carnets la lista se carga completa; por encima se busca. */
    private const CARNET_OPTIONS_PRELOAD = 100;

    private const CARNETS_SCOPE_ALL = 'all';

    private const CARNETS_SCOPE_SOME = 'some';

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
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Enviar')
            ->modalCancelActionLabel('Cancelar')
            ->fillForm(function (): array {
                $issue = $this->currentIssue();
                $rememberedCc = CertificateDeliveryRememberedCopies::for($this->sendByEmailUser(), CertificateDeliveryRememberedCopies::KIND_CC);
                $rememberedBcc = CertificateDeliveryRememberedCopies::for($this->sendByEmailUser(), CertificateDeliveryRememberedCopies::KIND_BCC);

                return [
                    'parts' => $this->sendByEmailAvailableParts(),
                    'carnets_scope' => self::CARNETS_SCOPE_ALL,
                    'carnets' => [],
                    'recipients' => $issue !== null ? CertificateDeliveryRecipients::defaultsFor($issue) : [],
                    'cc_recipients' => $rememberedCc,
                    'remember_cc' => $rememberedCc !== [],
                    'bcc_recipients' => CertificateDeliveryRecipients::withMandatoryBcc($rememberedBcc),
                    'remember_bcc' => $rememberedBcc !== [],
                    'message' => null,
                ];
            })
            ->schema([
                Section::make('¿Qué desea enviar?')
                    ->description(fn (Get $get): string => $this->sendByEmailPartsSummary((array) ($get('parts') ?? [])))
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->compact()
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('parts')
                            ->hiddenLabel()
                            ->options(fn (): array => ($issue = $this->currentIssue()) !== null ? CertificateDeliveryParts::optionsFor($issue) : [])
                            ->descriptions(fn (): array => ($issue = $this->currentIssue()) !== null ? CertificateDeliveryParts::descriptionsFor($issue) : [])
                            ->columns(3)
                            ->visible(fn (): bool => count($this->sendByEmailAvailableParts()) > 1)
                            ->required()
                            ->live()
                            ->validationMessages(['required' => 'Marque al menos una parte del documento.']),
                        Radio::make('carnets_scope')
                            ->label('¿Qué carnets?')
                            ->options([
                                self::CARNETS_SCOPE_ALL => 'Todos los carnets',
                                self::CARNETS_SCOPE_SOME => 'Elegir carnets',
                            ])
                            ->inline()
                            ->live()
                            ->visible(fn (Get $get): bool => $this->sendByEmailIncludesCarnets($get) && $this->carnetCount() > 1),
                        Select::make('carnets')
                            ->label('Carnets a enviar')
                            ->helperText('Hasta '.AffiliationCertificateDeliveryService::MAX_CARNETS_PER_DELIVERY.' carnets elegidos uno a uno.')
                            ->placeholder('Busque por nombre o cédula')
                            ->multiple()
                            ->searchable()
                            ->options(fn (): array => $this->carnetCount() <= self::CARNET_OPTIONS_PRELOAD ? $this->carnetLabels() : [])
                            ->getSearchResultsUsing(fn (string $search): array => $this->carnetLabels($search))
                            ->getOptionLabelsUsing(fn (array $values): array => $this->carnetLabels(null, $values))
                            ->maxItems(AffiliationCertificateDeliveryService::MAX_CARNETS_PER_DELIVERY)
                            ->visible(fn (Get $get): bool => $this->sendByEmailChoosesCarnets($get))
                            ->required(fn (Get $get): bool => $this->sendByEmailChoosesCarnets($get))
                            ->validationMessages([
                                'required' => 'Elija al menos un carnet o marque «Todos los carnets».',
                                'max' => 'Puede elegir hasta '.AffiliationCertificateDeliveryService::MAX_CARNETS_PER_DELIVERY.' carnets uno a uno.',
                            ]),
                    ]),
                Section::make('Destinatarios')
                    ->icon(Heroicon::OutlinedUsers)
                    ->compact()
                    ->columnSpanFull()
                    ->schema([
                        TagsInput::make('recipients')
                            ->hiddenLabel()
                            ->placeholder('Escriba un correo y pulse Enter')
                            ->helperText(fn (): string => $this->recipientsHelperText())
                            ->splitKeys(['Enter', 'Tab', ',', ' '])
                            ->trim()
                            ->required()
                            ->nestedRecursiveRules(['email:filter', 'max:150'])
                            ->rules(['array', 'max:'.CertificateDeliveryRecipients::MAX_RECIPIENTS])
                            ->validationAttribute('destinatarios')
                            ->validationMessages([
                                'required' => 'Indique al menos un correo.',
                                'max' => 'Puede enviar a un máximo de '.CertificateDeliveryRecipients::MAX_RECIPIENTS.' correos por envío.',
                                'email' => 'Uno de los correos no es válido.',
                            ]),
                    ]),
                Section::make('Copias y mensaje')
                    ->description(fn (Get $get): string => $this->sendByEmailCopiesSummary($get))
                    ->icon(Heroicon::OutlinedEnvelopeOpen)
                    ->compact()
                    ->collapsible()
                    ->collapsed(fn (): bool => ! $this->sendByEmailHasRememberedCopies())
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Group::make([
                            TagsInput::make('cc_recipients')
                                ->label('Testigos (CC)')
                                ->placeholder('Correo y Enter')
                                ->helperText('Los destinatarios verán estos correos.')
                                ->splitKeys(['Enter', 'Tab', ',', ' '])
                                ->trim()
                                ->live()
                                ->nestedRecursiveRules(['email:filter', 'max:150'])
                                ->rules(['array', 'max:'.CertificateDeliveryRecipients::MAX_COPIES])
                                ->validationMessages([
                                    'max' => 'Puede agregar hasta '.CertificateDeliveryRecipients::MAX_COPIES.' correos testigos.',
                                    'email' => 'Uno de los correos testigos no es válido.',
                                ]),
                            Toggle::make('remember_cc')
                                ->label('Recordar para mis próximos envíos'),
                        ]),
                        Group::make([
                            TagsInput::make('bcc_recipients')
                                ->label('Copia oculta (CCO)')
                                ->placeholder('Correo y Enter')
                                ->helperText(implode(', ', CertificateDeliveryRecipients::MANDATORY_BCC).' va siempre y no se puede quitar.')
                                ->splitKeys(['Enter', 'Tab', ',', ' '])
                                ->trim()
                                ->live()
                                ->afterStateUpdated(function (mixed $state, Set $set): void {
                                    $set('bcc_recipients', CertificateDeliveryRecipients::withMandatoryBcc((array) ($state ?? [])));
                                })
                                ->nestedRecursiveRules(['email:filter', 'max:150'])
                                ->rules(['array', 'max:'.CertificateDeliveryRecipients::MAX_COPIES])
                                ->validationMessages([
                                    'max' => 'Puede agregar hasta '.CertificateDeliveryRecipients::MAX_COPIES.' copias ocultas, contando la fija.',
                                    'email' => 'Uno de los correos de copia oculta no es válido.',
                                ]),
                            Toggle::make('remember_bcc')
                                ->label('Recordar las que agregué'),
                        ]),
                        Textarea::make('message')
                            ->label('Mensaje (opcional)')
                            ->placeholder('Se agrega al cuerpo del correo.')
                            ->rows(2)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
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
                        array_values((array) ($data['parts'] ?? $this->sendByEmailAvailableParts())),
                        ($data['carnets_scope'] ?? self::CARNETS_SCOPE_ALL) === self::CARNETS_SCOPE_SOME
                            ? array_values((array) ($data['carnets'] ?? []))
                            : null,
                        (array) ($data['recipients'] ?? []),
                        $data['message'] ?? null,
                        $user,
                        (array) ($data['cc_recipients'] ?? []),
                        (array) ($data['bcc_recipients'] ?? []),
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('No se pudo enviar')->body($exception->getMessage())->danger()->send();

                    return;
                }

                $this->rememberSendByEmailCopies($user, CertificateDeliveryRememberedCopies::KIND_CC, (array) ($data['cc_recipients'] ?? []), (bool) ($data['remember_cc'] ?? false));
                $this->rememberSendByEmailCopies($user, CertificateDeliveryRememberedCopies::KIND_BCC, (array) ($data['bcc_recipients'] ?? []), (bool) ($data['remember_bcc'] ?? false));

                Notification::make()
                    ->title('Envío en curso')
                    ->body($this->sendByEmailQueuedBody($delivery))
                    ->success()
                    ->send();
            });
    }

    protected function sendByEmailUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Guarda u olvida las copias recordadas (CC o CCO). Si falla, el envío ya
     * quedó en cola: solo se registra, sin interrumpir al analista.
     *
     * @param  list<mixed>  $emails
     */
    protected function rememberSendByEmailCopies(User $user, string $kind, array $emails, bool $remember): void
    {
        try {
            CertificateDeliveryRememberedCopies::update($user, $kind, $emails, $remember);
        } catch (Throwable $exception) {
            Log::warning('Generador de certificado: no se pudieron recordar las copias del correo', [
                'user_id' => $user->id,
                'kind' => $kind,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function sendByEmailQueuedBody(AffiliationCertificateDelivery $delivery): string
    {
        $body = 'Se enviará a '.implode(', ', $delivery->recipients ?? []).'.';

        if (($delivery->cc_recipients ?? []) !== []) {
            $body .= ' Con copia a '.implode(', ', $delivery->cc_recipients).'.';
        }

        if (($delivery->bcc_recipients ?? []) !== []) {
            $body .= ' Copia oculta a '.implode(', ', $delivery->bcc_recipients).'.';
        }

        return $body.' Le avisaremos cuando salga.';
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
     * @return list<string>
     */
    protected function sendByEmailAvailableParts(): array
    {
        $issue = $this->currentIssue();

        return $issue !== null ? CertificateDeliveryParts::availableFor($issue) : [];
    }

    /** Con testigos o copias ocultas recordadas, la sección de copias se abre de entrada. */
    protected function sendByEmailHasRememberedCopies(): bool
    {
        $user = $this->sendByEmailUser();

        return CertificateDeliveryRememberedCopies::for($user, CertificateDeliveryRememberedCopies::KIND_CC) !== []
            || CertificateDeliveryRememberedCopies::for($user, CertificateDeliveryRememberedCopies::KIND_BCC) !== [];
    }

    protected function sendByEmailIncludesCarnets(Get $get): bool
    {
        return in_array(CertificateDeliveryParts::CARNETS, (array) ($get('parts') ?? $this->sendByEmailAvailableParts()), true);
    }

    protected function sendByEmailChoosesCarnets(Get $get): bool
    {
        return $this->sendByEmailIncludesCarnets($get)
            && $this->carnetCount() > 1
            && $get('carnets_scope') === self::CARNETS_SCOPE_SOME;
    }

    /**
     * @param  list<mixed>  $parts
     */
    protected function sendByEmailPartsSummary(array $parts): string
    {
        $issue = $this->currentIssue();

        if ($issue === null) {
            return '';
        }

        if (count(CertificateDeliveryParts::availableFor($issue)) === 1) {
            return 'Esta emisión solo tiene el certificado: se envía completo.';
        }

        $chosen = CertificateDeliveryParts::clean($parts, $issue);

        if ($chosen === []) {
            return 'Marque al menos una parte.';
        }

        return $chosen === CertificateDeliveryParts::availableFor($issue)
            ? 'Todo junto: se envía el documento completo.'
            : 'Se arma un PDF solo con lo marcado.';
    }

    protected function sendByEmailCopiesSummary(Get $get): string
    {
        $cc = count((array) ($get('cc_recipients') ?? []));
        $bcc = count((array) ($get('bcc_recipients') ?? []));

        return implode(' · ', array_filter([
            $cc > 0 ? ($cc === 1 ? '1 testigo' : $cc.' testigos') : 'Sin testigos',
            $bcc === 1 ? '1 copia oculta' : $bcc.' copias ocultas',
        ]));
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

        return 'Precargados: '.implode(' · ', array_column($suggestions, 'label')).'. Puede quitar o agregar (máximo '.CertificateDeliveryRecipients::MAX_RECIPIENTS.').';
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
