<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->string('name');
            $table->string('disk');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime')->nullable();
            $table->boolean('portal')->default(false);
            $table->string('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_passwords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->cascadeOnDelete();
            $table->string('label');
            $table->text('url')->nullable();
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->text('notes')->nullable();
            $table->string('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_passwords');
        Schema::dropIfExists('crm_files');
    }
};
