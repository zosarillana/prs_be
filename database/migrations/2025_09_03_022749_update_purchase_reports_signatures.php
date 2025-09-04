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
            $table->string('tr_user_id')->after('pr_status')->nullable();
            $table->string('hod_user_id')->after('tr_user_id')->nullable();
            $table->timestamp('tr_signed_at')->after('hod_user_id')->nullable();
            $table->timestamp('hod_signed_at')->after('tr_signed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_reports', function (Blueprint $table) {
            $table->dropColumn([
                'tr_user_id',
                'hod_user_id',
                'tr_signed_at',
                'hod_signed_at'
            ]);
        });
    }
};
