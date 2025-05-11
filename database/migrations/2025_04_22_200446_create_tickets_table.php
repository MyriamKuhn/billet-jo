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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->json('product_snapshot');
            $table->uuid('token')->unique();
            $table->string('qr_filename');
            $table->string('pdf_filename');
            $table->enum('status', ['issued','used','refunded','cancelled'])->default('issued')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreignId('user_id')->constrained('users');

            $table->foreignId('payment_id')->constrained('payments');

            $table->foreignId('product_id')->constrained('products');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
