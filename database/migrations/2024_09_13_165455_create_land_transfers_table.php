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
        Schema::create('land_transfers', function (Blueprint $table) {
            $table->id();
            $table->integer('land_id');
            $table->integer('seller_user_id');
            $table->integer('buyer_user_id');
            $table->string('transfer_type');
            $table->string('asset_type');
            $table->unsignedBigInteger('asset_amount');
            $table->integer('land_transfer_times')->default(false);
            // shares
            $table->string('receiver_share_amount');
            $table->string('bank_share_amount');
            $table->string('foundation_share_amount');
            $table->string('inviter_share_amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('land_transfers');
    }
};
