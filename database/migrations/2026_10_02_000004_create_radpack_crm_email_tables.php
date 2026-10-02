<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('email_token', 40)->nullable()->unique();
        });

        Schema::create('crm_email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->longText('body');
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->string('to');
            $table->string('subject');
            $table->longText('body');
            $table->string('status')->default('sent')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->string('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('match')->default('all');
            $table->json('conditions');
            $table->timestamps();
        });

        Schema::create('crm_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->longText('body');
            $table->foreignId('segment_id')->nullable()->constrained('crm_segments')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('crm_campaigns')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->string('email');
            $table->string('token', 40)->unique();
            $table->string('status')->default('queued')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->text('error')->nullable();
            $table->unique(['campaign_id', 'email']);
        });
    }

    public function down(): void
    {
        foreach (['crm_campaign_recipients', 'crm_campaigns', 'crm_segments', 'crm_emails', 'crm_email_templates'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropUnique(['email_token']);
            $table->dropColumn(['unsubscribed_at', 'email_token']);
        });
    }
};
