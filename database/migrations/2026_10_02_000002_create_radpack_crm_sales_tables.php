<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['crm_quotes', 'crm_invoices'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $table->id();
                $table->string('number')->unique();
                $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
                $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
                $table->string('title')->nullable();
                $table->string('status')->default('draft')->index();
                $table->char('currency', 3);
                $table->date('issue_date');
                $table->date($tableName === 'crm_quotes' ? 'valid_until' : 'due_date')->nullable();
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('discount', 14, 2)->default(0);
                $table->decimal('tax_total', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->text('terms')->nullable();
                $table->string('token', 64)->unique();
                $table->timestamp('sent_at')->nullable();
                $table->json('data')->nullable();
                $table->timestamps();

                if ($tableName === 'crm_quotes') {
                    $table->timestamp('responded_at')->nullable();
                    $table->string('responded_by')->nullable();
                } else {
                    $table->unsignedBigInteger('quote_id')->nullable()->index();
                    $table->decimal('amount_paid', 14, 2)->default(0);
                    $table->timestamp('paid_at')->nullable();
                }
            });
        }

        Schema::create('crm_line_items', function (Blueprint $table) {
            $table->id();
            $table->morphs('document');
            $table->unsignedInteger('position')->default(0);
            $table->text('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->string('tax_name')->nullable();
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->decimal('total', 14, 2)->default(0);
        });

        Schema::create('crm_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->index();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('crm_invoices')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('type')->default('sale');
            $table->string('status')->default('succeeded')->index();
            $table->decimal('amount', 14, 2);
            $table->decimal('fee', 14, 2)->default(0);
            $table->char('currency', 3);
            $table->date('date')->index();
            $table->string('source')->default('manual');
            $table->string('external_id')->nullable()->index();
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['crm_transactions', 'crm_line_items', 'crm_invoices', 'crm_quotes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
