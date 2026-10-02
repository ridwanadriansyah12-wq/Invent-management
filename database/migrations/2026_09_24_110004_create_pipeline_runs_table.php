<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id')->unique();
            $table->enum('type', ['FORECAST', 'CLASSIFICATION']);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->enum('status', ['RUNNING', 'SUCCESS', 'PARTIAL', 'FAILED'])->default('RUNNING');
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->enum('triggered_by', ['SCHEDULER', 'MANUAL'])->default('SCHEDULER');
            $table->text('error_summary')->nullable();
            $table->json('settings_snapshot')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_runs');
    }
};
