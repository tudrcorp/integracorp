<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('birthday_celebration_accesses')) {
            Schema::create('birthday_celebration_accesses', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('birthday_notification_delivery_id')->nullable();
                $table->string('public_token', 64)->unique();
                $table->string('full_name');
                $table->string('document_number', 32)->nullable();
                $table->string('phone', 32)->nullable();
                $table->date('birth_date')->nullable();
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->index(['birthday_notification_delivery_id'], 'bca_delivery_idx');
            });
        }

        if (! Schema::hasTable('birthday_coupon_tokens')) {
            Schema::create('birthday_coupon_tokens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('birthday_celebration_access_id')
                    ->constrained('birthday_celebration_accesses')
                    ->cascadeOnDelete();
                $table->string('coupon_type', 32);
                $table->string('public_token', 64)->unique();
                $table->string('status', 32)->default('pending');
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->unsignedBigInteger('used_by_user_id')->nullable();
                $table->string('used_from_ip', 45)->nullable();
                $table->timestamps();

                $table->unique(['birthday_celebration_access_id', 'coupon_type'], 'bct_access_type_unique');
                $table->index(['status', 'expires_at'], 'bct_status_expires_idx');
            });
        }

        if (Schema::hasTable('birthday_notification_deliveries')) {
            Schema::table('birthday_notification_deliveries', function (Blueprint $table): void {
                if (! Schema::hasColumn('birthday_notification_deliveries', 'document_number')) {
                    $table->string('document_number', 32)->nullable()->after('phone');
                }
                if (! Schema::hasColumn('birthday_notification_deliveries', 'birth_date')) {
                    $table->date('birth_date')->nullable()->after('document_number');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_coupon_tokens');
        Schema::dropIfExists('birthday_celebration_accesses');
    }
};
