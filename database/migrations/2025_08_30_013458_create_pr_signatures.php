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
        Schema::create('pr_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_report_id')
                  ->constrained('purchase_reports')
                  ->onDelete('cascade');
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->string('role')->nullable();  
            $table->timestamp('signed_at')->nullable(); 
            $table->boolean('is_approved')->default(false);
            $table->timestamp('date_created')->useCurrent();
            $table->timestamp('date_updated')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_signatures');
    }
};
