<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_details', function (Blueprint $table) {
            $table->bigIncrements('id_delivery_details');

            $table->string('product', 250);
            $table->string('status', 50)->nullable();
            $table->string('state', 50)->nullable();

            $table->unsignedBigInteger('id_delivery');

            $table->timestamp('created_at')->nullable();
            $table->string('created_by', 250)->nullable();

            $table->timestamp('updated_at')->nullable();
            $table->string('updated_by', 250)->nullable();

            $table->softDeletes();
            $table->string('deleted_by', 50)->nullable();

            $table->foreign('id_delivery')
                ->references('id_delivery')
                ->on('delivery')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_details');
    }
};