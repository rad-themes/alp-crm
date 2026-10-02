<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_automations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->string('trigger')->index();
            $table->json('trigger_options')->nullable();
            $table->string('match')->default('all');
            $table->json('conditions')->nullable();
            $table->json('actions');
            $table->unsignedInteger('runs_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('crm_automations')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->cascadeOnDelete();
            $table->unsignedSmallInteger('step');
            $table->string('status')->default('pending')->index();
            $table->timestamp('run_at')->index();
            $table->json('context')->nullable();
            $table->text('result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_automation_runs');
        Schema::dropIfExists('crm_automations');
    }
};
