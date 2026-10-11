<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->string('gateway');
            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_order_id')->nullable();
            $table->string('method');
            $table->unsignedInteger('amount_inr');
            $table->unsignedInteger('amount_paid_inr')->default(0);
            $table->string('currency')->default('INR');
            $table->string('status')->default('pending');
            $table->json('gateway_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->unsignedInteger('refunded_amount_inr')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index(['appointment_id', 'status']);
            $table->index(['customer_profile_id', 'status']);
            $table->index(['gateway_payment_id']);
            $table->index(['gateway_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
