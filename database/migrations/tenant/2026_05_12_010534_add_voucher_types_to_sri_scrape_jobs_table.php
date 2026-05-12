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
        Schema::table('sri_scrape_jobs', function (Blueprint $table) {
            $table->json('voucher_types')->nullable()->after('mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sri_scrape_jobs', function (Blueprint $table) {
            $table->dropColumn('voucher_types');
        });
    }
};
