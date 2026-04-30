<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->on('accounts')->cascadeOnDelete();
            $table->decimal('quantity', 14, 6);
            $table->decimal('unit_price', 14, 6);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_value', 14, 2)->default(0);
            $table->json('data_additional')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_items');
    }
};
