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
            // Add new json column after 'tag'
            $table->json('item_status')->after('tag')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_reports', function (Blueprint $table) {
            $table->dropColumn('item_status');
        });
    }
};
