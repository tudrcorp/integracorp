<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para la búsqueda global de usuarios en el panel Negocios.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! $this->hasIndex('users_name_index') && Schema::hasColumn('users', 'name')) {
                $table->index('name', 'users_name_index');
            }

            if (! $this->hasIndex('users_identity_card_index') && Schema::hasColumn('users', 'identity_card')) {
                $table->index('identity_card', 'users_identity_card_index');
            }

            if (! $this->hasIndex('users_phone_index') && Schema::hasColumn('users', 'phone')) {
                $table->index('phone', 'users_phone_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if ($this->hasIndex('users_name_index')) {
                $table->dropIndex('users_name_index');
            }

            if ($this->hasIndex('users_identity_card_index')) {
                $table->dropIndex('users_identity_card_index');
            }

            if ($this->hasIndex('users_phone_index')) {
                $table->dropIndex('users_phone_index');
            }
        });
    }

    private function hasIndex(string $name): bool
    {
        foreach (Schema::getIndexes('users') as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
