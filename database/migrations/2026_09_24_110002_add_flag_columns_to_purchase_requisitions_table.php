<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requisitions', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requisitions', 'needs_review')) {
                $table->boolean('needs_review')->default(false)->after('status');
            }
            if (!Schema::hasColumn('purchase_requisitions', 'flag_reason')) {
                $table->string('flag_reason')->nullable()->after('needs_review');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requisitions', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_requisitions', 'flag_reason')) {
                $table->dropColumn('flag_reason');
            }
            if (Schema::hasColumn('purchase_requisitions', 'needs_review')) {
                $table->dropColumn('needs_review');
            }
        });
    }
};
