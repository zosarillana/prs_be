<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_report_item_pos', function (Blueprint $table) {
            $table->id();

            // Link to purchase report
            $table->foreignId('purchase_report_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Index of the item in the PR JSON arrays
            $table->unsignedInteger('item_index');

            // PO info
            $table->string('po_number')->nullable();
            $table->foreignId('purchaser_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->enum('status', ['created', 'approved', 'cancelled'])->default('created');

            $table->timestamp('po_created_at')->nullable();
            $table->timestamp('po_approved_at')->nullable();

            $table->timestamps();

            // Prevent duplicate PO for same item
            $table->unique(['purchase_report_id', 'item_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_report_item_pos');
    }
};
