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
        if (Schema::hasTable('vendor_payment')) {
            return;
        }

        Schema::create('vendor_payment', function (Blueprint $table) {
            $table->id();
            $table->string('po_number');
            $table->unsignedBigInteger('vendor_id');
            $table->enum('cheque_status', [
                'on_process',
                'available',
                'closed'
            ])->default('on_process');
            $table->string('cheque_number')->nullable();
            $table->date('date_served')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_payment');
    }
};
