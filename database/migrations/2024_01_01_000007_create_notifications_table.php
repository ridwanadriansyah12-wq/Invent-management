<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->enum('type', ['low_stock', 'out_of_stock', 'reorder', 'overstock', 'po_received', 'system']);
            $table->string('title', 200);
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->enum('target_role', ['all', 'procurement', 'gudang', 'it'])->default('all');
            $table->timestamps();

            $table->index(['is_read', 'created_at']);
            $table->index('target_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
