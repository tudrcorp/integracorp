<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_desks', function (Blueprint $table): void {
            if (! Schema::hasColumn('help_desks', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('help_desks', 'latest_note_by_user_id')) {
                $table->foreignId('latest_note_by_user_id')->nullable()->after('latest_note_by')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('help_desks', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable()->after('ticket_type');
            }

            if (! Schema::hasColumn('help_desks', 'first_response_due_at')) {
                $table->timestamp('first_response_due_at')->nullable()->after('terms_accepted_at');
            }

            if (! Schema::hasColumn('help_desks', 'resolution_due_at')) {
                $table->timestamp('resolution_due_at')->nullable()->after('first_response_due_at');
            }

            if (! Schema::hasColumn('help_desks', 'first_responded_at')) {
                $table->timestamp('first_responded_at')->nullable()->after('resolution_due_at');
            }

            if (! Schema::hasColumn('help_desks', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('first_responded_at');
            }

            if (! Schema::hasColumn('help_desks', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('resolved_at');
            }

            if (! Schema::hasColumn('help_desks', 'sla_breached_at')) {
                $table->timestamp('sla_breached_at')->nullable()->after('cancelled_at');
            }

            if (! Schema::hasColumn('help_desks', 'cancellation_reason')) {
                $table->string('cancellation_reason')->nullable()->after('sla_breached_at');
            }
        });

        if (! Schema::hasTable('help_desk_csats')) {
            Schema::create('help_desk_csats', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('help_desk_id')->constrained('help_desks')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedTinyInteger('score');
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->unique('help_desk_id');
            });
        }

        $this->backfillCreatorUserIds();
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_csats');

        Schema::table('help_desks', function (Blueprint $table): void {
            foreach ([
                'created_by_user_id',
                'latest_note_by_user_id',
                'terms_accepted_at',
                'first_response_due_at',
                'resolution_due_at',
                'first_responded_at',
                'resolved_at',
                'cancelled_at',
                'sla_breached_at',
                'cancellation_reason',
            ] as $column) {
                if (Schema::hasColumn('help_desks', $column)) {
                    if (in_array($column, ['created_by_user_id', 'latest_note_by_user_id'], true)) {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }

    private function backfillCreatorUserIds(): void
    {
        if (! Schema::hasColumn('help_desks', 'created_by_user_id')) {
            return;
        }

        $users = DB::table('users')
            ->select('id', 'name')
            ->whereNotNull('name')
            ->get()
            ->groupBy(static fn (object $user): string => mb_strtolower(trim((string) $user->name)));

        DB::table('help_desks')
            ->whereNull('created_by_user_id')
            ->whereNotNull('created_by')
            ->orderBy('id')
            ->chunkById(200, function ($tickets) use ($users): void {
                foreach ($tickets as $ticket) {
                    $key = mb_strtolower(trim((string) $ticket->created_by));
                    $matches = $users->get($key);

                    if ($matches === null || $matches->count() !== 1) {
                        continue;
                    }

                    DB::table('help_desks')
                        ->where('id', $ticket->id)
                        ->update(['created_by_user_id' => (int) $matches->first()->id]);
                }
            });
    }
};
