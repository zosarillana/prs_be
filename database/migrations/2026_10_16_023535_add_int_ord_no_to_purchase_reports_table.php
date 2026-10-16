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
        Schema::table('purchase_reports', function (Blueprint $table) {
            // One internal order # per line item, stored as a JSON array
            $table->json('int_ord_no')->nullable()->after('tag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_reports', function (Blueprint $table) {
            $table->dropColumn('int_ord_no');
        });
    }
};