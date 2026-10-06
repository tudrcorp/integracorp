<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporates\Pages;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCertificateIssue;
use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Services\AffiliationCertificateGeneratorService;
use App\Support\AffiliationCorporates\CorporateAffiliationContractedPlan;
use App\Support\Affiliations\Certificates\AffiliationCertificateAccess;
use App\Support\Affiliations\Certificates\CertificateFormat;
use App\Support\Affiliations\Certificates\CorporateCertificateDocument;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
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
 * «Generador de Certificado» de afiliaciones corporativas.
 *
 * Un colectivo puede tener miles de afiliados: los carnets se eligen con «todos»,
 * con un buscador o ninguno (una lista de casillas con 2.700 opciones no sirve), y
 * los documentos grandes se dibujan en cola con aviso al terminar.
 */
class CorporateCertificateGenerator extends Page
{
    public const CERTIFIABLE_STATUSES = ['ACTIVA', 'PRE-APROBADA'];

    public const MODE_ALL = 'all';

    public const MODE_PICK = 'pick';

    public const MODE_NONE = 'none';

    protected static string $resource = AffiliationCorporateResource::class;

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
            && AffiliationCertificateAccess::canUseFor($user, AffiliationCertificateIssue::TYPE_CORPORATE);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $affiliationId = (int) request()->query('afiliacion', 0);

