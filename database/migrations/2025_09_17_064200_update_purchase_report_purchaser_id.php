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
            // Add purchaser_id column after po_approved_date
            $table->unsignedBigInteger('purchaser_id')->nullable()->after('po_approved_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_reports', function (Blueprint $table) {
            // Drop the column when rolling back
            $table->dropColumn('purchaser_id');
        });
    }
};
