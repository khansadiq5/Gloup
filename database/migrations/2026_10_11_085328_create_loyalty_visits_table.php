<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->string('source_type'); // 'appointment' or 'walk_in'
            $table->unsignedBigInteger('source_id');
            $table->string('status')->default('pending');
            $table->unsignedInteger('visit_number')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Compound unique index for idempotency
            $table->unique(['branch_id', 'source_type', 'source_id']);
            $table->index(['branch_id', 'customer_profile_id']);
            $table->index(['branch_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_visits');
    }
};
