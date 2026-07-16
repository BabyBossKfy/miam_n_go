<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_users', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('id_users');
            $table->unsignedBigInteger('id_partners');

            $table->string('state', 50)->default('ACTIVE');

            $table->timestamps();

            $table->foreign('id_users')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('id_partners')
                ->references('id_partners')
                ->on('partners')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_users');
    }
};