<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('lead')->index();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('owner_id')->nullable()->index();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('lead')->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->string('owner_id')->nullable()->index();
            $table->json('data')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_contact_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('crm_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('crm_taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained('crm_tags')->cascadeOnDelete();
            $table->morphs('taggable');
            $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
        });

        Schema::create('crm_notes', function (Blueprint $table) {
            $table->id();
            $table->morphs('notable');
            $table->string('type')->default('note');
            $table->text('body');
            $table->string('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('event');
            $table->string('description');
            $table->json('properties')->nullable();
            $table->string('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['crm_activities', 'crm_notes', 'crm_taggables', 'crm_tags', 'crm_contact_aliases', 'crm_contacts', 'crm_companies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
