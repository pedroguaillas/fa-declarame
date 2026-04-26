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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('ruc', 13)->unique();
            $table->string('name', 300);
            $table->string('matrix_address', 300);
            $table->integer('special_contribution')->nullable();
            $table->boolean('accounting')->default(false);
            $table->integer('retention_agent')->nullable();
            $table->boolean('phantom_taxpayer')->default(false);
            $table->boolean('no_transactions')->default(false);
            $table->foreignId('contributor_type_id')->constrained('contributor_types');
            $table->string('phone', 20)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('type_declaration')->nullable(); // mensual | semestral
            $table->string('pass_sri', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
