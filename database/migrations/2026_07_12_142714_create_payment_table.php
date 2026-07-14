<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment', function (Blueprint $table) {
            $table->bigIncrements('id_payment');

            $table->string('reference', 250);
            $table->string('transaction', 250)->nullable();
            $table->string('type', 250)->nullable();
            $table->string('token', 250)->nullable();
            $table->string('response', 50)->nullable();

            $table->string('status_transaction', 250)->nullable();
            $table->string('status', 50)->nullable();
            $table->string('state', 50)->nullable();

            $table->unsignedBigInteger('id_orders');

            $table->timestamp('created_at')->nullable();
            $table->string('created_by', 250)->nullable();

            $table->timestamp('updated_at')->nullable();
            $table->string('updated_by', 250)->nullable();

            $table->softDeletes();
            $table->string('deleted_by', 50)->nullable();

            $table->foreign('id_orders')
                ->references('id_orders')
                ->on('orders')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment');
    }
};