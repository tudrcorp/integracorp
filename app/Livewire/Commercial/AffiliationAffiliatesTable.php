<?php

declare(strict_types=1);

namespace App\Livewire\Commercial;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Support\Affiliations\AffiliateDocumentsPackage;
use App\Support\Filament\CommercialNetworkTelemedicineScope;
use App\Support\FilamentDateDisplay;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Tabla de afiliados de una afiliación para los paneles comerciales (agentes, MASTER
 * y GENERAL), con descarga de la documentación de cada persona.
 *
 * Se monta dentro del modal de `ListAffiliationAffiliatesAction`, así que solo recibe
 * identificadores: el alcance se revalida en cada petición contra la red del usuario
 * autenticado, nunca se confía en lo que llega del navegador.
 */
class AffiliationAffiliatesTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public int $affiliationId = 0;

    public bool $corporate = false;

    public function mount(int $affiliationId, bool $corporate = false): void
    {
        $this->affiliationId = $affiliationId;
        $this->corporate = $corporate;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->affiliatesQuery())
            ->defaultSort('id')
            ->emptyStateHeading('Sin afiliados registrados')
            ->emptyStateDescription('Esta afiliación todavía no tiene personas cargadas en su grupo.')
            ->emptyStateIcon('heroicon-o-users')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->columns($this->corporate ? $this->corporateColumns() : $this->individualColumns())
            ->recordActions([
                Action::make('download_affiliate_documents')
                    ->label('Documentación')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->tooltip('Descarga el condicionado del plan y el carnet en un ZIP')
                    ->action(fn (Model $record) => $this->downloadDocuments($record)),
            ]);
    }

    public function render(): View
    {
        return view('livewire.commercial.affiliation-affiliates-table');
    }

    /**
     * @return array<int, TextColumn>
     */
    private function individualColumns(): array
    {
        return [
            TextColumn::make('relationship')
                ->label('Parentesco')
                ->badge()
                ->color(fn (?string $state): string => $state === 'TITULAR' ? 'primary' : 'gray')
                ->sortable()
                ->searchable(),
            TextColumn::make('full_name')
                ->label('Nombre completo')
                ->icon('heroicon-o-user')
                ->weight(FontWeight::SemiBold)
                ->wrap()
                ->searchable()
                ->sortable(),
            TextColumn::make('nro_identificacion')
                ->label('C.I.')
                ->fontFamily(FontFamily::Mono)
                ->searchable()
                ->copyable()
                ->copyMessage('C.I. copiada'),
            TextColumn::make('age')
                ->label('Edad')
                ->alignCenter()
                ->sortable(),
            TextColumn::make('birth_date')
                ->label('Nacimiento')
                ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                ->toggleable(),
            TextColumn::make('sex')
                ->label('Sexo')
                ->badge()
                ->color('gray')
                ->toggleable(),
            TextColumn::make('plan.description')
                ->label('Plan')
                ->badge()
                ->color('success')
                ->limit(24)
                ->searchable(),
            TextColumn::make('status')
                ->label('Estatus')
                ->badge()
                ->color(fn (?string $state): string => match ($state) {
                    'ACTIVO' => 'success',
                    'INACTIVO', 'EXCLUIDO' => 'danger',
                    default => 'warning',
                })
                ->sortable(),
        ];
    }

    /**
     * @return array<int, TextColumn>
     */
    private function corporateColumns(): array
    {
        return [
            TextColumn::make('relationship')
                ->label('Parentesco')
                ->badge()
                ->color(fn (?string $state): string => $state === 'TITULAR' ? 'primary' : 'gray')
                ->sortable()
                ->searchable(),
            TextColumn::make('first_name')
                ->label('Nombre completo')
                ->icon('heroicon-o-user')
                ->weight(FontWeight::SemiBold)
                ->wrap()
                ->formatStateUsing(fn (AffiliateCorporate $record): string => trim(
                    trim((string) $record->first_name).' '.trim((string) $record->last_name)
                ))
                ->searchable(['first_name', 'last_name'])
                ->sortable(),
            TextColumn::make('nro_identificacion')
                ->label('C.I.')
                ->fontFamily(FontFamily::Mono)
                ->searchable()
                ->copyable()
                ->copyMessage('C.I. copiada'),
            TextColumn::make('position_company')
                ->label('Cargo')
                ->limit(24)
                ->toggleable()
                ->searchable(),
            TextColumn::make('age')
                ->label('Edad')
                ->alignCenter()
                ->sortable(),
            TextColumn::make('birth_date')
                ->label('Nacimiento')
                ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                ->toggleable(),
            TextColumn::make('plan.description')
                ->label('Plan')
                ->badge()
                ->color('success')
                ->limit(24)
                ->searchable(),
            TextColumn::make('status')
                ->label('Estatus')
                ->badge()
                ->color(fn (?string $state): string => match ($state) {
                    'ACTIVO' => 'success',
                    'INACTIVO', 'EXCLUIDO' => 'danger',
                    default => 'warning',
                })
                ->sortable(),
        ];
    }

    private function affiliatesQuery(): Builder
    {
        $affiliation = $this->authorizedAffiliation();

        if ($affiliation === null) {
            return $this->corporate
                ? AffiliateCorporate::query()->whereRaw('1 = 0')
                : Affiliate::query()->whereRaw('1 = 0');
        }

        if ($this->corporate) {
            return AffiliateCorporate::query()
                ->with('plan:id,description')
                ->where('affiliation_corporate_id', $affiliation->getKey());
        }

        return Affiliate::query()
            ->with('plan:id,description')
            ->where('affiliation_id', $affiliation->getKey());
    }

    /**
     * La afiliación solo se devuelve si pertenece a la red del usuario autenticado:
     * un id manipulado desde el navegador no debe exponer la población de otra agencia.
     */
    private function authorizedAffiliation(): Affiliation|AffiliationCorporate|null
    {
        $user = Auth::user();

        if (! $user instanceof User || $this->affiliationId <= 0) {
            return null;
        }

        $affiliation = $this->corporate
            ? AffiliationCorporate::query()->find($this->affiliationId)
            : Affiliation::query()->find($this->affiliationId);

        if ($affiliation === null) {
            return null;
        }

        return CommercialNetworkTelemedicineScope::affiliationBelongsToUser($affiliation, $user)
            ? $affiliation
            : null;
    }

    private function downloadDocuments(Model $record): ?BinaryFileResponse
    {
        $affiliation = $this->authorizedAffiliation();

        if ($affiliation === null || ! $this->recordBelongsToAffiliation($record, $affiliation)) {
            Notification::make()
                ->title('Sin acceso a la documentación')
                ->body('No tiene permisos sobre este afiliado.')
                ->danger()
                ->send();

            return null;
        }

        $package = $affiliation instanceof AffiliationCorporate
            ? AffiliateDocumentsPackage::forCorporate($affiliation, $record)
            : AffiliateDocumentsPackage::forIndividual($affiliation, $record);

        $auditDetails = [
            'affiliation_id' => $affiliation->getKey(),
            'affiliation_code' => $affiliation->code,
            'affiliate_id' => $record->getKey(),
            'identification' => $package->identification,
            'corporate' => $this->corporate,
            'missing' => $package->missingLabels(),
        ];

        if (! $package->hasAnyDocument()) {
            SecurityAudit::log(
                'AUDIT_COMMERCIAL_AFFILIATE_DOCUMENTS_UNAVAILABLE',
                'commercial.affiliates.download-documents',
                $auditDetails,
            );

            Notification::make()
                ->title('Documentación no disponible')
                ->body('Todavía no se han emitido el condicionado ni el carnet de '.$package->affiliateName.'. Solicite la emisión al área de Negocios.')
                ->warning()
                ->persistent()
                ->send();

            return null;
        }

        try {
            $zipPath = $package->buildZip();
        } catch (Throwable $exception) {
            SecurityAudit::log(
                'AUDIT_COMMERCIAL_AFFILIATE_DOCUMENTS_FAILED',
                'commercial.affiliates.download-documents',
                $auditDetails + ['error' => $exception->getMessage()],
            );

            Notification::make()
                ->title('No se pudo preparar la descarga')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return null;
        }

        if ($package->missingLabels() !== []) {
            Notification::make()
                ->title('Documentación incompleta')
                ->body('Falta '.implode(' y ', $package->missingLabels()).' de '.$package->affiliateName.'. Se descargó lo que ya está emitido; solicite el resto al área de Negocios.')
                ->warning()
                ->persistent()
                ->send();
        }

        SecurityAudit::log(
            'AUDIT_COMMERCIAL_AFFILIATE_DOCUMENTS_DOWNLOADED',
            'commercial.affiliates.download-documents',
            $auditDetails,
        );

        return response()
            ->download($zipPath, $package->downloadFilename())
            ->deleteFileAfterSend(true);
    }

    private function recordBelongsToAffiliation(Model $record, Affiliation|AffiliationCorporate $affiliation): bool
    {
        if ($affiliation instanceof AffiliationCorporate) {
            return $record instanceof AffiliateCorporate
                && (int) $record->affiliation_corporate_id === (int) $affiliation->getKey();
        }

        return $record instanceof Affiliate
            && (int) $record->affiliation_id === (int) $affiliation->getKey();
    }
}
