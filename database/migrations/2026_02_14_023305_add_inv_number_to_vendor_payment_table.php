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
        if (!Schema::hasColumn('vendor_payment', 'inv_number')) {
            Schema::table('vendor_payment', function (Blueprint $table) {
                $table->string('inv_number')
                    ->nullable()
                    ->after('po_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('vendor_payment', 'inv_number')) {
            Schema::table('vendor_payment', function (Blueprint $table) {
                $table->dropColumn('inv_number');
            });
        }
    }
};