<?php

declare(strict_types=1);

use App\Enums\CompanyAssociateStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_associates', function (Blueprint $table): void {
            $table->string('status', 64)
                ->default(CompanyAssociateStatus::ActivoSinVaucherIls->value)
                ->after('registration_period_days');
            $table->text('annulment_reason')->nullable()->after('status');
            $table->timestamp('annulled_at')->nullable()->after('annulment_reason');

            $table->index('status');
        });

        DB::table('company_associates')
            ->whereNull('status')
            ->orWhere('status', '')
            ->update([
                'status' => CompanyAssociateStatus::ActivoSinVaucherIls->value,
            ]);

        DB::table('company_associates')
            ->where(function ($query): void {
                $query->whereNotNull('vaucher_ils')
                    ->orWhereNotNull('document_ils')
                    ->orWhereNotNull('date_init')
                    ->orWhereNotNull('date_end');
            })
            ->where('status', CompanyAssociateStatus::ActivoSinVaucherIls->value)
            ->update([
                'status' => CompanyAssociateStatus::Activo->value,
            ]);
    }

    public function down(): void
    {
        Schema::table('company_associates', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'annulment_reason', 'annulled_at']);
        });
    }
};
