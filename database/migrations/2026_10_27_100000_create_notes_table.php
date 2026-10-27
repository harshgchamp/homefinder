<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Private admin notes on properties AND agencies, in ONE table (polymorphic).
 *
 *   notable_type  notable_id  body
 *   property      74          Owner wants a quick sale
 *   agency        3           Contract renews in March
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();

            // morphs('notable') = notable_type (string) + notable_id (unsigned big int)
            // + one composite index on (notable_type, notable_id), which is exactly
            // what $property->notes runs: WHERE notable_type = ? AND notable_id = ?
            //
            // No foreign key is possible: notable_id may point at properties OR
            // agencies, and MySQL cannot express that. So no ON DELETE CASCADE either;
            // the HasNotes trait deletes the notes in code instead.
            $table->morphs('notable');

            // The author. A normal foreign key works here (always the users table).
            // nullOnDelete: if the user is deleted, keep the note without an author.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
