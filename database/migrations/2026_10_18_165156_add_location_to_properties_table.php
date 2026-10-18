<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Map position of a property, filled in from its postcode by the
 * GeocodeProperty job. Nullable: unknown until the job has run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // decimal(10, 7): up to ±999.9999999, i.e. about 1 cm precision.
            // Never float: floats store approximations (0.1 + 0.2 != 0.3).
            $table->decimal('latitude', 10, 7)->nullable()->after('postcode');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
