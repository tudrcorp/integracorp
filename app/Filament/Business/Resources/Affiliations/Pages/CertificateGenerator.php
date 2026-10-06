<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliations\Pages;

use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AffiliationCertificateIssue;
use App\Models\User;
use App\Services\AffiliationCertificateGeneratorService;
use App\Support\Affiliations\Certificates\AffiliationCertificateAccess;
use App\Support\Affiliations\Certificates\CertificateFormat;
use App\Support\Affiliations\Certificates\IndividualCertificateDocument;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Throwable;

/**
 * «Generador de Certificado»: el analista elige la afiliación individual y qué
 * afiliados llevan carnet; el resto se llena solo desde la afiliación. El
 * resultado se ve en la misma página (el PDF real, no una imitación) y se descarga.
 */
class CertificateGenerator extends Page
{
    /** Estatus de afiliación que pueden certificarse. */
    public const CERTIFIABLE_STATUSES = ['ACTIVA', 'PRE-APROBADA'];

    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Generador de Certificado';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.business.resources.affiliations.pages.certificate-generator';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?int $issueId = null;

    public static function getRoutePath(Panel $panel): string
    {
        return '/generador-certificado';
    }

    public static function canAccess(array $parameters = []): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && AffiliationCertificateAccess::canUseFor($user, AffiliationCertificateIssue::TYPE_INDIVIDUAL);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $affiliationId = (int) request()->query('afiliacion', 0);
        $affiliation = $affiliationId > 0 ? self::certifiableQuery()->find($affiliationId) : null;

        $this->form->fill([
            'affiliation_id' => $affiliation?->id,
            'carnets' => $affiliation !== null ? self::printableIds($affiliation) : [],
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Generador de Certificado';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Elija la afiliación y los afiliados que llevan carnet. Plan, beneficios, vigencia y período pagado se toman de la afiliación.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->statePath('data')
            ->components([
                Section::make('Afiliación')
                    ->description('Solo afiliaciones activas o pre-aprobadas.')
                    ->icon(Heroicon::OutlinedDocumentCheck)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('affiliation_id')
                            ->label('Afiliación')
                            ->placeholder('Busque por código, titular, pagador o cédula')
                            ->searchable()
                            ->searchDebounce(350)
                            ->searchPrompt('Escriba al menos 2 caracteres del código, el nombre o la cédula')
                            ->noSearchResultsMessage('No hay afiliaciones activas que coincidan.')
                            ->getSearchResultsUsing(fn (string $search): array => self::searchAffiliations($search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => ($affiliation = self::certifiableQuery()->find((int) $value)) ? self::optionLabel($affiliation) : null)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $affiliation = $state ? self::certifiableQuery()->find((int) $state) : null;

                                $set('carnets', $affiliation !== null ? self::printableIds($affiliation) : []);
                                $this->issueId = null;
                            })
                            ->validationMessages([
                                'required' => 'Elija la afiliación que desea certificar.',
                            ]),
                        Placeholder::make('summary')
                            ->hiddenLabel()
                            ->visible(fn (Get $get): bool => filled($get('affiliation_id')))
                            ->content(fn (Get $get): HtmlString => self::summary((int) $get('affiliation_id'))),
                    ]),
                Section::make('Carnets')
                    ->description('Marque a quién se le imprime carnet. Use «Seleccionar todos» para el grupo completo.')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => filled($get('affiliation_id')))
                    ->schema([
                        CheckboxList::make('carnets')
                            ->hiddenLabel()
                            ->options(fn (Get $get): array => self::carnetOptions((int) $get('affiliation_id')))
                            ->bulkToggleable()
                            ->columns(2)
                            ->live()
                            ->afterStateUpdated(fn () => $this->issueId = null)
                            ->helperText('Si no marca ninguno, se genera solo el certificado.'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('certificate-generator-form')
                ->livewireSubmitHandler('generate')
                ->footer([
                    Actions::make([$this->generateAction()])->fullWidth(false),
                ]),
        ]);
    }

