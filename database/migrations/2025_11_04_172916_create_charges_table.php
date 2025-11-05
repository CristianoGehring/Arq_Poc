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
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_gateway_id')->nullable()->constrained();
            $table->string('gateway_charge_id')->nullable()->unique();
            $table->decimal('amount', 10, 2);
            $table->string('description');
            $table->enum('payment_method', ['credit_card', 'debit_card', 'boleto', 'pix']);
            $table->enum('status', ['pending', 'paid', 'cancelled', 'refunded', 'expired', 'failed'])
                  ->default('pending');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices para performance
            $table->index(['customer_id', 'status']);
            $table->index('gateway_charge_id');
            $table->index('due_date');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};