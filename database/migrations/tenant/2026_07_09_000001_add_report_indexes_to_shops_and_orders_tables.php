<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->index(['company_id', 'emision', 'state'], 'shops_company_emision_state_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['company_id', 'emision', 'state'], 'orders_company_emision_state_idx');
        });

        // PostgreSQL no crea índices para las FK automáticamente
        Schema::table('shop_items', function (Blueprint $table) {
            $table->index('shop_id', 'shop_items_shop_id_idx');
        });

        Schema::table('shop_retention_items', function (Blueprint $table) {
            $table->index('shop_id', 'shop_retention_items_shop_id_idx');
        });

        Schema::table('order_retention_items', function (Blueprint $table) {
            $table->index('order_id', 'order_retention_items_order_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropIndex('shops_company_emision_state_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_company_emision_state_idx');
        });

        Schema::table('shop_items', function (Blueprint $table) {
            $table->dropIndex('shop_items_shop_id_idx');
        });

        Schema::table('shop_retention_items', function (Blueprint $table) {
            $table->dropIndex('shop_retention_items_shop_id_idx');
        });

        Schema::table('order_retention_items', function (Blueprint $table) {
            $table->dropIndex('order_retention_items_order_id_idx');
        });
    }
};
