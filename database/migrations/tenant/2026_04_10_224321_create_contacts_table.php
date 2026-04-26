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
            $table->foreignId('identification_type_id')->constrained('identification_types');
            $table->string('identification', 13)->unique();
            $table->string('name', 300);
            $table->string('address', 300)->nullable();
            $table->boolean('special_contribution')->default(false);
            $table->boolean('accounting')->default(false);
            $table->integer('retention_agent')->nullable();
            $table->boolean('phantom_taxpayer')->default(false);
            $table->boolean('no_transactions')->default(false);
            // Al import se puede ver tipo de contribuyente se puede poner aqui
            $table->unsignedBigInteger('contributor_type_id')->nullable();
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
