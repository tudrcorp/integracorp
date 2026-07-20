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
        if (Schema::hasTable('birthday_notification_deliveries')) {
            return;
        }

        Schema::create('birthday_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('birthday_notification_id');
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('delivery_date');
            $table->string('email_status')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->text('email_error')->nullable();
            $table->string('whatsapp_status')->nullable();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->text('whatsapp_error')->nullable();
            $table->timestamps();

            $table->foreign('birthday_notification_id', 'bnd_notification_id_fk')
                ->references('id')
                ->on('birthday_notifications')
                ->cascadeOnDelete();

            $table->index(['birthday_notification_id', 'delivery_date'], 'bnd_notification_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('birthday_notification_deliveries');
    }
};
