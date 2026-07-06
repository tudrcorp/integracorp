<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('mass_notifications')) {
            return;
        }

        Schema::table('mass_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('mass_notifications', 'test_email_success_count')) {
                $table->unsignedInteger('test_email_success_count')->default(0)->after('email_subject');
            }

            if (! Schema::hasColumn('mass_notifications', 'test_email_failed_count')) {
                $table->unsignedInteger('test_email_failed_count')->default(0)->after('test_email_success_count');
            }

            if (! Schema::hasColumn('mass_notifications', 'last_test_email_to')) {
                $table->string('last_test_email_to')->nullable()->after('test_email_failed_count');
            }

            if (! Schema::hasColumn('mass_notifications', 'last_test_email_at')) {
                $table->timestamp('last_test_email_at')->nullable()->after('last_test_email_to');
            }

            if (! Schema::hasColumn('mass_notifications', 'last_test_email_error')) {
                $table->text('last_test_email_error')->nullable()->after('last_test_email_at');
            }

            if (! Schema::hasColumn('mass_notifications', 'test_whatsapp_success_count')) {
                $table->unsignedInteger('test_whatsapp_success_count')->default(0)->after('last_test_email_error');
            }

            if (! Schema::hasColumn('mass_notifications', 'test_whatsapp_failed_count')) {
                $table->unsignedInteger('test_whatsapp_failed_count')->default(0)->after('test_whatsapp_success_count');
            }

            if (! Schema::hasColumn('mass_notifications', 'last_test_whatsapp_to')) {
                $table->string('last_test_whatsapp_to')->nullable()->after('test_whatsapp_failed_count');
            }

            if (! Schema::hasColumn('mass_notifications', 'last_test_whatsapp_at')) {
                $table->timestamp('last_test_whatsapp_at')->nullable()->after('last_test_whatsapp_to');
            }

            if (! Schema::hasColumn('mass_notifications', 'last_test_whatsapp_error')) {
                $table->text('last_test_whatsapp_error')->nullable()->after('last_test_whatsapp_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('mass_notifications')) {
            return;
        }

        Schema::table('mass_notifications', function (Blueprint $table) {
            $columns = [
                'test_email_success_count',
                'test_email_failed_count',
                'last_test_email_to',
                'last_test_email_at',
                'last_test_email_error',
                'test_whatsapp_success_count',
                'test_whatsapp_failed_count',
                'last_test_whatsapp_to',
                'last_test_whatsapp_at',
                'last_test_whatsapp_error',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('mass_notifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
