<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Support\Affiliations\MergeIndividualAffiliationsException;
use App\Support\Affiliations\MergeIndividualAffiliationsRequest;
use App\Support\Affiliations\MergeIndividualAffiliationsService;
use App\Support\Filament\UserNavigationAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

final class MergeFamilyGroupAction
{
    public static function make(): Action
    {
        return Action::make('mergeFamilyGroup')
            ->label('Unificar grupo familiar')
            ->icon(Heroicon::OutlinedUserGroup)
            ->color('danger')
            ->modalHeading('Unificar grupo familiar')
            ->modalDescription('Esta póliza sobrevive. Las otras pasan a EXCLUIDO: no se borran. Ventas y comisiones ya emitidas se conservan.')
            ->modalSubmitActionLabel('Unificar ahora')
            ->modalCancelActionLabel('Cancelar')
            ->modalWidth(Width::FiveExtraLarge)
            ->visible(function (?Affiliation $record): bool {
                $user = Auth::user();

                return $user !== null
                    && UserNavigationAccess::isSuperAdmin($user)
                    && $record instanceof Affiliation
                    && ! in_array(strtoupper((string) $record->status), ['EXCLUIDO', 'EXCLUIDA'], true);
            })
            ->authorize(function (): bool {
                $user = Auth::user();

                return $user !== null && UserNavigationAccess::isSuperAdmin($user);
            })
            ->fillForm(function (Affiliation $record): array {
                $members = self::membersFromAffiliationIds($record, []);
                $titularId = self::defaultTitularAffiliateId($record, $members);

                return [
                    'source_affiliation_ids' => [],
                    'titular_affiliate_id' => $titularId,
                    'members' => self::withTitularRelationship($members, $titularId),
                    'reason' => '',
                    'confirmed' => false,
                ];
            })
            ->steps([
                Step::make('Pólizas')
                    ->description('Elegir las afiliaciones a unir')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->schema([
                        Section::make('Afiliaciones origen')
                            ->description('Busque por código, titular o cédula. Esta póliza (la que está viendo) es la que sobrevive.')
                            ->icon(Heroicon::OutlinedRectangleStack)
                            ->schema([
                                Select::make('source_affiliation_ids')
                                    ->label('Afiliaciones a unir en esta póliza')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->minItems(1)
                                    ->native(false)
                                    ->live()
                                    ->getSearchResultsUsing(
                                        function (string $search, Affiliation $record): array {
                                            $term = trim($search);

                                            if (mb_strlen($term) < 2) {
                                                return [];
                                            }

                                            return Affiliation::query()
                                                ->whereKeyNot($record->id)
                                                ->whereNotIn('status', ['EXCLUIDO', 'EXCLUIDA'])
                                                ->where(function ($query) use ($term): void {
                                                    $query->where('code', 'like', '%'.$term.'%')
                                                        ->orWhere('full_name_ti', 'like', '%'.$term.'%')
                                                        ->orWhere('nro_identificacion_ti', 'like', '%'.$term.'%');
                                                })
                                                ->orderByDesc('id')
                                                ->limit(30)
                                                ->get(['id', 'code', 'full_name_ti', 'nro_identificacion_ti', 'status'])
                                                ->mapWithKeys(fn (Affiliation $affiliation): array => [
                                                    $affiliation->id => self::affiliationOptionLabel($affiliation),
                                                ])
                                                ->all();
                                        }
                                    )
                                    ->getOptionLabelsUsing(
                                        function (array $values): array {
                                            if ($values === []) {
                                                return [];
                                            }

                                            return Affiliation::query()
                                                ->whereIn('id', $values)
                                                ->get(['id', 'code', 'full_name_ti', 'nro_identificacion_ti', 'status'])
                                                ->mapWithKeys(fn (Affiliation $affiliation): array => [
                                                    $affiliation->id => self::affiliationOptionLabel($affiliation),
                                                ])
                                                ->all();
                                        }
                                    )
                                    ->afterStateUpdated(function (Set $set, Get $get, Affiliation $record, mixed $state): void {
                                        $sourceIds = array_values(array_filter(
                                            array_map(static fn (mixed $id): int => (int) $id, is_array($state) ? $state : []),
                                        ));
                                        $members = self::membersFromAffiliationIds($record, $sourceIds);
                                        $titularId = (int) ($get('titular_affiliate_id') ?: 0);

                                        if ($titularId === 0 || ! self::membersContainAffiliate($members, $titularId)) {
                                            $titularId = self::defaultTitularAffiliateId($record, $members);
                                        }

                                        $set('titular_affiliate_id', $titularId ?: null);
                                        $set('members', self::withTitularRelationship($members, $titularId));
                                    })
                                    ->helperText('Solo aparecen pólizas individuales no excluidas.')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
                Step::make('Parentescos')
                    ->description('Titular y grupo familiar')
                    ->icon(Heroicon::OutlinedUsers)
                    ->schema([
                        Section::make('Quién será el titular')
                            ->schema([
                                Select::make('titular_affiliate_id')
                                    ->label('Titular de la póliza unificada')
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->options(fn (Get $get): array => self::titularOptions($get('members') ?? []))
                                    ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                        $set(
                                            'members',
                                            self::withTitularRelationship($get('members') ?? [], (int) $state),
                                        );
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                        Section::make('Personas del grupo')
                            ->description('Asigne el parentesco de cada persona. Solo una puede ser TITULAR.')
                            ->schema([
                                Repeater::make('members')
                                    ->label('Grupo familiar')
                                    ->schema([
                                        Hidden::make('affiliate_id'),
                                        TextInput::make('full_name')
                                            ->label('Nombre')
                                            ->disabled()
                                            ->dehydrated(),
                                        TextInput::make('nro_identificacion')
                                            ->label('Cédula')
                                            ->disabled()
                                            ->dehydrated(),
                                        TextInput::make('from_code')
                                            ->label('Póliza origen')
                                            ->disabled()
                                            ->dehydrated(),
                                        Select::make('relationship')
                                            ->label('Parentesco')
                                            ->options(MergeIndividualAffiliationsService::relationshipOptions())
                                            ->required()
                                            ->native(false),
                                    ])
                                    ->columns(4)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
                Step::make('Confirmar')
                    ->description('Revisar el impacto y confirmar')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->schema([
                        Placeholder::make('preview')
                            ->label('Vista previa (sin escribir todavía)')
                            ->content(function (Get $get, Affiliation $record): HtmlString {
                                try {
                                    $request = self::requestFromForm($record, $get, forPreview: true);
                                    $preview = app(MergeIndividualAffiliationsService::class)->preview($request);

                                    return $preview->toHtml();
                                } catch (MergeIndividualAffiliationsException $exception) {
                                    return new HtmlString(
                                        '<p class="text-sm text-red-700 dark:text-red-300">'.e($exception->getMessage()).'</p>'
                                    );
                                } catch (\Throwable $exception) {
                                    return new HtmlString(
                                        '<p class="text-sm text-red-700 dark:text-red-300">No se pudo armar la vista previa: '.e($exception->getMessage()).'</p>'
                                    );
                                }
                            })
                            ->columnSpanFull(),
                        Textarea::make('reason')
                            ->label('Motivo de la unificación')
                            ->required()
                            ->minLength(8)
                            ->maxLength(2000)
                            ->rows(3)
                            ->placeholder('Ejemplo: familia cargada como cuatro pólizas individuales; se consolida el grupo en la póliza del titular.')
                            ->columnSpanFull(),
                        Checkbox::make('confirmed')
                            ->label('Revisé el preview. Entiendo que las pólizas origen pasarán a EXCLUIDO, no se borrarán, y las cuotas POR PAGAR de esas pólizas se cancelarán.')
                            ->accepted()
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (Affiliation $record, array $data): void {
                try {
                    $request = self::requestFromArray($record, $data);
                    $result = app(MergeIndividualAffiliationsService::class)->execute($request);
                } catch (MergeIndividualAffiliationsException $exception) {
                    Notification::make()
                        ->title('No se unificó el grupo familiar')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Grupo familiar unificado')
                    ->body(
                        'La póliza '.$result->targetCode.' quedó con '.$result->newFamilyMembers
                        .' persona(s). Se excluyeron: '.implode(', ', $result->excludedCodes)
                        .'. El certificado y los carnets se regeneran en cola.'
                    )
                    ->success()
                    ->persistent()
                    ->send();

                $record->refresh();
                $record->load(['affiliates', 'affiliationObservations', 'status_log_affiliations']);
            });
    }

    public static function affiliationOptionLabel(Affiliation $affiliation): string
    {
        return trim($affiliation->code.' — '.$affiliation->full_name_ti.' ('.$affiliation->nro_identificacion_ti.') · '.$affiliation->status);
    }

    /**
     * @param  list<int>  $sourceAffiliationIds
     * @return list<array{affiliate_id: int, full_name: string, nro_identificacion: string, from_code: string, relationship: string}>
     */
    public static function membersFromAffiliationIds(Affiliation $target, array $sourceAffiliationIds): array
    {
        $ids = array_values(array_unique([
            (int) $target->id,
            ...array_map(static fn (mixed $id): int => (int) $id, $sourceAffiliationIds),
        ]));

        $affiliations = Affiliation::query()
            ->whereIn('id', $ids)
            ->with(['affiliates' => fn ($query) => $query->orderByRaw("CASE WHEN relationship = 'TITULAR' THEN 0 ELSE 1 END")->orderBy('id')])
            ->get()
            ->keyBy('id');

        $members = [];

        foreach ($ids as $affiliationId) {
            $affiliation = $affiliations->get($affiliationId);

            if (! $affiliation instanceof Affiliation) {
                continue;
            }

            foreach ($affiliation->affiliates as $affiliate) {
                if (! $affiliate instanceof Affiliate) {
                    continue;
                }

                $members[] = [
                    'affiliate_id' => (int) $affiliate->id,
                    'full_name' => (string) $affiliate->full_name,
                    'nro_identificacion' => (string) $affiliate->nro_identificacion,
                    'from_code' => (string) $affiliation->code,
                    'relationship' => MergeIndividualAffiliationsService::normalizeRelationship((string) $affiliate->relationship),
                ];
            }
        }

        return $members;
    }

    /**
     * @param  list<array<string, mixed>>  $members
     * @return list<array<string, mixed>>
     */
    public static function withTitularRelationship(array $members, int $titularAffiliateId): array
    {
        foreach ($members as $index => $member) {
            $affiliateId = (int) ($member['affiliate_id'] ?? 0);

            if ($affiliateId === $titularAffiliateId) {
                $members[$index]['relationship'] = 'TITULAR';

                continue;
            }

            if (MergeIndividualAffiliationsService::normalizeRelationship((string) ($member['relationship'] ?? '')) === 'TITULAR') {
                $members[$index]['relationship'] = 'OTRO';
            }
        }

        return $members;
    }

    /**
     * @param  list<array<string, mixed>>  $members
     */
    public static function defaultTitularAffiliateId(Affiliation $target, array $members): int
    {
        $targetCode = (string) $target->code;

        foreach ($members as $member) {
            if (
                ($member['from_code'] ?? '') === $targetCode
                && MergeIndividualAffiliationsService::normalizeRelationship((string) ($member['relationship'] ?? '')) === 'TITULAR'
            ) {
                return (int) ($member['affiliate_id'] ?? 0);
            }
        }

        return (int) ($members[0]['affiliate_id'] ?? 0);
    }

    /**
     * @param  list<array<string, mixed>>  $members
     * @return array<int, string>
     */
    public static function titularOptions(array $members): array
    {
        $options = [];

        foreach ($members as $member) {
            $id = (int) ($member['affiliate_id'] ?? 0);

            if ($id === 0) {
                continue;
            }

            $options[$id] = trim(($member['full_name'] ?? '').' — '.($member['nro_identificacion'] ?? '').' ('.($member['from_code'] ?? '').')');
        }

        return $options;
    }

    /**
     * @param  list<array<string, mixed>>  $members
     */
    public static function membersContainAffiliate(array $members, int $affiliateId): bool
    {
        foreach ($members as $member) {
            if ((int) ($member['affiliate_id'] ?? 0) === $affiliateId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function requestFromArray(Affiliation $record, array $data, bool $forPreview = false): MergeIndividualAffiliationsRequest
    {
        $sourceIds = array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            is_array($data['source_affiliation_ids'] ?? null) ? $data['source_affiliation_ids'] : [],
        )));

        $relationships = [];
        foreach (is_array($data['members'] ?? null) ? $data['members'] : [] as $member) {
            if (! is_array($member)) {
                continue;
            }

            $affiliateId = (int) ($member['affiliate_id'] ?? 0);

            if ($affiliateId === 0) {
                continue;
            }

            $relationships[$affiliateId] = (string) ($member['relationship'] ?? '');
        }

        $user = Auth::user();

        return new MergeIndividualAffiliationsRequest(
            targetAffiliationId: (int) $record->id,
            sourceAffiliationIds: $sourceIds,
            titularAffiliateId: (int) ($data['titular_affiliate_id'] ?? 0),
            relationships: $relationships,
            reason: $forPreview ? (string) ($data['reason'] ?? 'Vista previa') : (string) ($data['reason'] ?? ''),
            actorName: (string) ($user?->name ?? 'SISTEMA'),
            actorUserId: $user?->id !== null ? (int) $user->id : null,
        );
    }

    private static function requestFromForm(Affiliation $record, Get $get, bool $forPreview = false): MergeIndividualAffiliationsRequest
    {
        return self::requestFromArray($record, [
            'source_affiliation_ids' => $get('source_affiliation_ids') ?? [],
            'titular_affiliate_id' => $get('titular_affiliate_id'),
            'members' => $get('members') ?? [],
            'reason' => $get('reason') ?? '',
        ], $forPreview);
    }
}
