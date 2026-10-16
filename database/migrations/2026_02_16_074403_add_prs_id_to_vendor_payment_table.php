<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_payment', function (Blueprint $table) {

            $table->foreignId('prs_id')
                  ->after('vendor_id')
                  ->constrained('purchase_reports') // ✅ correct table name
                  ->onDelete('cascade');

        });
    }

    public function down(): void
    {
        Schema::table('vendor_payment', function (Blueprint $table) {

            $table->dropForeign(['prs_id']);
            $table->dropColumn('prs_id');

        });
    }
};
