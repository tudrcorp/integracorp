<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AnnualCollections\Pages;

use App\Filament\Administration\Pages\DashboardCobranza;
use App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource;
use App\Filament\Concerns\ReviewsTableSelection;
use App\Models\User;
use App\Support\Collections\CollectionFollowUp;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ListAnnualCollections extends ListRecords
{
    use ReviewsTableSelection;

    protected static string $resource = AnnualCollectionResource::class;

    protected static ?string $title = 'Cuentas por cobrar';

    /**
     * «Ver KPIs» abre el Dashboard de Cobranza, donde viven los indicadores que antes iban
     * sobre esta tabla.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('kpis')
                ->label('Ver KPIs')
                ->icon('heroicon-o-presentation-chart-bar')
                ->color('gray')
                ->url(DashboardCobranza::getUrl())
                ->visible(fn (): bool => DashboardCobranza::canAccess()),
        ];
    }

    /**
     * @return array{singular: string, plural: string}
     */
    protected function selectionReviewNouns(): array
    {
        return ['singular' => 'cuenta por cobrar', 'plural' => 'cuentas por cobrar'];
    }

    /**
     * Pulsa una casilla de la columna «Estado de gestión» ($option) o, sin casilla, quita todos
     * los estados. La regla de combinación la aplica el servidor. No vuelve a dibujar la tabla:
     * la casilla ya se actualizó en el navegador y solo necesita saber cómo quedó guardado (o lo
     * vigente, si otra persona lo cambió).
     *
     * @return array{ok: bool, status: ?string, message: ?string}
     */
    public function setCollectionFollowUp(int $collectionId, ?string $option = null, ?string $expected = null): array
    {
        $this->skipRender();
        $user = Auth::user();

        if (! $user instanceof User) {
            return ['ok' => false, 'status' => $expected, 'message' => 'Su sesión expiró. Vuelva a iniciar sesión.'];
        }

        try {
            $result = filled($option)
                ? CollectionFollowUp::toggle($collectionId, (string) $option, $expected, $user)
                : CollectionFollowUp::clear($collectionId, $expected, $user);
        } catch (InvalidArgumentException $exception) {
            return ['ok' => false, 'status' => $expected, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            Log::error('ListAnnualCollections: no se guardó el estado de gestión', ['collection_id' => $collectionId, 'message' => $exception->getMessage()]);

            return ['ok' => false, 'status' => $expected, 'message' => 'No se guardó el estado. Intente de nuevo.'];
        }

        if ($result['conflict']) {
            return ['ok' => false, 'status' => $result['status'], 'message' => 'Otra persona lo cambió a «'.CollectionFollowUp::label($result['status']).'». Se muestra lo vigente.'];
        }

        return ['ok' => true, 'status' => $result['status'], 'message' => null];
    }
}
