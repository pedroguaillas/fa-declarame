<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->on('companies')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->on('accounts')->cascadeOnDelete();
            $table->unique(['product_id', 'company_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_accounts');
    }
};
