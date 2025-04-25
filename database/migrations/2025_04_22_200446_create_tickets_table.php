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
            $table->string('qr_code_link');
            $table->string('pdf_link');
            $table->boolean('is_used')->default(false);
            $table->boolean('is_refunded')->default(false);
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
