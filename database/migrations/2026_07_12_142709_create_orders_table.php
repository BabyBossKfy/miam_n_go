<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id_orders');

            $table->string('reference', 250);
            $table->decimal('price', 15, 2)->default(0);

            $table->string('status_order', 50)->nullable();
            $table->string('status_delivery', 50)->nullable();
            $table->string('status_payment', 50)->nullable();
            $table->string('state', 50)->nullable();

            $table->unsignedBigInteger('id_customers');

            $table->timestamp('created_at')->nullable();
            $table->string('created_by', 250)->nullable();

            $table->timestamp('updated_at')->nullable();
            $table->string('updated_by', 250)->nullable();

            $table->softDeletes();
            $table->string('deleted_by', 50)->nullable();

            $table->foreign('id_customers')
                ->references('id_customers')
                ->on('customers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};