        $this->form->fill([
            'affiliation_id' => $affiliationId > 0 && self::certifiableQuery()->whereKey($affiliationId)->exists() ? $affiliationId : null,
            'carnet_mode' => self::MODE_ALL,
            'carnets' => [],
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Generador de Certificado';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Elija la afiliación corporativa y a quién se le imprime carnet. Planes, beneficios, vigencia y período pagado se toman del colectivo.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->statePath('data')
            ->components([
                Section::make('Afiliación corporativa')
                    ->description('Solo afiliaciones activas o pre-aprobadas.')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('affiliation_id')
                            ->label('Afiliación')
                            ->placeholder('Busque por código, empresa o RIF')
                            ->searchable()
                            ->searchDebounce(350)
                            ->searchPrompt('Escriba al menos 2 caracteres del código, la empresa o el RIF')
                            ->noSearchResultsMessage('No hay afiliaciones corporativas activas que coincidan.')
                            ->getSearchResultsUsing(fn (string $search): array => self::searchAffiliations($search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => ($affiliation = self::certifiableQuery()->find((int) $value)) ? self::optionLabel($affiliation) : null)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('carnet_mode', self::MODE_ALL);
                                $set('carnets', []);
                                $this->issueId = null;
                            })
                            ->validationMessages(['required' => 'Elija la afiliación que desea certificar.']),
                        Placeholder::make('summary')
                            ->hiddenLabel()
                            ->visible(fn (Get $get): bool => filled($get('affiliation_id')))
                            ->content(fn (Get $get): HtmlString => self::summary((int) $get('affiliation_id'))),
                    ]),
                Section::make('Carnets')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => filled($get('affiliation_id')))
                    ->schema([
                        Radio::make('carnet_mode')
                            ->hiddenLabel()
                            ->options(fn (Get $get): array => [
                                self::MODE_ALL => 'Todos los afiliados vigentes ('.number_format(self::printableCount((int) $get('affiliation_id')), 0, ',', '.').')',
                                self::MODE_PICK => 'Elegir afiliados',
                                self::MODE_NONE => 'Solo el certificado, sin carnets',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->issueId = null),
                        Select::make('carnets')
                            ->label('Afiliados con carnet')
                            ->placeholder('Busque por nombre o cédula')
                            ->multiple()
                            ->searchable()
                            ->searchDebounce(350)
                            ->getSearchResultsUsing(fn (string $search, Get $get): array => self::searchAffiliates((int) $get('affiliation_id'), $search))
                            ->getOptionLabelsUsing(fn (array $values, Get $get): array => self::affiliateLabels((int) $get('affiliation_id'), $values))
                            ->visible(fn (Get $get): bool => $get('carnet_mode') === self::MODE_PICK)
                            ->required(fn (Get $get): bool => $get('carnet_mode') === self::MODE_PICK)
                            ->live()
                            ->afterStateUpdated(fn () => $this->issueId = null)
                            ->validationMessages(['required' => 'Elija al menos un afiliado o cambie a «Solo el certificado».']),
                        Placeholder::make('queue_hint')
                            ->hiddenLabel()
                            ->visible(fn (Get $get): bool => self::wouldQueue((int) $get('affiliation_id'), (string) $get('carnet_mode'), (array) $get('carnets')))
                            ->content(new HtmlString('<p class="text-sm text-warning-600 dark:text-warning-400">Es un documento grande: se preparará en segundo plano y le llegará una notificación con el enlace de descarga.</p>')),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('corporate-certificate-generator-form')
                ->livewireSubmitHandler('generate')
                ->footer([
                    Actions::make([
                        Action::make('generate')
                            ->label('Generar certificado')
                            ->icon(Heroicon::OutlinedSparkles)
                            ->color('success')
                            ->submit('generate')
                            ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('success')]),
                    ])->fullWidth(false),
                ]),
        ]);
    }

    public function generate(): void
    {
        $data = $this->form->getState();
        $user = Auth::user();
        $affiliation = self::certifiableQuery()->find((int) ($data['affiliation_id'] ?? 0));

        if (! $user instanceof User || $affiliation === null) {
            Notification::make()
                ->title('No se pudo generar el certificado')
                ->body('La afiliación ya no está activa o su sesión expiró. Recargue la página e intente de nuevo.')
                ->danger()
                ->send();

            return;
        }

        $carnetIds = match ($data['carnet_mode'] ?? self::MODE_ALL) {
            self::MODE_ALL => CorporateCertificateDocument::printableAffiliates($affiliation)->pluck('id')->all(),
            self::MODE_PICK => array_values((array) ($data['carnets'] ?? [])),
            default => [],
        };

        try {
            $issue = app(AffiliationCertificateGeneratorService::class)->issueCorporate($affiliation, $carnetIds, $user);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('No se pudo generar el certificado')->body($exception->getMessage())->danger()->send();

            return;
        } catch (Throwable $exception) {
            Log::error('CorporateCertificateGenerator: error al emitir', ['affiliation_corporate_id' => $affiliation->id, 'message' => $exception->getMessage()]);

            Notification::make()
                ->title('No se pudo generar el certificado')
                ->body('Ocurrió un error inesperado. Intente de nuevo; si persiste, reporte a soporte.')
                ->danger()
                ->send();

            return;
        }

        $this->issueId = $issue->id;

        Notification::make()
            ->title($issue->isQueued() ? 'Generando el certificado' : 'Certificado generado')
            ->body($issue->isQueued()
                ? 'Son '.number_format($issue->carnets_count, 0, ',', '.').' carnets: se prepara en segundo plano y le avisaremos cuando esté listo.'
                : 'Revise la vista previa y descárguelo.')
            ->success()
            ->send();
    }

    public function previewUrl(): ?string
    {
        $issue = $this->currentIssue();

        if ($issue === null || ($issue->isQueued() && $issue->pdf_status !== AffiliationCertificateIssue::PDF_READY)) {
            return null;
        }

        return route('business.affiliation-certificate.pdf', ['issue' => $issue->id]);
    }

    public function downloadUrl(): ?string
    {
        return $this->previewUrl() !== null ? route('business.affiliation-certificate.pdf', ['issue' => $this->issueId, 'descargar' => 1]) : null;
    }

    public function currentIssue(): ?AffiliationCertificateIssue
    {
        return $this->issueId !== null ? AffiliationCertificateIssue::query()->find($this->issueId) : null;
    }

    /**
     * @return Builder<AffiliationCorporate>
     */
    public static function certifiableQuery(): Builder
    {
        return AffiliationCorporate::query()->whereIn('status', self::CERTIFIABLE_STATUSES);
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
            ->where(fn (Builder $query): Builder => $query
                ->where('code', 'like', $like)
                ->orWhere('name_corporate', 'like', $like)
                ->orWhere('rif', 'like', $like))
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'code', 'name_corporate', 'status'])
            ->mapWithKeys(fn (AffiliationCorporate $affiliation): array => [$affiliation->id => self::optionLabel($affiliation)])
            ->all();
    }

    public static function optionLabel(AffiliationCorporate $affiliation): string
    {
        return collect([$affiliation->code, trim((string) $affiliation->name_corporate)])
            ->filter(fn (?string $part): bool => filled($part))
            ->implode(' · ');
    }

    public static function printableCount(int $affiliationId): int
    {
        return $affiliationId > 0
            ? AffiliateCorporate::query()->where('affiliation_corporate_id', $affiliationId)->whereIn('status', CorporateCertificateDocument::PRINTABLE_STATUSES)->count()
            : 0;
    }

    /**
     * @return array<int, string>
     */
    public static function searchAffiliates(int $affiliationId, string $search): array
    {
        $term = trim($search);

        if ($affiliationId < 1 || mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        return AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $affiliationId)
            ->whereIn('status', CorporateCertificateDocument::PRINTABLE_STATUSES)
            ->where(fn (Builder $query): Builder => $query
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('nro_identificacion', 'like', $like))
            ->orderBy('first_name')
            ->limit(50)
            ->get(['id', 'first_name', 'last_name', 'nro_identificacion'])
            ->mapWithKeys(fn (AffiliateCorporate $affiliate): array => [(int) $affiliate->id => self::affiliateLabel($affiliate)])
            ->all();
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    public static function affiliateLabels(int $affiliationId, array $values): array
    {
        return AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $affiliationId)
            ->whereKey(array_map('intval', $values))
            ->get(['id', 'first_name', 'last_name', 'nro_identificacion'])
            ->mapWithKeys(fn (AffiliateCorporate $affiliate): array => [(int) $affiliate->id => self::affiliateLabel($affiliate)])
            ->all();
    }

    public static function affiliateLabel(AffiliateCorporate $affiliate): string
    {
        return CorporateCertificateDocument::fullName($affiliate).' · '.CertificateFormat::document($affiliate->nro_identificacion);
    }

    /**
     * @param  array<int, mixed>  $picked
     */
    public static function wouldQueue(int $affiliationId, string $mode, array $picked): bool
    {
        if ($affiliationId < 1) {
            return false;
        }

        $affiliates = self::printableCount($affiliationId);
        $carnets = match ($mode) {
            self::MODE_ALL => $affiliates,
            self::MODE_PICK => count($picked),
            default => 0,
        };

        return AffiliationCertificateGeneratorService::estimatedPages($affiliates, $carnets) > AffiliationCertificateGeneratorService::SYNC_MAX_PAGES;
    }

    public static function summary(int $affiliationId): HtmlString
    {
        $affiliation = $affiliationId > 0 ? self::certifiableQuery()->with('affiliationCorporatePlans.plan')->find($affiliationId) : null;

        if ($affiliation === null) {
            return new HtmlString('');
        }

        return new HtmlString(view('filament.business.resources.affiliations.pages.certificate-generator-summary', [
            'planLabel' => (string) (CorporateAffiliationContractedPlan::plan($affiliation)?->description ?? 'Sin plan'),
            'statusLabel' => (string) $affiliation->status,
            'frequency' => (string) ($affiliation->payment_frequency ?: 'anual'),
            'period' => CorporateCertificateDocument::paymentPeriod($affiliation),
            'affiliatesCount' => self::printableCount($affiliationId),
        ])->render());
    }
}
