<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->bigIncrements('id_customers');

            $table->string('first_name_customers', 250);
            $table->string('last_name_customers', 250);
            $table->string('phone_customers', 250)->nullable();
            $table->string('mail_customers', 250)->nullable();

            $table->string('status', 50)->nullable();

            $table->timestamp('created_at')->nullable();
            $table->string('created_by', 250)->nullable();

            $table->timestamp('updated_at')->nullable();
            $table->string('updated_by', 250)->nullable();

            $table->softDeletes();
            $table->string('deleted_by', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};