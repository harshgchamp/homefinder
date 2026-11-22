<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('database')->unique();
            $table->string('status')->default('provisioning'); // provisioning | active | failed
            $table->timestamps();
        });

        Schema::create('organisation_user', function (Blueprint $table) {
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->primary(['organisation_id', 'user_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_user');
        Schema::dropIfExists('organisations');
    }
};
