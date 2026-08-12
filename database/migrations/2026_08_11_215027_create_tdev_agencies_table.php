<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tdev_agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('identification_number')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->date('anniversary_date')->nullable();
            $table->string('representative_name')->nullable();
            $table->date('representative_birth_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_additional')->nullable();
            $table->string('instagram_username')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->text('address')->nullable();
            $table->string('logo')->nullable();
            $table->string('url')->nullable();
            $table->uuid('registration_token')->unique();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tdev_agencies');
    }
};