    protected function generateAction(): Action
    {
        return Action::make('generate')
            ->label('Generar certificado')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('success')
            ->submit('generate')
            ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('success')]);
    }

    public function generate(): void
    {
        $generator = app(AffiliationCertificateGeneratorService::class);
        $data = $this->form->getState();
        $user = Auth::user();
        $affiliation = self::certifiableQuery()->with(IndividualCertificateDocument::eagerLoads())->find((int) ($data['affiliation_id'] ?? 0));

        if (! $user instanceof User || $affiliation === null) {
            Notification::make()
                ->title('No se pudo generar el certificado')
                ->body('La afiliación ya no está activa o su sesión expiró. Recargue la página e intente de nuevo.')
                ->danger()
                ->send();

            return;
        }

        try {
            $issue = $generator->issueIndividual($affiliation, array_values((array) ($data['carnets'] ?? [])), $user);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('No se pudo generar el certificado')->body($exception->getMessage())->danger()->send();

            return;
        } catch (Throwable $exception) {
            Log::error('CertificateGenerator: error al emitir', ['affiliation_id' => $affiliation->id, 'message' => $exception->getMessage()]);

            Notification::make()
                ->title('No se pudo generar el certificado')
                ->body('Ocurrió un error inesperado. Intente de nuevo; si persiste, reporte a soporte.')
                ->danger()
                ->send();

            return;
        }

        $this->issueId = $issue->id;

        Notification::make()
            ->title('Certificado generado')
            ->body($issue->carnets_count > 0
                ? 'Certificado y '.$issue->carnets_count.' '.($issue->carnets_count === 1 ? 'carnet' : 'carnets').' listos. Revise la vista previa y descárguelo.'
                : 'Certificado listo. Revise la vista previa y descárguelo.')
            ->success()
            ->send();
    }

    public function previewUrl(): ?string
    {
        return $this->issueId !== null ? route('business.affiliation-certificate.pdf', ['issue' => $this->issueId]) : null;
    }

    public function downloadUrl(): ?string
    {
        return $this->issueId !== null ? route('business.affiliation-certificate.pdf', ['issue' => $this->issueId, 'descargar' => 1]) : null;
    }

    public function currentIssue(): ?AffiliationCertificateIssue
    {
        return $this->issueId !== null ? AffiliationCertificateIssue::query()->find($this->issueId) : null;
    }

    /**
     * @return Builder<Affiliation>
     */
    public static function certifiableQuery(): Builder
    {
        return Affiliation::query()->whereIn('status', self::CERTIFIABLE_STATUSES);
    }

    /**
     * @return array<int, string>
     */
    public static function searchAffiliations(string $search): array
    {
        $term = trim($search);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        return self::certifiableQuery()
            ->with('plan:id,description')
            ->where(fn (Builder $query): Builder => $query
                ->where('code', 'like', $like)
                ->orWhere('full_name_ti', 'like', $like)
                ->orWhere('nro_identificacion_ti', 'like', $like)
                ->orWhere('full_name_payer', 'like', $like)
                ->orWhere('nro_identificacion_payer', 'like', $like))
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'code', 'full_name_ti', 'full_name_payer', 'plan_id', 'status'])
            ->mapWithKeys(fn (Affiliation $affiliation): array => [$affiliation->id => self::optionLabel($affiliation)])
            ->all();
    }

    public static function optionLabel(Affiliation $affiliation): string
    {
        return collect([
            $affiliation->code,
            CertificateFormat::name($affiliation->full_name_ti ?: $affiliation->full_name_payer),
            $affiliation->plan?->description,
        ])->filter(fn (?string $part): bool => filled($part) && $part !== '—')->implode(' · ');
    }

    /**
     * @return list<int>
     */
    public static function printableIds(Affiliation $affiliation): array
    {
        return IndividualCertificateDocument::printableAffiliates($affiliation)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function carnetOptions(int $affiliationId): array
    {
        $affiliation = $affiliationId > 0 ? self::certifiableQuery()->with('affiliates')->find($affiliationId) : null;

        if ($affiliation === null) {
            return [];
        }

        return IndividualCertificateDocument::printableAffiliates($affiliation)
            ->mapWithKeys(fn (Affiliate $affiliate): array => [
                (int) $affiliate->id => CertificateFormat::name($affiliate->full_name)
                    .' · '.CertificateFormat::document($affiliate->nro_identificacion)
                    .' · '.CertificateFormat::relationship($affiliate->relationship),
            ])
            ->all();
    }

    public static function summary(int $affiliationId): HtmlString
    {
        $affiliation = $affiliationId > 0 ? self::certifiableQuery()->with(['plan:id,description', 'affiliates'])->find($affiliationId) : null;

        if ($affiliation === null) {
            return new HtmlString('');
        }

        $period = IndividualCertificateDocument::paymentPeriod($affiliation);

        return new HtmlString(view('filament.business.resources.affiliations.pages.certificate-generator-summary', [
            'planLabel' => (string) ($affiliation->plan?->description ?? 'Sin plan'),
            'statusLabel' => (string) $affiliation->status,
            'frequency' => (string) ($affiliation->payment_frequency ?: 'anual'),
            'period' => $period,
            'affiliatesCount' => IndividualCertificateDocument::printableAffiliates($affiliation)->count(),
        ])->render());
    }
}
