<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->json('config')->nullable(); // discount_percent, discount_amount, free_service_id, etc.
            $table->unsignedInteger('required_visits')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('expiry_days')->nullable();
            $table->timestamps();

            $table->index(['loyalty_program_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
