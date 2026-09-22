<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Kolom parameter komputasi Machine Learning murni
            $table->decimal('ml_safety_stock', 10, 2)->default(0)->after('coverage_period');
            $table->decimal('ml_rop', 10, 2)->default(0)->after('ml_safety_stock');

            // Flag dan nilai intervensi manusia (Human-in-the-Loop)
            $table->boolean('is_manual_override')->default(false)->after('ml_rop');
            $table->decimal('manual_safety_stock', 10, 2)->nullable()->after('is_manual_override');
            $table->decimal('manual_rop', 10, 2)->nullable()->after('manual_safety_stock');
            $table->string('override_reason', 255)->nullable()->after('manual_rop');
            $table->foreignId('override_updated_by')->nullable()->after('override_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('override_updated_at')->nullable()->after('override_updated_by');

            // Index gabungan untuk scan stock checker yang cepat
            $table->index(['is_active', 'stock_on_hand'], 'idx_items_stock_scan');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('idx_items_stock_scan');
            $table->dropForeign(['override_updated_by']);
            $table->dropColumn([
                'ml_safety_stock',
                'ml_rop',
                'is_manual_override',
                'manual_safety_stock',
                'manual_rop',
                'override_reason',
                'override_updated_by',
                'override_updated_at',
            ]);
        });
    }
};
