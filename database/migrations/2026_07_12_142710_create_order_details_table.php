<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_details', function (Blueprint $table) {
            $table->bigIncrements('id_order_details');

            $table->unsignedBigInteger('id_orders');
            $table->unsignedBigInteger('id_products');

            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);

            $table->string('status_order', 50)->nullable();
            $table->string('status_delivery', 50)->nullable();
            $table->string('status_payment', 50)->nullable();
            $table->string('state', 50)->nullable();

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

            $table->foreign('id_products')
                ->references('id_products')
                ->on('products')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};