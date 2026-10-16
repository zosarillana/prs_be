<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('purchase_report_item_pos')) {
            Schema::create('purchase_report_item_pos', function (Blueprint $table) {
                $table->id();

                $table->foreignId('purchase_report_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->unsignedInteger('item_index');

                $table->string('po_number')->nullable();

                $table->foreignId('purchaser_id')
                      ->nullable()
                      ->constrained('users')
                      ->nullOnDelete();

                $table->enum('status', ['created', 'approved', 'cancelled'])
                      ->default('created');

                $table->timestamp('po_created_at')->nullable();
                $table->timestamp('po_approved_at')->nullable();

                $table->timestamps();

                $table->unique(['purchase_report_id', 'item_index']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_report_item_pos');
    }
};

