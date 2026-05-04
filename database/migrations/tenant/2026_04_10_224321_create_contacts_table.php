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
            $table->foreignId('identification_type_id')->constrained()->on('identification_types')->cascadeOnDelete();
            $table->string('identification', 13)->unique();
            $table->string('name', 300);
            // Si tipo de identificación proveedor RUC 3er digito 6 o 9 pasa a 02
            $table->string('provider_type', 2)->default('01');
            $table->string('address', 300)->nullable();
            $table->boolean('special_contribution')->default(false);
            $table->boolean('accounting')->default(false);
            $table->integer('retention_agent')->nullable();
            $table->boolean('phantom_taxpayer')->default(false);
            $table->boolean('no_transactions')->default(false);
            // Al import se puede ver tipo de contribuyente se puede poner aqui
            $table->foreignId('contributor_type_id')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 50)->nullable();
            $table->json('data_additional')->nullable();
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
