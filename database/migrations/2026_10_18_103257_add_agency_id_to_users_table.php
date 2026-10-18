<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user who belongs to an agency is an AGENT: they can manage that agency's
 * properties in the admin area (see PropertyPolicy). NULL = a normal user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // nullOnDelete: if an agency is deleted, its agents become normal
            // users instead of being deleted or blocking the delete.
            $table->foreignId('agency_id')->nullable()->after('is_admin')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drops the foreign key constraint, its index, then the column.
            $table->dropConstrainedForeignId('agency_id');
        });
    }
};
