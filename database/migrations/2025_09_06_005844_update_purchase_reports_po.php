<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('po_no')->nullable()->after('series_no');
            $table->string('po_status')->nullable()->after('po_no');
            $table->timestamp('po_created_date')->nullable()->after('updated_at');
            $table->timestamp('po_approved_date')->nullable()->after('po_created_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_reports', function (Blueprint $table) {
            $table->dropColumn(['po_no', 'po_status', 'po_created_date', 'po_approved_date']);
        });
    }
};
