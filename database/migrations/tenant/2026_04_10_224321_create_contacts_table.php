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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('identification', 13)->unique();
            $table->string('name', 300);
            $table->string('address', 300)->nullable();
            $table->boolean('special_contribution')->default(false);
            $table->boolean('accounting')->default(false);
            $table->boolean('retention_agent')->default(false);
            $table->boolean('phantom_taxpayer')->default(false);
            $table->boolean('no_transactions')->default(false);
            $table->string('rimpe', 50)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
