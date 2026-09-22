<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->string('unit', 30)->default('pcs');
            $table->text('description')->nullable();

            // Stock levels
            $table->decimal('stock_on_hand', 10, 2)->default(0);
            $table->decimal('stock_on_order', 10, 2)->default(0);
            $table->decimal('stock_reserved', 10, 2)->default(0);

            // ML Params (inputs)
            $table->unsignedSmallInteger('lead_time_days')->default(7);
            $table->unsignedSmallInteger('coverage_period')->default(30); // days

            // ML Outputs (computed)
            $table->decimal('safety_stock', 10, 2)->default(0);
            $table->decimal('rop', 10, 2)->default(0);
            $table->decimal('max_stock', 10, 2)->default(0);
            $table->decimal('avg_usage', 10, 4)->default(0);
            $table->decimal('planning_usage', 10, 4)->default(0);
            $table->decimal('cv_value', 8, 4)->default(0);
            $table->enum('demand_type', ['regular', 'intermittent'])->default('regular');
            $table->timestamp('last_ml_update')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
