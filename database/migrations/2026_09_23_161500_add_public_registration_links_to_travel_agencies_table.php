<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('travel_agencies')) {
            return;
        }

        if (! Schema::hasColumn('travel_agencies', 'parent_id')) {
            Schema::table('travel_agencies', function (Blueprint $table): void {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->constrained('travel_agencies')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('travel_agencies', 'registration_token')) {
            Schema::table('travel_agencies', function (Blueprint $table): void {
                $table->uuid('registration_token')->nullable()->unique();
            });
        }

        if (! Schema::hasColumn('travel_agencies', 'agency_registration_token')) {
            Schema::table('travel_agencies', function (Blueprint $table): void {
                $table->uuid('agency_registration_token')->nullable()->unique();
            });
        }

        $agencies = DB::table('travel_agencies')
            ->where(function ($query): void {
                $query->whereNull('registration_token')
                    ->orWhereNull('agency_registration_token');
            })
            ->get(['id', 'registration_token', 'agency_registration_token']);

        foreach ($agencies as $agency) {
            DB::table('travel_agencies')
                ->where('id', $agency->id)
                ->update([
                    'registration_token' => $agency->registration_token ?: (string) Str::uuid(),
                    'agency_registration_token' => $agency->agency_registration_token ?: (string) Str::uuid(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('travel_agencies')) {
            return;
        }

        Schema::table('travel_agencies', function (Blueprint $table): void {
            if (Schema::hasColumn('travel_agencies', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }

            if (Schema::hasColumn('travel_agencies', 'registration_token')) {
                $table->dropUnique(['registration_token']);
                $table->dropColumn('registration_token');
            }

            if (Schema::hasColumn('travel_agencies', 'agency_registration_token')) {
                $table->dropUnique(['agency_registration_token']);
                $table->dropColumn('agency_registration_token');
            }
        });
    }
};
