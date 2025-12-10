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
            // Modify existing 'po_no' column to string (VARCHAR 50), nullable
            $table->string('po_no', 50)->nullable()->change();

            // Add new columns if they don't exist
            if (!Schema::hasColumn('purchase_reports', 'po_status')) {
                $table->string('po_status')->nullable()->after('po_no');
            }

            if (!Schema::hasColumn('purchase_reports', 'po_created_date')) {
                $table->timestamp('po_created_date')->nullable()->after('updated_at');
            }

            if (!Schema::hasColumn('purchase_reports', 'po_approved_date')) {
                $table->timestamp('po_approved_date')->nullable()->after('po_created_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_reports', function (Blueprint $table) {
            // Revert 'po_no' to unsignedBigInteger (nullable)
            $table->unsignedBigInteger('po_no')->nullable()->change();

            // Drop the new columns
            $table->dropColumn(['po_status', 'po_created_date', 'po_approved_date']);
        });
    }
};
