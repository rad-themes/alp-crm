<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->string('user_id')->nullable()->index();
        });

        Schema::create('crm_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key_hash', 64)->unique();
            $table->string('hint', 8);
            $table->boolean('can_write')->default(true);
            $table->string('user_id')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('url');
            $table->json('events');
            $table->string('secret', 64);
            $table->boolean('active')->default(true);
            $table->string('source')->default('cp');
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_webhooks');
        Schema::dropIfExists('crm_api_keys');

        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
