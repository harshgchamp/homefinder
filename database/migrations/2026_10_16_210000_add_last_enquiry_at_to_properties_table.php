<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * properties already exists, so this is a NEW migration with Schema::table,
 * never an edit to the original create_properties_table migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Nullable: existing properties have never had an enquiry recorded.
            // Filled by the RecordLastEnquiryOnProperty listener.
            $table->timestamp('last_enquiry_at')->nullable()->after('published_at');

            // The archive command filters on it ("no enquiry in the last 30 days").
            $table->index('last_enquiry_at');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Drop the index before the column it is built on.
            $table->dropIndex(['last_enquiry_at']);
            $table->dropColumn('last_enquiry_at');
        });
    }
};
