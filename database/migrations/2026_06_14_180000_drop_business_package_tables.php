<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('business_package_coverages');
        Schema::dropIfExists('business_package_age_ranges');
        Schema::dropIfExists('business_package_benefit_coverages');
        Schema::dropIfExists('business_package_sub_benefits');
        Schema::dropIfExists('business_package_benefits');
        Schema::dropIfExists('business_packages');
        Schema::dropIfExists('business_package_benefit_catalog');
    }

    public function down(): void
    {
        // El módulo Constructor de Paquetes fue retirado; no se restauran las tablas.
    }
};
