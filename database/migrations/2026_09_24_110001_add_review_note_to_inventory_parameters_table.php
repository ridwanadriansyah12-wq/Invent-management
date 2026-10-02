<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_parameters', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_parameters', 'review_note')) {
                $table->text('review_note')->nullable()->after('flag_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_parameters', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_parameters', 'review_note')) {
                $table->dropColumn('review_note');
            }
        });
    }
};
