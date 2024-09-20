<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('web3_meta_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tx_hash')->unique();
            $table->string('from_address');
            $table->string('to_address');
            $table->double('amount');
            $table->enum('type', ['deposit', 'withdrawal'])->nullable();
            $table->boolean('is_processed')->default(false);
            $table->unsignedBigInteger('block_number');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('web3_meta_transactions');
    }
};