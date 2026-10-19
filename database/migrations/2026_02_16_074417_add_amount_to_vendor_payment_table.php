<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_payment', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)
                  ->after('prs_id')
                  ->default(0); // optional default value
        });
    }

    public function down(): void
    {
        Schema::table('vendor_payment', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};
