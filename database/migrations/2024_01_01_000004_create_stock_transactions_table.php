<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('type', ['in', 'out', 'adjustment']);
            $table->decimal('quantity', 10, 2);
            $table->decimal('stock_before', 10, 2)->default(0);
            $table->decimal('stock_after', 10, 2)->default(0);
            $table->string('reference_no', 100)->nullable();
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->timestamps();

            $table->index(['item_id', 'transaction_date']);
            $table->index(['type', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
