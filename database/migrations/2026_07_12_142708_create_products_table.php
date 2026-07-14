<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id_products');

            $table->string('label_products', 250);
            $table->decimal('price', 15, 2)->default(0);

            $table->string('state', 50)->nullable();

            $table->unsignedBigInteger('id_partners');
            $table->unsignedBigInteger('id_category');

            $table->timestamp('created_at')->nullable();
            $table->string('created_by', 250)->nullable();

            $table->timestamp('updated_at')->nullable();
            $table->string('updated_by', 250)->nullable();

            $table->softDeletes();
            $table->string('deleted_by', 50)->nullable();

            $table->foreign('id_partners')
                ->references('id_partners')
                ->on('partners')
                ->restrictOnDelete();

            $table->foreign('id_category')
                ->references('id_category')
                ->on('category')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};