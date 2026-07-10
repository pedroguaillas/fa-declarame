<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sri_scrape_jobs', function (Blueprint $table) {
            $table->tinyInteger('end_month')->nullable()->after('month');
        });
    }

    public function down(): void
    {
        Schema::table('sri_scrape_jobs', function (Blueprint $table) {
            $table->dropColumn('end_month');
        });
    }
};
