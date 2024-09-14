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
            $table->unsignedBigInteger('land_id');
            $table->unsignedBigInteger('seller_user_id');
            $table->unsignedBigInteger('buyer_user_id');
            $table->unsignedBigInteger('land_transfer_times')->default(0);
            $table->double('asset_amount');
            $table->string('transfer_type');
            $table->string('asset_type');
            // shares
            $table->double('receiver_share_amount');
            $table->double('bank_share_amount');
            $table->double('foundation_share_amount');
            $table->double('inviter_share_amount');
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
