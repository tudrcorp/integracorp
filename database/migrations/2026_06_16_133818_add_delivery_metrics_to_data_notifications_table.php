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
        if (! Schema::hasTable('data_notifications')) {
            return;
        }

        Schema::table('data_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('data_notifications', 'email_status')) {
                $table->string('email_status')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('data_notifications', 'email_sent_at')) {
                $table->timestamp('email_sent_at')->nullable()->after('email_status');
            }

            if (! Schema::hasColumn('data_notifications', 'email_error')) {
                $table->text('email_error')->nullable()->after('email_sent_at');
            }

            if (! Schema::hasColumn('data_notifications', 'whatsapp_status')) {
                $table->string('whatsapp_status')->nullable()->after('email_error');
            }

            if (! Schema::hasColumn('data_notifications', 'whatsapp_sent_at')) {
                $table->timestamp('whatsapp_sent_at')->nullable()->after('whatsapp_status');
            }

            if (! Schema::hasColumn('data_notifications', 'whatsapp_error')) {
                $table->text('whatsapp_error')->nullable()->after('whatsapp_sent_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('data_notifications')) {
            return;
        }

        Schema::table('data_notifications', function (Blueprint $table) {
            $columns = [
                'email_status',
                'email_sent_at',
                'email_error',
                'whatsapp_status',
                'whatsapp_sent_at',
                'whatsapp_error',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('data_notifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
