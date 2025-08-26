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
        Schema::create('purchase_reports', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('series_no');         // For series/PR number
            $table->string('pr_purpose');        // Purpose of the PR
            $table->string('department');        // Department name
            $table->date('date_submitted');      // Date submitted
            $table->date('date_needed');         // Date needed

            $table->json('quantity');
            $table->json('unit');
            $table->json('item_description');
            $table->json('tag');
            $table->json('remarks');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_reports');
    }
};
