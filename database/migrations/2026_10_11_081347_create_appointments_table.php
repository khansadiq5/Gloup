<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('total_price_inr')->default(0);
            $table->unsignedInteger('total_duration_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->string('payment_status')->default('unpaid');
            $table->json('metadata')->nullable();
            $table->string('management_token')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['branch_id', 'starts_at', 'ends_at']);
            $table->index(['staff_id', 'starts_at', 'ends_at']);
            $table->index(['customer_profile_id', 'starts_at']);
            $table->index(['starts_at', 'status']);
            $table->index('management_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
