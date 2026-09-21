<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('referidor_assignments')) {
            Schema::create('referidor_assignments', function (Blueprint $table): void {
                $table->id();
                $table->string('assignment_key', 64);
                $table->foreignId('referrer_agency_id')
                    ->nullable()
                    ->constrained('agencies')
                    ->cascadeOnDelete();
                $table->foreignId('referrer_agent_id')
                    ->nullable()
                    ->constrained('agents')
                    ->cascadeOnDelete();
                $table->foreignId('referred_agency_id')
                    ->nullable()
                    ->constrained('agencies')
                    ->cascadeOnDelete();
                $table->foreignId('referred_agent_id')
                    ->nullable()
                    ->constrained('agents')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique('assignment_key', 'ref_asg_key_unique');
                $table->index(['referrer_agency_id', 'referred_agency_id'], 'ref_asg_rra_rda');
                $table->index(['referrer_agency_id', 'referred_agent_id'], 'ref_asg_rra_rdt');
                $table->index(['referrer_agent_id', 'referred_agency_id'], 'ref_asg_rrt_rda');
                $table->index(['referrer_agent_id', 'referred_agent_id'], 'ref_asg_rrt_rdt');
                $table->index(['referred_agency_id'], 'ref_asg_rda');
                $table->index(['referred_agent_id'], 'ref_asg_rdt');
            });
        }

        $this->backfillFromLegacyColumns();
    }

    public function down(): void
    {
        Schema::dropIfExists('referidor_assignments');
    }

    private function backfillFromLegacyColumns(): void
    {
        if (! Schema::hasTable('referidor_assignments')) {
            return;
        }

        $now = now();

        if (Schema::hasTable('agencies') && Schema::hasColumn('agencies', 'referidor_id')) {
            $this->insertAssignments(
                DB::table('agencies')
                    ->select(['id as referred_id', 'referidor_id as referrer_id'])
                    ->whereNotNull('referidor_id')
                    ->whereColumn('id', '!=', 'referidor_id')
                    ->get(),
                referrerPrefix: 'agency',
                referredPrefix: 'agency',
                now: $now,
            );
        }

        if (Schema::hasTable('agencies') && Schema::hasColumn('agencies', 'referidor_agent_id')) {
            $this->insertAssignments(
                DB::table('agencies')
                    ->select(['id as referred_id', 'referidor_agent_id as referrer_id'])
                    ->whereNotNull('referidor_agent_id')
                    ->get(),
                referrerPrefix: 'agent',
                referredPrefix: 'agency',
                now: $now,
            );
        }

        if (Schema::hasTable('agents') && Schema::hasColumn('agents', 'referidor_id')) {
            $this->insertAssignments(
                DB::table('agents')
                    ->select(['id as referred_id', 'referidor_id as referrer_id'])
                    ->whereNotNull('referidor_id')
                    ->get(),
                referrerPrefix: 'agency',
                referredPrefix: 'agent',
                now: $now,
            );
        }

        if (Schema::hasTable('agents') && Schema::hasColumn('agents', 'referidor_agent_id')) {
            $this->insertAssignments(
                DB::table('agents')
                    ->select(['id as referred_id', 'referidor_agent_id as referrer_id'])
                    ->whereNotNull('referidor_agent_id')
                    ->whereColumn('id', '!=', 'referidor_agent_id')
                    ->get(),
                referrerPrefix: 'agent',
                referredPrefix: 'agent',
                now: $now,
            );
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function insertAssignments(mixed $rows, string $referrerPrefix, string $referredPrefix, mixed $now): void
    {
        foreach ($rows as $row) {
            $referrerId = (int) ($row->referrer_id ?? 0);
            $referredId = (int) ($row->referred_id ?? 0);

            if ($referrerId <= 0 || $referredId <= 0) {
                continue;
            }

            $assignmentKey = $referrerPrefix.':'.$referrerId.'|'.$referredPrefix.':'.$referredId;

            $exists = DB::table('referidor_assignments')
                ->where('assignment_key', $assignmentKey)
                ->exists();

            if ($exists) {
                continue;
            }

            if ($referrerPrefix === 'agency' && ! DB::table('agencies')->where('id', $referrerId)->exists()) {
                continue;
            }

            if ($referrerPrefix === 'agent' && ! DB::table('agents')->where('id', $referrerId)->exists()) {
                continue;
            }

            if ($referredPrefix === 'agency' && ! DB::table('agencies')->where('id', $referredId)->exists()) {
                continue;
            }

            if ($referredPrefix === 'agent' && ! DB::table('agents')->where('id', $referredId)->exists()) {
                continue;
            }

            DB::table('referidor_assignments')->insert([
                'assignment_key' => $assignmentKey,
                'referrer_agency_id' => $referrerPrefix === 'agency' ? $referrerId : null,
                'referrer_agent_id' => $referrerPrefix === 'agent' ? $referrerId : null,
                'referred_agency_id' => $referredPrefix === 'agency' ? $referredId : null,
                'referred_agent_id' => $referredPrefix === 'agent' ? $referredId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
