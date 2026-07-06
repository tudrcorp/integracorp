<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\IndividualQuoteController;
use App\Models\Agency;
use App\Models\IndividualQuote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class IndividualQuotePdfGenerator
{
    /**
     * @param  list<array<string, mixed>>  $detailsQuote
     */
    public static function generateForQuote(IndividualQuote $record, array $detailsQuote = []): bool
    {
        $agentName = self::resolveAgentName($record);

        if ($record->plan === 'CM') {
            return self::generateMultiple($record, $detailsQuote, $agentName);
        }

        $planId = (int) $record->plan;
        $layout = IndividualQuotePdfLayout::resolve($planId);
        $details = self::buildDetailsPayload($record, $planId, $layout, $agentName);

        if ($details === null) {
            return false;
        }

        IndividualQuoteController::generatePdf($details, Auth::id(), $layout);

        return true;
    }

    public static function regenerateIfMissing(IndividualQuote $record): bool
    {
        $path = public_path('storage/quotes/'.$record->code.'.pdf');

        if (file_exists($path)) {
            return true;
        }

        return self::generateForQuote($record);
    }

    /**
     * @param  list<array<string, mixed>>  $detailsQuote
     */
    private static function generateMultiple(IndividualQuote $record, array $detailsQuote, string $agentName): bool
    {
        $planIds = collect($detailsQuote)
            ->pluck('plan_id')
            ->filter()
            ->unique()
            ->values();

        if ($planIds->isEmpty()) {
            $planIds = DB::table('detail_individual_quotes')
                ->where('individual_quote_id', $record->id)
                ->distinct()
                ->pluck('plan_id');
        }

        $groupDetails = [];
        $processedPlanIds = [];

        foreach ($planIds as $planId) {
            $planId = (int) $planId;

            if (in_array($planId, $processedPlanIds, true)) {
                continue;
            }

            $processedPlanIds[] = $planId;
            $layout = IndividualQuotePdfLayout::resolve($planId);
            $details = self::buildDetailsPayload($record, $planId, $layout, $agentName);

            if ($details !== null) {
                $groupDetails[] = $details;
            }
        }

        if ($groupDetails === []) {
            return false;
        }

        usort($groupDetails, fn (array $a, array $b): int => (int) $a['plan'] <=> (int) $b['plan']);

        IndividualQuoteController::generatePdfMultiple($groupDetails, Auth::id());

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function buildDetailsPayload(
        IndividualQuote $record,
        int $planId,
        string $layout,
        string $agentName,
    ): ?array {
        $query = DB::table('detail_individual_quotes')
            ->join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
            ->join('age_ranges', 'detail_individual_quotes.age_range_id', '=', 'age_ranges.id')
            ->select(
                'detail_individual_quotes.*',
                'plans.description as plan',
                'age_ranges.range as age_range',
            )
            ->where('individual_quote_id', $record->id)
            ->where('detail_individual_quotes.plan_id', $planId);

        if (IndividualQuotePdfLayout::usesCoverageBreakdown($layout)) {
            $query->join('coverages', 'detail_individual_quotes.coverage_id', '=', 'coverages.id')
                ->addSelect('coverages.price as coverage');
        }

        $data = $query->get()->toArray();

        if ($data === []) {
            return null;
        }

        return [
            'plan' => $planId,
            'layout' => $layout,
            'code' => $record->code,
            'name' => $record->full_name,
            'agent_name' => $agentName,
            'email' => $record->email,
            'phone' => $record->phone,
            'date' => $record->created_at->format('d-m-Y'),
            'data' => $data,
        ];
    }

    private static function resolveAgentName(IndividualQuote $record): string
    {
        if ($record->agent_id !== null) {
            return $record->agent?->name ?? Auth::user()->name;
        }

        if ($record->code_agency !== null && $record->code_agency !== 'TDG-100') {
            return Agency::query()->where('code', $record->code_agency)->value('name_corporative') ?? Auth::user()->name;
        }

        return Auth::user()->name;
    }
}
