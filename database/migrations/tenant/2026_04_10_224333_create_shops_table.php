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
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acount_id')->nullable()->constrained()->on('acounts')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->on('companies')->cascadeOnDelete();
            $table->foreignId('voucher_type_id')->constrained()->on('voucher_types')->cascadeOnDelete();
            $table->date('emision');
            $table->string('autorization', 49);
            $table->timestamp('autorized_at')->nullable();
            $table->string('serie', 17);
            $table->foreignId('contact_id')->constrained()->on('contacts');
            $table->decimal('sub_total')->default(0);
            $table->decimal('no_iva')->default(0);
            $table->decimal('exempt')->default(0);
            $table->decimal('base0')->default(0);
            $table->decimal('base5')->default(0);
            $table->decimal('base8')->default(0);
            $table->decimal('base12')->default(0);
            $table->decimal('base15')->default(0);
            $table->decimal('iva5')->default(0);
            $table->decimal('iva8')->default(0);
            $table->decimal('iva12')->default(0);
            $table->decimal('iva15')->default(0);
            $table->decimal('aditional_discount')->default(0);
            $table->decimal('discount')->default(0);
            $table->decimal('ice')->default(0);
            $table->decimal('total')->default(0);
            $table->string('state');
            $table->string('serie_retention', 17)->nullable();
            $table->date('date_retention')->nullable();
            $table->string('state_retention')->nullable();
            $table->string('autorization_retention', 49)->nullable();
            $table->timestamp('retention_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
