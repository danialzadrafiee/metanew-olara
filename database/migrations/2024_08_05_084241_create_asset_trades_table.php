<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asset_trades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->double('amount');
            $table->double('price');
            $table->string('asset_type');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_trades');
    }
};