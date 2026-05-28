<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sri_scrape_jobs', function (Blueprint $table) {
            $table->tinyInteger('day')->nullable()->after('month');
            $table->string('source', 20)->default('manual')->after('mode'); // manual | automatic
        });
    }

    public function down(): void
    {
        Schema::table('sri_scrape_jobs', function (Blueprint $table) {
            $table->dropColumn(['day', 'source']);
        });
    }
};
