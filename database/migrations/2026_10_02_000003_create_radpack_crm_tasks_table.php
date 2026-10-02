<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type')->default('task');
            $table->string('priority')->default('normal');
            $table->text('description')->nullable();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->unsignedInteger('reminder_minutes')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->string('assigned_to')->nullable()->index();
            $table->string('created_by')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
    }
};
